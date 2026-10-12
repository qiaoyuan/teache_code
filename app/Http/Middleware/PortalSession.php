<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PortalSession
{
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->is('admin', 'admin/*', 'api/v1/admin/*');
        config(['session.portal' => $admin ? 'admin' : 'parent', 'session.cookie' => $admin ? 'growth_admin_session' : 'growth_parent_session', 'session.path' => '/', 'session.http_only' => true, 'session.same_site' => 'lax']);
        app('session')->driver()->setName(config('session.cookie'));
        Auth::shouldUse($admin ? 'admin' : 'parent');

        return $next($request);
    }
}
