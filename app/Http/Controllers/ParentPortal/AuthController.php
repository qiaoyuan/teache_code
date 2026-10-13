<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Models\ParentAccount;
use App\Services\Admin\ParentAuthorization;
use App\Services\Shared\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $data = $r->validate([
            'phone' => 'required|regex:/^1[3-9][0-9]{9}$/',
            'password' => [...Text::passwordRules(), 'confirmed'],
            'agreement_accepted' => ['required', 'accepted'],
            'agreement_version' => ['required', 'string', Rule::in([config('agreement.version')])],
        ], [
            'agreement_accepted.required' => '请阅读并同意用户协议与隐私政策',
            'agreement_accepted.accepted' => '请阅读并同意用户协议与隐私政策',
            'agreement_version.required' => '请刷新页面并阅读最新用户协议与隐私政策',
            'agreement_version.in' => '协议已更新，请刷新页面后重新阅读并同意',
        ]);
        $p = DB::transaction(function () use ($data) {
            abort_if(ParentAccount::where('phone', $data['phone'])->exists(), 409);

            return ParentAccount::create([
                'phone' => $data['phone'],
                'password' => $data['password'],
                'agreement_version' => config('agreement.version'),
                'agreement_accepted_at' => now(),
            ]);
        });
        $r->session()->forget(ParentAuthorization::SESSION_KEY);
        Auth::guard('parent')->login($p);
        $r->session()->regenerate();
        $r->session()->put('parent_auth_version', $p->auth_version);

        return response()->json(['user' => $p, 'csrf_token' => $r->session()->token()], 201);
    }

    public function login(Request $r)
    {
        $data = $r->validate(['phone' => 'required|string', 'password' => 'required|string|max:100']);
        $p = ParentAccount::where('phone', $data['phone'])->first();
        abort_unless($p && Hash::check($data['password'], $p->password), 401);

        // Re-read under lock so a concurrent administrator reset cannot authenticate an old password.
        return DB::transaction(function () use ($r, $p, $data) {
            $p = ParentAccount::whereKey($p->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($data['password'], $p->password), 401);
            $r->session()->forget(ParentAuthorization::SESSION_KEY);
            Auth::guard('parent')->login($p);
            $r->session()->regenerate();
            $r->session()->put('parent_auth_version', $p->auth_version);

            return ['user' => $p, 'csrf_token' => $r->session()->token()];
        });
    }

    public function me(Request $r)
    {
        return ['user' => $r->user(), 'authorized_login' => app(ParentAuthorization::class)->info($r)];
    }

    public function logout(Request $r)
    {
        app(ParentAuthorization::class)->finish($r);
        Auth::guard('parent')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return ['csrf_token' => $r->session()->token()];
    }

    public function changePassword(Request $r)
    {
        $d = $r->validate(['current_password' => 'required|string', 'password' => [...Text::passwordRules(), 'confirmed', 'different:current_password']]);

        return DB::transaction(function () use ($r, $d) {
            $p = ParentAccount::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($p->auth_version === $r->session()->get('parent_auth_version') && Hash::check($d['current_password'], $p->password), 403);
            $p->update(['password' => $d['password'], 'must_change_password' => false, 'auth_version' => $p->auth_version + 1]);
            Auth::guard('parent')->setUser($p);
            DB::table('sessions')->where('portal', 'parent')->where('user_id', $p->id)->delete();
            $r->session()->regenerate(true);
            $r->session()->put('parent_auth_version', $p->auth_version);

            return ['user' => $p, 'csrf_token' => $r->session()->token()];
        });
    }
}
