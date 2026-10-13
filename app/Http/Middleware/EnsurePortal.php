<?php

namespace App\Http\Middleware;

use App\Services\Admin\ParentAuthorization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePortal
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = Auth::guard($role)->user();
        if (! $user) {
            if (! $request->is('api/*')) {
                return redirect('/'.($role === 'parent' ? 'app' : 'admin').'/login');
            } abort(401, '请先登录');
        }
        Auth::shouldUse($role);
        if ($role === 'admin' && (int) $request->session()->get('admin_auth_version', 0) !== $user->auth_version) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            if (! $request->is('api/*')) {
                return redirect('/admin/login');
            }
            abort(401, '密码已变更，请重新登录');
        }
        if ($role === 'parent') {
            if ((int) $request->session()->get('parent_auth_version', 0) !== $user->auth_version) {
                Auth::guard('parent')->logout();
                $request->session()->invalidate();
                abort(401, '密码已变更，请重新登录');
            }
            $authorized = $request->session()->has(ParentAuthorization::SESSION_KEY);
            if ($authorized) {
                if (! app(ParentAuthorization::class)->valid($request, $user)) {
                    Auth::guard('parent')->logout();
                    $request->session()->invalidate();
                    if (! $request->is('api/*')) {
                        return redirect('/app/login');
                    }
                    abort(401);
                }
                if ((! $request->isMethodSafe() && ! $request->is('api/v1/app/auth/logout'))
                    || $request->is('app/change-password', 'app/students/new', 'app/students/*/exams/new', 'app/exams/*/edit')) {
                    if (! $request->is('api/*')) {
                        return redirect('/app/');
                    }

                    return response()->json(['error' => ['code' => 'AUTHORIZED_READ_ONLY', 'message' => '当前为管理员授权查看，不能修改资料或密码']], 403);
                }
            }
            if (! $authorized && $user->must_change_password && ! $request->is('api/v1/app/auth/me', 'api/v1/app/auth/logout', 'api/v1/app/auth/change-password', 'app/change-password')) {
                if (! $request->is('api/*')) {
                    return redirect('/app/change-password');
                }

                return response()->json(['error' => ['code' => 'PASSWORD_CHANGE_REQUIRED', 'message' => '请先修改管理员提供的临时密码']], 403);
            }
        }

        return $next($request);
    }
}
