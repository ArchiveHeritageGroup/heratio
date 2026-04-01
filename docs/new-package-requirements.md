# New Package Requirements

**Version:** 1.0  
**Effective Date:** 2026-04-01  
**Owner:** Development Team

---

## 1. Overview

Every new package added to Heratio must meet minimum requirements for testing and structure. This ensures consistent quality across the monorepo.

---

## 2. Required Directory Structure

Every new package must include:

```
packages/{package-name}/
├── composer.json
├── src/
│   └── Providers/
│       └── {PackageName}ServiceProvider.php
└── tests/                          # Required
    ├── TestCase.php                 # Required
    ├── bootstrap.php                 # Required
    ├── Unit/                       # Required
    │   └── Services/               # At least one test
    │       └── {Service}Test.php
    └── Feature/                    # Required
        └── Integration/
            └── BootstrapTest.php    # Required - validates package loads
```

---

## 3. Required Test Files

### 3.1 TestCase.php

Each package must have its own `TestCase.php` extending `Tests\PackageTestCase`:

```php
<?php

namespace Packages\{PackageName}\Tests;

use Tests\PackageTestCase;

class TestCase extends PackageTestCase
{
    protected function getPackageName(): string
    {
        return '{package-name}';
    }
}
```

### 3.2 bootstrap.php

Each package must have a `bootstrap.php` that validates the package loads correctly:

```php
<?php

/**
 * Package bootstrap - validates package structure
 */

require_once __DIR__ . '/../../../vendor/autoload.php';

// Validate required files exist
$requiredFiles = [
    __DIR__ . '/../src/Providers/{PackageName}ServiceProvider.php',
    __DIR__ . '/../composer.json',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        throw new RuntimeException("Required file missing: {$file}");
    }
}

// Validate service provider class exists
$providerClass = 'Packages\\{PackageName}\\Providers\\{PackageName}ServiceProvider';
if (!class_exists($providerClass)) {
    throw new RuntimeException("Service provider class not found: {$providerClass}");
}
```

---

## 4. Required Test Outcomes

Every new package must have tests that verify:

### 4.1 Bootstrap Test (Required)
```
Test: package_can_be_bootstrapped
Purpose: Verify the package loads without errors
Criteria: Test must pass
```

### 4.2 Happy-Path Test (Required)
```
Test: service_provider_can_be_resolved
Purpose: Verify the main service can be instantiated
Criteria: Test must pass
```

### 4.3 Failure-Path Test (Required)
```
Test: handles_missing_dependencies_gracefully
Purpose: Verify error handling works
Criteria: Test must pass
```

---

## 5. Coverage Requirements

| Metric | Requirement | Enforcement |
|--------|-------------|-------------|
| Line coverage | 30% minimum | CI fails below this |
| Changed files | 60% minimum | CI fails below this |

---

## 6. Validation Process

### 6.1 Local Validation

Before submitting a PR, validate your package:

```bash
# Run package tests
php ./vendor/bin/phpunit packages/{package-name}/tests --testdox

# Check coverage
php ./vendor/bin/phpunit packages/{package-name}/tests --coverage-text
```

### 6.2 CI Validation

CI automatically validates:
1. Test suite runs successfully (exit code 0)
2. Bootstrap test passes
3. Happy-path test passes
4. Failure-path test passes
5. Coverage meets 30% threshold

### 6.3 Failure Handling

| Failure | CI Status | Action Required |
|---------|-----------|-----------------|
| Test suite fails | ❌ Blocked | Fix tests |
| Bootstrap test fails | ❌ Blocked | Check package structure |
| Coverage < 30% | ❌ Blocked | Add more tests |

---

## 7. Checklist

Before submitting a PR with a new package:

- [ ] `packages/{name}/composer.json` exists
- [ ] `packages/{name}/src/Providers/{PackageName}ServiceProvider.php` exists
- [ ] `packages/{name}/tests/TestCase.php` exists
- [ ] `packages/{name}/tests/bootstrap.php` exists
- [ ] `packages/{name}/tests/Unit/` directory exists with tests
- [ ] `packages/{name}/tests/Feature/` directory exists with tests
- [ ] Bootstrap test passes
- [ ] Happy-path test passes
- [ ] Failure-path test passes
- [ ] Test suite runs successfully
- [ ] Coverage meets 30% minimum

---

## 8. Small Package Exceptions

For packages with minimal functionality (< 100 lines of PHP code):

- Bootstrap test only required
- Happy-path test only required
- Coverage target: 20% minimum

These exceptions must be approved by lead developer.

---

## 9. Template Package

See `packages/ahg-ric` for a well-structured example package with comprehensive tests.

---

## 10. Related Documents

- [Test Coverage Policy](./test-coverage-policy.md)
- [Package Classification](./package-classification.md)
- [Critical Path Tests](./critical-path-tests.md)
