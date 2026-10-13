<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ParentAuthorization;
use Illuminate\Http\Request;

class ParentAuthorizationController extends Controller
{
    public function store(Request $request, string $id, ParentAuthorization $authorization)
    {
        return response()->json([
            'url' => $authorization->issue($request->user(), $id, $request->ip()),
        ])->header('Cache-Control', 'no-store');
    }
}
