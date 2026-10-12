<?php

use App\Models\Administrator;
use App\Models\ParentAccount;

return [
    'defaults' => ['guard' => 'parent', 'passwords' => 'parents'],
    'guards' => ['parent' => ['driver' => 'session', 'provider' => 'parents'], 'admin' => ['driver' => 'session', 'provider' => 'administrators']],
    'providers' => ['parents' => ['driver' => 'eloquent', 'model' => ParentAccount::class], 'administrators' => ['driver' => 'eloquent', 'model' => Administrator::class]],
    'password_timeout' => 10800,
];
