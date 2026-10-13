<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Administrator;
use App\Models\AuditLog;
use App\Services\Shared\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdministratorController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['page' => 'sometimes|integer|min:1']);

        return Administrator::select('id', 'username', 'created_at', 'updated_at')
            ->orderBy('id')->paginate(20, ['*'], 'page', $data['page'] ?? 1);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'],
            'password' => [...Text::passwordRules(), 'confirmed'],
            'current_password' => 'required|string|max:100',
        ]);
        $admin = DB::transaction(function () use ($request, $data) {
            $actor = Administrator::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $this->verifyOperator($request, $actor, $data['current_password']);
            $username = strtolower($data['username']);
            if (Administrator::whereRaw('LOWER(username) = ?', [$username])->exists()) {
                throw ValidationException::withMessages(['username' => '该管理员账号已存在']);
            }
            $admin = Administrator::create(['username' => $username, 'password' => $data['password']]);
            $this->audit($request, 'administrator_created', $admin);

            return $admin;
        }, 3);

        return response()->json(['data' => $admin->only('id', 'username', 'created_at', 'updated_at')], 201);
    }

    public function password(Request $request, string $id)
    {
        $data = $request->validate([
            'password' => [...Text::passwordRules(), 'confirmed'],
            'current_password' => 'required|string|max:100',
        ]);
        DB::transaction(function () use ($request, $id, $data) {
            // Lock in ID order so two administrators resetting each other do not reverse lock order.
            $admins = Administrator::whereIn('id', [$request->user()->id, $id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $admins->get($request->user()->id);
            $target = $admins->get((int) $id);
            abort_unless($actor && $target, 404);
            $this->verifyOperator($request, $actor, $data['current_password']);
            $target->update(['password' => $data['password'], 'auth_version' => $target->auth_version + 1]);
            DB::table('sessions')->where('portal', 'admin')->where('user_id', $target->id)->delete();
            $this->audit($request, 'administrator_password_changed', $target);
        }, 3);

        $self = (int) $request->user()->id === (int) $id;
        if ($self) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ['require_login' => $self, 'message' => $self ? '密码已修改，请重新登录' : '密码已修改，该管理员需要重新登录'];
    }

    private function verifyOperator(Request $request, Administrator $actor, string $password): void
    {
        abort_unless((int) $request->session()->get('admin_auth_version', 0) === $actor->auth_version, 401);
        if (! Hash::check($password, $actor->password)) {
            throw ValidationException::withMessages(['current_password' => '当前登录管理员的密码不正确']);
        }
    }

    private function audit(Request $request, string $action, Administrator $target): void
    {
        AuditLog::create([
            'admin_id' => $request->user()->id,
            'action' => $action,
            'target_id' => $target->id,
            'ip_address' => $request->ip(),
            'details' => ['username' => $target->username],
            'created_at' => now(),
        ]);
    }
}
