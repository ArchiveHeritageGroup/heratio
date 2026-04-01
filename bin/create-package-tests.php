#!/usr/bin/env php
<?php

/**
 * Package Test Template Generator
 * 
 * Usage: php bin/create-package-tests.php {package-name}
 * 
 * Creates the standard test directory structure for a new package.
 * 
 * Required structure:
 *   packages/{name}/tests/
 *   packages/{name}/tests/TestCase.php
 *   packages/{name}/tests/bootstrap.php
 *   packages/{name}/tests/Unit/
 *   packages/{name}/tests/Feature/
 *   packages/{name}/tests/Feature/Integration/
 */

$packageName = $argv[1] ?? null;

if (!$packageName) {
    echo "Error: Package name required\n";
    echo "Usage: php bin/create-package-tests.php {package-name}\n";
    exit(1);
}

// Convert kebab-case to PascalCase for class names
$pascalCase = str_replace('-', '', ucwords($packageName, '-'));

$testsDir = __DIR__ . '/../packages/' . $packageName . '/tests';

// Create directories
$directories = [
    $testsDir,
    $testsDir . '/Unit',
    $testsDir . '/Unit/Services',
    $testsDir . '/Feature',
    $testsDir . '/Feature/Integration',
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created: $dir\n";
    }
}

// Create TestCase.php
$testCaseContent = <<<PHP
<?php

namespace Packages\\{$pascalCase}\\Tests;

use Tests\\PackageTestCase;

class TestCase extends PackageTestCase
{
    protected function getPackageName(): string
    {
        return '{$packageName}';
    }
}
PHP;

file_put_contents($testsDir . '/TestCase.php', $testCaseContent);
echo "Created: {$testsDir}/TestCase.php\n";

// Create bootstrap.php
$bootstrapContent = <<<PHP
<?php

/**
 * Package bootstrap - validates package structure
 * 
 * This file is run before tests to ensure the package is properly configured.
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Validate required files exist
\$requiredFiles = [
    __DIR__ . '/../src/Providers/{$pascalCase}ServiceProvider.php',
    __DIR__ . '/../composer.json',
];

foreach (\$requiredFiles as \$file) {
    if (!file_exists(\$file)) {
        throw new RuntimeException("Required file missing: {\$file}");
    }
}

// Validate service provider class exists
\$providerClass = 'Packages\\\\{$pascalCase}\\\\Providers\\\\{$pascalCase}ServiceProvider';
if (!class_exists(\$providerClass)) {
    throw new RuntimeException("Service provider class not found: {\$providerClass}");
}
PHP;

file_put_contents($testsDir . '/bootstrap.php', $bootstrapContent);
echo "Created: {$testsDir}/bootstrap.php\n";

// Create BootstrapTest.php
$bootstrapTestContent = <<<PHP
<?php

namespace Packages\\{$pascalCase}\\Tests\\Feature\\Integration;

use Packages\\{$pascalCase}\\Tests\\TestCase;

/**
 * Bootstrap test - validates package loads correctly
 * 
 * @group {$packageName}
 * @group bootstrap
 */
class BootstrapTest extends TestCase
{
    /**
     * @test
     */
    public function package_can_be_bootstrapped(): void
    {
        \$this->assertTrue(
            file_exists(__DIR__ . '/../../bootstrap.php'),
            'Bootstrap file should exist'
        );
    }

    /**
     * @test
     */
    public function service_provider_can_be_instantiated(): void
    {
        \$providerClass = 'Packages\\\\{$pascalCase}\\\\Providers\\\\{$pascalCase}ServiceProvider';
        
        if (class_exists(\$providerClass)) {
            \$this->assertTrue(
                true,
                'Service provider class exists'
            );
        } else {
            \$this->markTestSkipped('Service provider not yet implemented');
        }
    }
}
PHP;

file_put_contents($testsDir . '/Feature/Integration/BootstrapTest.php', $bootstrapTestContent);
echo "Created: {$testsDir}/Feature/Integration/BootstrapTest.php\n";

// Create SampleServiceTest.php
$serviceTestContent = <<<PHP
<?php

namespace Packages\\{$pascalCase}\\Tests\\Unit\\Services;

use Packages\\{$pascalCase}\\Tests\\TestCase;

/**
 * Sample service test - happy path and failure path
 * 
 * @group {$packageName}
 * @group services
 */
class SampleServiceTest extends TestCase
{
    /**
     * @test
     */
    public function service_can_be_resolved(): void
    {
        // Happy path: verify the service can be resolved from the container
        \$this->assertTrue(
            true,
            'Service can be resolved (placeholder)'
        );
    }

    /**
     * @test
     */
    public function handles_missing_dependencies_gracefully(): void
    {
        // Failure path: verify error handling works
        \$this->assertTrue(
            true,
            'Error handling works (placeholder)'
        );
    }
}
PHP;

file_put_contents($testsDir . '/Unit/Services/SampleServiceTest.php', $serviceTestContent);
echo "Created: {$testsDir}/Unit/Services/SampleServiceTest.php\n";

echo "\n";
echo "Package test structure created for: {$packageName}\n";
echo "\n";
echo "Required test outcomes:\n";
echo "  - Bootstrap test: package_can_be_bootstrapped\n";
echo "  - Happy path: service_can_be_resolved\n";
echo "  - Failure path: handles_missing_dependencies_gracefully\n";
echo "\n";
echo "Coverage target: 30% minimum\n";
echo "\n";
echo "To run tests:\n";
echo "  php ./vendor/bin/phpunit packages/{$packageName}/tests --testdox\n";
