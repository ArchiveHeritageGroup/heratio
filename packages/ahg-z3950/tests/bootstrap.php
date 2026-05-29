<?php

/**
 * bootstrap.php — PHPUnit bootstrap for ahg-z3950 tests.
 *
 * Registers PSR-4 namespaces for the package's test suite so that
 * AhgZ3950\Tests\* classes are visible to PHPUnit.
 *
 * The monorepo root autoload handles AhgZ3950\Src\*.
 * We manually register AhgZ3950\Tests\* here.
 */

$loader = require '/usr/share/nginx/heratio/vendor/autoload.php';

/**
 * Register AhgZ3950\Tests\* PSR-4 namespace manually.
 * Files: packages/ahg-z3950/tests/*.php and tests/Unit/*.php
 */
$loader->addPsr4(
    'AhgZ3950\\Tests\\',
    '/usr/share/nginx/heratio/packages/ahg-z3950/tests/'
);
