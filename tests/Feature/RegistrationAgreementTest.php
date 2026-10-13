<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationAgreementTest extends TestCase
{
    use RefreshDatabase;

    public function test_agreement_is_public_and_contains_the_supplied_text_and_contact(): void
    {
        $this->get('/app/agreement')->assertOk()
            ->assertSee('最近更新日期：2026年10月13日')
            ->assertSee('不满14周岁')
            ->assertSee('八、联系我们')
            ->assertSee('18117988868')
            ->assertSee('撤回同意：您可在登录后撤回授权');
        $this->get('/app/agreement?embedded=1')->assertOk()->assertDontSee('返回家长注册');
        $this->get('/app/register')->assertOk()->assertSee('name="agreement-version" content="2026-10-13"', false);
    }

    public function test_registration_rejects_missing_false_and_outdated_agreement_without_creating_an_account(): void
    {
        $data = ['phone' => '13800000031', 'password' => 'TestPass123', 'password_confirmation' => 'TestPass123'];
        $this->postJson('/api/v1/app/auth/register', $data)->assertBadRequest()->assertJsonValidationErrors(['agreement_accepted', 'agreement_version'], 'error.fields');
        $this->postJson('/api/v1/app/auth/register', [...$data, 'agreement_accepted' => false, 'agreement_version' => config('agreement.version')])->assertBadRequest()->assertJsonValidationErrors('agreement_accepted', 'error.fields');
        $this->postJson('/api/v1/app/auth/register', [...$data, 'agreement_accepted' => true, 'agreement_version' => '2026-10-12'])->assertBadRequest()->assertJsonValidationErrors('agreement_version', 'error.fields');
        $this->assertDatabaseCount('parents', 0);
    }

    public function test_registration_records_server_time_and_version_and_does_not_accept_client_timestamp(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(9, 30));
        $this->postJson('/api/v1/app/auth/register', [
            'phone' => '13800000032', 'password' => 'TestPass123', 'password_confirmation' => 'TestPass123',
            'agreement_accepted' => true, 'agreement_version' => config('agreement.version'),
            'agreement_accepted_at' => '1999-01-01 00:00:00',
        ])->assertCreated()->assertJsonPath('user.agreement_version', '2026-10-13')->assertJsonMissingPath('user.password');
        $parent = ParentAccount::firstOrFail();
        $this->assertSame('2026-10-13', $parent->agreement_version);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $parent->agreement_accepted_at->format('Y-m-d H:i:s'));
        $this->getJson('/api/v1/app/auth/me')->assertOk();
    }

    public function test_existing_accounts_without_consent_can_still_login(): void
    {
        $parent = ParentAccount::create(['phone' => '13800000033', 'password' => 'TestPass123']);
        $parent->refresh();
        $this->assertNull($parent->agreement_version);
        $this->assertNull($parent->agreement_accepted_at);
        $this->assertSame('id', Schema::getColumns('parents')[0]['name']);
        $this->assertSame('datetime', collect(Schema::getColumns('parents'))->firstWhere('name', 'agreement_accepted_at')['type_name']);
        $this->postJson('/api/v1/app/auth/login', ['phone' => $parent->phone, 'password' => 'TestPass123'])->assertOk();
    }
}
