<?php

namespace App\Services\Admin;

use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\ParentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParentAuthorization
{
    public const SESSION_KEY = 'parent_authorization';

    public function issue(Administrator $actor, int|string $parentId, string $ip): string
    {
        return DB::transaction(function () use ($actor, $parentId, $ip) {
            $admin = Administrator::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($admin->auth_version === $actor->auth_version, 401);
            $parent = ParentAccount::whereKey($parentId)->lockForUpdate()->firstOrFail();
            $token = Str::random(64);
            $this->audit($admin->id, $parent, 'parent_authorization_issued', $ip);
            Cache::put($this->key($token), [
                'admin_id' => $admin->id,
                'admin_auth_version' => $admin->auth_version,
                'parent_id' => $parent->id,
                'parent_auth_version' => $parent->auth_version,
                'expires_at' => now()->addMinute()->timestamp,
            ], 60);

            // APP_URL is the configured parent origin, never a client-provided redirect.
            return rtrim(config('app.url'), '/').'/app/authorized-login#token='.$token;
        });
    }

    public function consume(Request $request, string $token): void
    {
        $key = $this->key($token);
        // pull alone is not atomic on every cache driver. Serialize competing exchanges.
        $grant = Cache::lock($key.':lock', 10)->block(3, fn () => Cache::pull($key));
        abort_unless(is_array($grant) && $grant['expires_at'] > now()->timestamp, 403);

        DB::transaction(function () use ($grant, $request) {
            $admin = Administrator::whereKey($grant['admin_id'])->lockForUpdate()->first();
            $parent = ParentAccount::whereKey($grant['parent_id'])->lockForUpdate()->first();
            abort_unless($admin && $parent
                && $admin->auth_version === $grant['admin_auth_version']
                && $parent->auth_version === $grant['parent_auth_version'], 403);
            $this->audit($admin->id, $parent, 'parent_authorization_started', $request->ip());
            // Replace only this browser's parent session; the admin cookie is independent.
            $request->session()->invalidate();
            Auth::guard('parent')->login($parent);
            $request->session()->regenerateToken();
            $request->session()->put('parent_auth_version', $parent->auth_version);
            $request->session()->put(self::SESSION_KEY, [
                'admin_id' => $admin->id,
                'admin_auth_version' => $admin->auth_version,
                'admin_username' => $admin->username,
                'parent_id' => $parent->id,
                'expires_at' => now()->addMinutes(30)->timestamp,
            ]);
        });
    }

    public function valid(Request $request, ParentAccount $parent): bool
    {
        $grant = $request->session()->get(self::SESSION_KEY);

        return is_array($grant)
            && ($grant['parent_id'] ?? null) === $parent->id
            && ($grant['expires_at'] ?? 0) > now()->timestamp
            && Administrator::whereKey($grant['admin_id'] ?? null)
                ->where('auth_version', $grant['admin_auth_version'] ?? -1)->exists();
    }

    public function info(Request $request): ?array
    {
        $grant = $request->session()->get(self::SESSION_KEY);

        return $grant ? [
            'admin_username' => $grant['admin_username'],
            'expires_at' => $grant['expires_at'],
            'read_only' => true,
        ] : null;
    }

    public function finish(Request $request): void
    {
        $grant = $request->session()->get(self::SESSION_KEY);
        if ($grant) {
            $this->audit($grant['admin_id'], $request->user(), 'parent_authorization_ended', $request->ip());
        }
    }

    private function key(string $token): string
    {
        return 'parent-authorization:'.hash('sha256', $token);
    }

    private function audit(int $adminId, ParentAccount $parent, string $action, string $ip): void
    {
        AuditLog::create([
            'admin_id' => $adminId, 'target_id' => $parent->id, 'action' => $action,
            'ip_address' => $ip, 'details' => ['phone' => $parent->phone, 'read_only' => true],
            'created_at' => now(),
        ]);
    }
}
