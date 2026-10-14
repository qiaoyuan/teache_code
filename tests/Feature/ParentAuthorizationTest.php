<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\ParentAccount;
use App\Models\Student;
use App\Services\Admin\ParentAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ParentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function accounts(): array
    {
        return [
            Administrator::create(['username' => 'operator', 'password' => 'AdminPass123']),
            ParentAccount::create(['phone' => '13800000001', 'password' => 'ParentPass123']),
        ];
    }

    private function issue(Administrator $admin, ParentAccount $parent): string
    {
        config(['app.url' => 'https://www.shengxue360.com']);
        $response = $this->actingAs($admin, 'admin')->withSession(['admin_auth_version' => $admin->auth_version])
            ->postJson('/api/v1/admin/parents/'.$parent->id.'/authorize-login');
        $response->assertOk();
        $this->assertStringStartsWith('https://www.shengxue360.com/app/authorized-login#token=', $response->json('url'));

        return substr($response->json('url'), strpos($response->json('url'), '#token=') + 7);
    }

    private function consume(string $token)
    {
        Auth::forgetGuards();

        return $this->postJson('/api/v1/app/auth/authorized-login', ['token' => $token]);
    }

    public function test_only_an_authenticated_admin_can_issue_a_grant_without_a_password(): void
    {
        [$admin, $parent] = $this->accounts();
        $path = '/api/v1/admin/parents/'.$parent->id.'/authorize-login';
        $this->postJson($path)->assertUnauthorized();
        $this->actingAs($parent, 'parent')->withSession(['parent_auth_version' => 1]);
        $this->postJson($path)->assertUnauthorized();
        $this->actingAs($admin, 'admin');
        $this->postJson('/api/v1/admin/parents/9999/authorize-login')->assertNotFound();
        $this->assertDatabaseCount('audit_logs', 0);
        $this->issue($admin, $parent);
        $this->assertDatabaseCount('audit_logs', 1);
        $admin->update(['auth_version' => 1]);
        $this->postJson($path)->assertUnauthorized();
    }

    public function test_exchange_authenticates_only_the_target_parent_and_audits_without_secrets(): void
    {
        [$admin, $parent] = $this->accounts();
        $password = $parent->password;
        DB::table('sessions')->insert(['id' => 'admin-session', 'portal' => 'admin', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);
        $token = $this->issue($admin, $parent);
        $this->consume($token)->assertOk()->assertJsonPath('user.id', $parent->id)
            ->assertJsonPath('authorized_login.read_only', true)
            ->assertJsonPath('authorized_login.admin_username', 'operator')
            ->assertJsonMissingPath('user.password')->assertJsonMissingPath('user.auth_version');
        $this->getJson('/api/v1/app/auth/me')->assertOk()->assertJsonPath('authorized_login.read_only', true);
        $this->getJson('/api/v1/admin/parents')->assertUnauthorized();
        $this->assertDatabaseHas('sessions', ['id' => 'admin-session', 'portal' => 'admin']);
        $this->assertSame($password, $parent->fresh()->password);
        $this->assertSame(1, $parent->fresh()->auth_version);
        $this->assertDatabaseCount('audit_logs', 2);
        foreach (AuditLog::all() as $audit) {
            $this->assertSame($admin->id, $audit->admin_id);
            $this->assertSame($parent->id, $audit->target_id);
            $this->assertSame(['phone' => $parent->phone, 'read_only' => true], $audit->details);
            $this->assertStringNotContainsString($token, $audit->toJson());
        }
    }

    public function test_tokens_are_single_use_expire_and_cannot_be_forged(): void
    {
        [$admin, $parent] = $this->accounts();
        $token = $this->issue($admin, $parent);
        $this->consume(str_repeat('x', 64))->assertForbidden();
        $this->consume($token)->assertOk();
        $this->consume($token)->assertForbidden();
        $token = $this->issue($admin, $parent);
        $this->travel(61)->seconds();
        $this->consume($token)->assertForbidden();
        $this->assertSame(1, AuditLog::where('action', 'parent_authorization_started')->count());
    }

    public function test_password_changes_revoke_unconsumed_grants_for_either_role(): void
    {
        [$admin, $parent] = $this->accounts();
        $token = $this->issue($admin, $parent);
        $parent->increment('auth_version');
        $this->consume($token)->assertForbidden();
        $token = $this->issue($admin, $parent->fresh());
        $admin->increment('auth_version');
        $this->consume($token)->assertForbidden();
    }

    public function test_authorized_session_is_read_only_and_preserves_parent_ownership(): void
    {
        [$admin, $parent] = $this->accounts();
        $other = ParentAccount::create(['phone' => '13800000002', 'password' => 'OtherPass123']);
        $data = ['name' => '测试学生', 'gender' => 'unspecified', 'school' => '测试学校', 'class_number' => '3', 'entry_year' => 2024, 'entry_grade' => 'P1', 'current_grade' => 'P3', 'contact_phone' => $parent->phone];
        $ownStudent = Student::create(['parent_id' => $parent->id, ...$data]);
        $otherStudent = Student::create(['parent_id' => $other->id, ...$data]);
        $parent->update(['must_change_password' => true]);
        $this->consume($this->issue($admin, $parent))->assertOk();
        $this->getJson('/api/v1/app/students')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownStudent->id);
        $this->getJson('/api/v1/app/students/'.$ownStudent->id)->assertOk();
        $this->getJson('/api/v1/app/students/'.$otherStudent->id)->assertForbidden();
        foreach ([['POST', 'students'], ['PUT', 'students/'.$ownStudent->id], ['DELETE', 'students/'.$ownStudent->id], ['POST', 'students/'.$ownStudent->id.'/exams'], ['PUT', 'exams/1'], ['DELETE', 'exams/1'], ['POST', 'students/'.$ownStudent->id.'/intentions'], ['PUT', 'intentions/1'], ['DELETE', 'intentions/1'], ['POST', 'auth/change-password']] as [$method, $path]) {
            $this->json($method, '/api/v1/app/'.$path, $data)->assertForbidden()->assertJsonPath('error.code', 'AUTHORIZED_READ_ONLY');
        }
        $this->assertDatabaseCount('students', 2);
        $this->assertTrue($parent->fresh()->must_change_password);
    }

    public function test_active_authorized_session_expires_and_operator_password_change_revokes_it(): void
    {
        [$admin, $parent] = $this->accounts();
        $this->consume($this->issue($admin, $parent))->assertOk();
        $this->travel(30)->minutes();
        $this->getJson('/api/v1/app/students')->assertUnauthorized();
        $this->assertGuest('parent');
        $this->travelBack();
        $this->consume($this->issue($admin, $parent))->assertOk();
        $admin->increment('auth_version');
        $this->getJson('/api/v1/app/students')->assertUnauthorized();
    }

    public function test_parent_password_change_revokes_active_authorization(): void
    {
        [$admin, $parent] = $this->accounts();
        $this->consume($this->issue($admin, $parent))->assertOk();
        $parent->increment('auth_version');
        Auth::forgetGuards();
        $this->getJson('/api/v1/app/students')->assertUnauthorized();
    }

    public function test_logout_clears_authorization_and_regular_login_has_normal_permissions(): void
    {
        [$admin, $parent] = $this->accounts();
        $this->consume($this->issue($admin, $parent))->assertOk();
        $this->postJson('/api/v1/app/auth/logout')->assertOk()->assertSessionMissing(ParentAuthorization::SESSION_KEY);
        $this->getJson('/api/v1/app/auth/me')->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'parent_authorization_ended', 'admin_id' => $admin->id]);
        $this->postJson('/api/v1/app/auth/login', ['phone' => $parent->phone, 'password' => 'ParentPass123'])->assertOk();
        $this->getJson('/api/v1/app/auth/me')->assertOk()->assertJsonPath('authorized_login', null);
        $this->postJson('/api/v1/app/students', [])->assertStatus(400);
    }

    public function test_regular_password_login_clears_an_existing_authorization(): void
    {
        [$admin, $parent] = $this->accounts();
        $this->consume($this->issue($admin, $parent))->assertOk();
        $this->postJson('/api/v1/app/auth/login', ['phone' => $parent->phone, 'password' => 'ParentPass123'])->assertOk()
            ->assertSessionMissing(ParentAuthorization::SESSION_KEY);
        $this->getJson('/api/v1/app/auth/me')->assertJsonPath('authorized_login', null);
    }

    public function test_exchange_works_with_shared_database_cache_and_real_csrf_is_required(): void
    {
        config(['cache.default' => 'database']);
        [$admin, $parent] = $this->accounts();
        $token = $this->issue($admin, $parent);
        $this->consume($token)->assertOk();
        $this->consume($token)->assertForbidden();
        $this->assertNull(Cache::get('parent-authorization:'.hash('sha256', $token)));
        // Laravel normally skips CSRF in tests. Use the production middleware branch.
        $this->app->instance('env', 'production');
        $this->postJson('/api/v1/app/auth/authorized-login', ['token' => $token])->assertStatus(419);
        $this->actingAs($admin, 'admin')->postJson('/api/v1/admin/parents/'.$parent->id.'/authorize-login')->assertStatus(419);
    }
}
