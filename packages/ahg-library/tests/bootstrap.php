<?php
/**
 * bootstrap.php — PHPUnit bootstrap for ahg-library tests.
 *
 * Registers PSR-4 namespaces for the package's test suite so that
 * AhgLibrary\Tests\* classes are visible to PHPUnit when run from
 * the monorepo root vendor/autoload.php.
 *
 * The monorepo root autoload handles AhgLibrary\Src\*.
 * We manually register AhgLibrary\Tests\* here.
 */

$loader = require '/usr/share/nginx/heratio/vendor/autoload.php';

/**
 * Register AhgLibrary\Tests\* PSR-4 namespace manually.
 * Files: packages/ahg-library/tests/*.php and tests/Unit/*.php
 */
$loader->addPsr4(
    'AhgLibrary\\Tests\\',
    '/usr/share/nginx/heratio/packages/ahg-library/tests/'
);