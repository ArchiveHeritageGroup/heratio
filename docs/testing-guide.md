# Heratio Testing Guide

## Overview

This document describes the comprehensive testing infrastructure for Heratio, including PHPUnit tests for API/CRUD operations and Playwright E2E tests for browser-based testing.

## Testing Stack

- **PHPUnit 11** - Unit and Feature testing
- **Playwright** - End-to-end browser testing
- **SQLite** - In-memory test database
- **FakerPHP** - Test data generation

## Test Structure

```
heratio/
├── tests/
│   ├── Unit/                    # Unit tests
│   │   └── ExampleTest.php
│   ├── Feature/                 # Feature/integration tests
│   │   ├── ExampleTest.php
│   │   ├── ActorCrudTest.php
│   │   └── InformationObjectCrudTest.php
│   └── e2e/                     # Playwright E2E tests
│       ├── 01-authentication.spec.js
│       ├── 02-actors.spec.js
│       └── playwright.config.js
└── database/
    └── factories/               # Test factories
        ├── ActorFactory.php
        ├── InformationObjectFactory.php
        ├── EventFactory.php
        ├── TermFactory.php
        └── AccessionFactory.php
```

## Running Tests

### PHPUnit Tests

```bash
# Run all tests
cd /usr/share/nginx/heratio
php artisan test

# Run specific test class
php artisan test tests/Feature/ActorCrudTest.php

# Run with coverage (requires Xdebug)
php artisan test --coverage

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature tests
php artisan test --testsuite=Feature

# Run specific test method
php artisan test --filter=test_can_create_person_actor
```

### Playwright E2E Tests

```bash
# Install Playwright browsers
cd /usr/share/nginx/heratio
npx playwright install

# Run E2E tests
npx playwright test

# Run with UI (visual mode)
npx playwright test --ui

# Run specific spec
npx playwright test tests/e2e/01-authentication.spec.js

# Run in headed mode (see browser)
npx playwright test --headed

# Run on specific browser
npx playwright test --project=chromium
```

## Test Coverage

### PHPUnit Tests

#### ActorCrudTest (30+ tests)
- **CREATE**: Person, family, corporate body actors
- **READ**: Find by ID, list all, filter by type, search
- **UPDATE**: Name, bio, type changes
- **DELETE**: Single delete, cascade delete
- **RELATIONS**: Events, information objects
- **IDENTIFIERS**: VIAF, ISNI
- **VALIDATION**: Required fields, type validation
- **PAGINATION**: Browse, sort
- **STATISTICS**: Count by type
- **BULK**: Bulk create/update

#### InformationObjectCrudTest (30+ tests)
- **CREATE**: Collection, series, file, item
- **HIERARCHY**: Parent-child relationships
- **READ**: Find, browse, search, filter
- **UPDATE**: Title, scope, parent, level
- **DELETE**: Leaf delete, orphan children
- **RELATIONS**: Creators, subjects
- **RIGHTS**: Copyright, public domain
- **ACCESS**: Access conditions
- **VALIDATION**: Required fields, levels
- **PAGINATION**: Browse, sort
- **STATISTICS**: Count by level, repository
- **FULL HIERARCHY**: Multi-level structures

### Playwright E2E Tests

#### Authentication (7 tests)
- Login page loads
- Valid credentials login
- Invalid credentials error
- Logout
- Redirect unauthenticated
- Remember me
- Password visibility toggle

#### Actors Management (10 tests)
- Browse page loads
- Create person actor
- Create corporate body
- Search actors
- View actor details
- Edit actor
- Delete actor
- Type filter
- Pagination
- Relations display

## Test Factories

### Available Factories

| Factory | Model | States |
|---------|-------|--------|
| ActorFactory | QubitActor | person(), family(), corporateBody() |
| InformationObjectFactory | QubitInformationObject | collection(), series(), file(), item(), withParent() |
| EventFactory | QubitEvent | creation(), accumulation(), withObject(), withActor() |
| TermFactory | QubitTerm | subject(), place(), genre() |
| AccessionFactory | QubitAccession | gift(), purchase(), donation() |
| UserFactory | QubitUser | (built-in) |

