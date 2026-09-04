<?php

namespace AhgLibrary\Tests;

use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Base test case for ahg-library package tests.
 * Provides package path and shared fixtures.
 */
abstract class AhgLibraryTestCase extends TestCase
{
    protected string $fixturesPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturesPath = __DIR__ . '/__fixtures__';

        // Four tests in this package boot a bare container of their own and
        // point the facades at it. A facade instance resolved earlier by a
        // test that booted a real Laravel application is still cached on the
        // Facade class and holds a reference to THAT application, and
        // setFacadeApplication() does not clear the cache - so a stale
        // LogManager answered \Log::warning() and resolved 'config' from an
        // application no longer bound. That is why SushiServerTest passed on
        // its own and failed only in the All suite, where something boots
        // Laravel first. Ordering between test classes is not ours to rely on,
        // so clear at both ends.
        Facade::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    protected function fixturesPath(string $filename): string
    {
        return $this->fixturesPath . '/' . ltrim($filename, '/');
    }

    protected function loadFixture(string $filename): string
    {
        $path = $this->fixturesPath($filename);
        $this->assertFileExists($path, "Fixture not found: {$filename}");
        return file_get_contents($path);
    }
}