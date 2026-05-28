<?php

namespace AhgLibrary\Tests;

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