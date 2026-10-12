<?php

use App\Models\Administrator;
use App\Services\Shared\Text;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

Artisan::command('admin:init {--username=} {--password=}', function () {
    $username = $this->option('username') ?: config('growth.admin_username');
    $password = $this->option('password') ?: config('growth.admin_password');
    if (Administrator::where('username', $username)->exists()) {
        $this->info('管理员已存在，未修改密码');

        return 0;
    }
    if (! $password && $this->input->isInteractive()) {
        $password = $this->secret('设置管理员密码（至少 8 位，包含字母和数字）');
    }
    $v = Validator::make(['username' => $username, 'password' => $password], ['username' => 'required|string|max:64', 'password' => Text::passwordRules()]);
    if ($v->fails()) {
        $this->error('请输入合法用户名和密码（可配置 ADMIN_USERNAME / ADMIN_PASSWORD）');

        return 1;
    }
    Administrator::create(['username' => $username, 'password' => $password]);
    $this->info('管理员初始化成功');

    return 0;
})->purpose('创建管理员；重复运行不会覆盖已有密码');
