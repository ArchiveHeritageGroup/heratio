<?php

namespace Tests\CriticalPath;

use Tests\TestCase as BaseTestCase;

/**
 * Base test case for critical path tests.
 * 
 * @group critical
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Assert that this is a critical path test.
     */
    protected function assertCritical(): void
    {
        $this->assertTrue(true, 'This is a critical path test');
    }
}
