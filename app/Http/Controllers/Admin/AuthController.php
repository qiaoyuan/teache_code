<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['username' => 'required|string|max:64', 'password' => 'required|string|max:100']);
        abort_unless(Auth::guard('admin')->attempt($d), 401);
        $r->session()->regenerate();
        $r->session()->put('admin_auth_version', Auth::guard('admin')->user()->auth_version);

        return ['user' => Auth::guard('admin')->user(), 'csrf_token' => $r->session()->token()];
    }

    public function me(Request $r)
    {
        return ['user' => $r->user()];
    }

    public function logout(Request $r)
    {
        Auth::guard('admin')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return ['csrf_token' => $r->session()->token()];
    }
}
