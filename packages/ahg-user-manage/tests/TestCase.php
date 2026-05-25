<?php
namespace AhgUserManage\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \AhgUserManage\Providers\AhgUserManageServiceProvider::class,
        ];
    }
}
