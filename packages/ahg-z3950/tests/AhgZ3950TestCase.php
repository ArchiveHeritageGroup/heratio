<?php

namespace AhgZ3950\Tests;

use PHPUnit\Framework\TestCase;

abstract class AhgZ3950TestCase extends TestCase
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
}
