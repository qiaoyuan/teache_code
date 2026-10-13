<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\ParentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $username = 'owner'): Administrator
    {
        return Administrator::create(['username' => $username, 'password' => 'AdminPass123']);
    }

    private function data(array $extra = []): array
    {
        return array_merge(['username' => 'New.Admin', 'password' => 'NewAdmin456', 'password_confirmation' => 'NewAdmin456', 'current_password' => 'AdminPass123'], $extra);
    }

    public function test_guests_and_parents_cannot_manage_administrators(): void
    {
        $this->getJson('/api/v1/admin/administrators')->assertUnauthorized();
        $parent = ParentAccount::create(['phone' => '13800000001', 'password' => 'Parent123']);
        $this->actingAs($parent, 'parent')->withSession(['parent_auth_version' => 1]);
        $this->postJson('/api/v1/admin/administrators', $this->data())->assertUnauthorized();
        $this->postJson('/api/v1/admin/administrators/1/password', $this->data())->assertUnauthorized();
        $this->assertDatabaseCount('administrators', 0);
    }

    public function test_create_list_and_login_new_administrator_without_exposing_passwords(): void
    {
        $actor = $this->admin();
        $this->actingAs($actor, 'admin');
        $this->postJson('/api/v1/admin/administrators', $this->data())->assertCreated()
            ->assertJsonPath('data.username', 'new.admin')->assertJsonMissingPath('data.password');
        $created = Administrator::where('username', 'new.admin')->firstOrFail();
        $this->assertTrue(Hash::check('NewAdmin456', $created->password));
        $this->getJson('/api/v1/admin/administrators')->assertOk()->assertJsonPath('total', 2)
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.auth_version');
        $audit = AuditLog::firstOrFail();
        $this->assertSame('administrator_created', $audit->action);
        $this->assertSame($actor->id, $audit->admin_id);
        $this->assertSame($created->id, $audit->target_id);
        $this->assertSame(['username' => 'new.admin'], $audit->details);
        Auth::forgetGuards();
        $this->postJson('/api/v1/admin/auth/login', ['username' => 'new.admin', 'password' => 'NewAdmin456'])
            ->assertOk()->assertJsonMissingPath('user.password')->assertSessionHas('admin_auth_version', 0);
    }

    public function test_validation_and_operator_password_fail_without_side_effects(): void
    {
        $this->actingAs($this->admin(), 'admin');
        foreach ([['current_password' => 'wrong'], ['password_confirmation' => 'mismatch'], ['password' => '12345678', 'password_confirmation' => '12345678'], ['username' => '../invalid']] as $extra) {
            $this->postJson('/api/v1/admin/administrators', $this->data($extra))->assertStatus(400);
        }
        $this->postJson('/api/v1/admin/administrators', $this->data(['username' => 'OWNER']))->assertStatus(400);
        $this->assertDatabaseCount('administrators', 1);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_password_change_revokes_only_target_admin_sessions_and_rejects_old_versions(): void
    {
        $target = $this->admin('target');
        $actor = $this->admin('operator');
        $parent = ParentAccount::create(['id' => $target->id, 'phone' => '13800000001', 'password' => 'Parent123']);
        $this->assertSame($target->id, $parent->id);
        foreach ([['target', 'admin', $target->id], ['actor', 'admin', $actor->id], ['parent', 'parent', $parent->id]] as [$id, $portal, $userId]) {
            DB::table('sessions')->insert(['id' => $id, 'portal' => $portal, 'user_id' => $userId, 'payload' => '', 'last_activity' => time()]);
        }
        $this->actingAs($actor, 'admin');
        $this->postJson('/api/v1/admin/administrators/'.$target->id.'/password', $this->data())
            ->assertOk()->assertJsonPath('require_login', false);
        $target->refresh();
        $this->assertSame(1, $target->auth_version);
        $this->assertTrue(Hash::check('NewAdmin456', $target->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'target']);
        $this->assertDatabaseHas('sessions', ['id' => 'actor']);
        $this->assertDatabaseHas('sessions', ['id' => 'parent']);
        $this->assertSame(['username' => 'target'], AuditLog::firstOrFail()->details);
        $this->actingAs($target, 'admin')->withSession(['admin_auth_version' => 0]);
        $this->getJson('/api/v1/admin/administrators')->assertUnauthorized();
        Auth::forgetGuards();
        $this->postJson('/api/v1/admin/auth/login', ['username' => 'target', 'password' => 'AdminPass123'])->assertUnauthorized();
        $this->postJson('/api/v1/admin/auth/login', ['username' => 'target', 'password' => 'NewAdmin456'])->assertOk()->assertSessionHas('admin_auth_version', 1);
    }

    public function test_self_password_change_logs_out_and_invalid_requests_do_not_change_password(): void
    {
        $actor = $this->admin();
        $this->actingAs($actor, 'admin');
        $this->postJson('/api/v1/admin/administrators/'.$actor->id.'/password', $this->data(['current_password' => 'wrong']))->assertStatus(400);
        $this->assertTrue(Hash::check('AdminPass123', $actor->fresh()->password));
        $this->postJson('/api/v1/admin/administrators/9999/password', $this->data())->assertNotFound();
        $this->postJson('/api/v1/admin/administrators/'.$actor->id.'/password', $this->data())->assertOk()->assertJsonPath('require_login', true);
        $this->assertGuest('admin');
        $this->getJson('/api/v1/admin/administrators')->assertUnauthorized();
    }

    public function test_stale_admin_page_redirects_to_login(): void
    {
        $actor = $this->admin();
        $actor->update(['auth_version' => 1]);
        $this->actingAs($actor, 'admin')->withSession(['admin_auth_version' => 0]);
        $this->get('/admin/administrators')->assertRedirect('/admin/login');
    }

    public function test_auth_version_migration_preserves_existing_admin(): void
    {
        $migration = require database_path('migrations/2026_10_13_000002_add_auth_version_to_administrators.php');
        $migration->down();
        $password = Hash::make('Existing123');
        $id = DB::table('administrators')->insertGetId(['username' => 'existing', 'password' => $password, 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $existing = Administrator::findOrFail($id);
        $this->assertSame($password, $existing->password);
        $this->assertSame(0, $existing->auth_version);
    }
}
