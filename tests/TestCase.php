<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $database = config('database.connections.'.config('database.default').'.database');
        if ($database !== ':memory:' && ! str_starts_with((string) $database, 'shengxue360_test_')) {
            throw new \RuntimeException('Refusing to run destructive tests outside an isolated test database');
        }

        return $app;
    }
}
