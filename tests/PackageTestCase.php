<?php

namespace Tests;

use Tests\TestCase as BaseTestCase;

abstract class PackageTestCase extends BaseTestCase
{
    /**
     * Get the package name for this test suite.
     */
    abstract protected function getPackageName(): string;

    /**
     * Get the package path.
     */
    protected function getPackagePath(): string
    {
        return __DIR__ . '/../packages/' . $this->getPackageName();
    }
}
