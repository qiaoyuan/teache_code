<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Services\Admin\ParentAuthorization;
use Illuminate\Http\Request;

class AuthorizedLoginController extends Controller
{
    public function store(Request $request, ParentAuthorization $authorization)
    {
        $data = $request->validate(['token' => ['required', 'string', 'regex:/^[A-Za-z0-9]{64}$/']]);
        $authorization->consume($request, $data['token']);

        return response()->json([
            'user' => $request->user(),
            'authorized_login' => $authorization->info($request),
            'csrf_token' => $request->session()->token(),
        ])->header('Cache-Control', 'no-store');
    }
}
