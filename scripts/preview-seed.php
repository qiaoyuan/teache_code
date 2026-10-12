<?php

use App\Models\Administrator;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_starts_with(config('database.connections.mysql.database'), 'shengxue360_test_')) {
    throw new RuntimeException('Preview fixtures require a separate test database');
}
Administrator::firstOrCreate(['username' => 'preview_admin'], ['password' => 'PreviewAdmin123']);
echo "Isolated preview administrator ready.\n";