### Using Factories

```php
// Create with defaults
$actor = ActorFactory::new()->create();

// Create with custom data
$actor = ActorFactory::new()->create([
    'authorized_form_of_name' => 'Custom Name'
]);

// Create using state
$person = ActorFactory::new()->person()->create();

// Create multiple
$actors = ActorFactory::new()->count(10)->create();

// Make instance without saving
$actor = ActorFactory::new()->make();
```

## Creating New Tests

### PHPUnit Feature Test

```php
<?php

namespace Tests\Feature;

use AhgCore\Models\QubitActor;
use Database\Factories\ActorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewEntityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_entity(): void
    {
        $data = [
            'field1' => 'value1',
            'field2' => 'value2',
        ];

        $entity = QubitActor::create($data);

        $this->assertDatabaseHas('actor', [
            'id' => $entity->id,
            'field1' => 'value1',
        ]);
    }
}
```

### Playwright E2E Test

```javascript
import { test, expect } from '@playwright/test';

test.describe('New Feature', () => {
  
  test.beforeEach(async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
  });

  test('can perform action', async ({ page }) => {
    await page.goto('/path/to/page');
    
    // Test steps
    await page.click('button');
    
    // Assertions
    await expect(page.locator('.result')).toBeVisible();
  });
});
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: test
          MYSQL_DATABASE: heratio_test
    steps:
      - uses: actions/checkout@v4
      - uses: php/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: php artisan migrate --seed
      - run: php artisan test --coverage

  playwright:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
      - run: npm ci
      - run: npx playwright install --with-deps
      - run: npx playwright test
```

## Test Database

Tests use SQLite in-memory database by default. The `RefreshDatabase` trait automatically:
1. Creates a fresh database for each test
2. Runs migrations
3. Rolls back changes after each test

### Database Configuration

```php
// phpunit.xml
<php>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
</php>
```

## Mocking External Services

For tests that require external services (Fuseki, Wikidata, etc.), use Mockery:

```php
use Mockery;

public function test_with_mocked_fuseki(): void
{
    $mock = Mockery::mock(FusekiService::class);
    $mock->shouldReceive('query')
        ->once()
        ->andReturn(['results' => []]);
    
    $this->app->instance(FusekiService::class, $mock);
    
    // Test code that uses Fuseki
}
```

## Troubleshooting

### Common Issues

1. **Tests fail with "table not found"**
   - Ensure migrations are run: `php artisan migrate`
   - Check phpunit.xml DB configuration

2. **Factory not found**
   - Ensure namespace matches: `Database\Factories\`
   - Model must have `$model` property

3. **Playwright browsers not installed**
   - Run: `npx playwright install --with-deps`

4. **E2E tests timeout**
   - Increase timeout in playwright.config.js
   - Check if dev server is running

## Coverage Reports

Generate HTML coverage reports:

```bash
composer require --dev phpunit/php-code-coverage
php artisan test --coverage-html coverage/
```

## Best Practices

1. **Isolate tests** - Each test should be independent
2. **Use factories** - Generate test data consistently
3. **Test edge cases** - Empty data, max values, special characters
4. **Keep tests fast** - Avoid unnecessary waits in E2E
5. **Use meaningful names** - Test method names should describe what they test
6. **Assert clearly** - Make failure messages helpful
7. **Clean up** - Use transactions or database refreshing

## Future Test Coverage

Planned additions:
- [ ] AccessionCrudTest
- [ ] TermCrudTest
- [ ] DigitalObjectCrudTest
- [ ] EventCrudTest
- [ ] Rights management tests
- [ ] Workflow tests
- [ ] Search functionality tests
- [ ] API endpoint tests
- [ ] Multi-tenant isolation tests
- [ ] Performance tests

---

For questions or issues, please contact the development team.
