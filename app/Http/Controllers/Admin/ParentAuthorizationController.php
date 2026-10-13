<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ParentAuthorization;
use Illuminate\Http\Request;

class ParentAuthorizationController extends Controller
{
    public function store(Request $request, string $id, ParentAuthorization $authorization)
    {
        $request->validate(['current_password' => ['required', 'string', 'max:100', 'current_password:admin']], [
            'current_password.current_password' => '管理员密码不正确',
        ]);

        return response()->json([
            'url' => $authorization->issue($request->user(), $id, $request->ip()),
        ])->header('Cache-Control', 'no-store');
    }
}
