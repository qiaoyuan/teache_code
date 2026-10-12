<?php

namespace App\Services\Admin;

use App\Models\Administrator;
use App\Models\AuditLog;
use App\Models\ParentAccount;
use Illuminate\Support\Facades\DB;

class PasswordReset
{
    public function reset(Administrator $admin, int|string $id, string $password, string $ip): void
    {
        DB::transaction(function () use ($admin, $id, $password, $ip) {
            $p = ParentAccount::whereKey($id)->lockForUpdate()->firstOrFail();
            $p->update(['password' => $password, 'must_change_password' => true, 'auth_version' => $p->auth_version + 1]);
            DB::table('sessions')->where('portal', 'parent')->where('user_id', $p->id)->delete();
            AuditLog::create(['admin_id' => $admin->id, 'action' => 'parent_password_reset', 'target_id' => $p->id, 'ip_address' => $ip, 'details' => ['phone' => $p->phone], 'created_at' => now()]);
        });
    }
}
