# Heratio Testing Suite

Comprehensive Symfony-to-Laravel parity and regression testing system for Heratio.

## Overview

This testing suite provides automated verification that Heratio (Laravel) has equivalent or replacement functionality compared to PSIS (Symfony/AtoM).

### Truth Hierarchy

1. **User-accessible functionality** - What users can reach and do in the browser
2. **UI Components** - Forms, buttons, links, and actions that exist
3. **Workflow Success** - End-to-end workflows that succeed
4. **Code Inventory** - Support and debugging at code level

## Test Structure

```
tests/
├── fixtures/                    # Test data and configurations
│   ├── seed-urls.json         # URLs for discovery crawl
│   ├── role-credentials.json  # Test user credentials
│   └── parity-map.json        # URL/function mappings
├── e2e/                       # Playwright E2E tests
│   ├── helpers/              # Helper modules
│   │   ├── login-helpers.ts  # Login/logout functions
│   │   ├── crawl-helpers.ts  # URL discovery functions
│   │   └── api-helpers.ts    # API verification functions
│   ├── 00-discovery/         # Layer A & B: Discovery crawlers
│   │   ├── psis-crawler.spec.ts
│   │   ├── heratio-crawler.spec.ts
│   │   └── parity-scanner.spec.ts
│   └── 03-workflows/         # Layer C: Workflow tests
│       ├── access-navigation.spec.ts
│       ├── browse-search.spec.ts
│       └── crud-records.spec.ts
├── Feature/                   # Laravel backend tests
│   ├── Api/                   # API tests
│   │   ├── ActorApiTest.php
│   │   ├── InformationObjectApiTest.php
│   │   └── TermApiTest.php
│   ├── ActorCrudTest.php
│   ├── AccessionCrudTest.php
│   ├── InformationObjectCrudTest.php
│   └── TermCrudTest.php
└── reports/                   # Generated reports (gitignored)
```

## Quick Start

### 1. Install Dependencies

```bash
npm install
npx playwright install --with-deps
```

### 2. Configure Environment

```bash
cp .env.testing .env
# Edit .env with your test credentials
```

### 3. Run Tests

```bash
# All tests
npm run test

# E2E only
npm run test:e2e

# Laravel only
npm run test:laravel

# Discovery (URL crawlers)
npm run test:discovery

# Workflows
npm run test:workflows

# With UI
npm run test:e2e:ui
```

## Test Suites

### Layer A: Discovery Crawlers

Crawls both PSIS and Heratio to discover:
- Pages and URLs
- Links
- Forms and form fields
- Buttons
- Console errors

**Run:**
```bash
npx playwright test tests/e2e/00-discovery/psis-crawler.spec.ts
npx playwright test tests/e2e/00-discovery/heratio-crawler.spec.ts
npx playwright test tests/e2e/00-discovery/parity-scanner.spec.ts
```

### Layer B: Parity Scanner

Compares discovered pages between systems and generates:
- `parity-report.json` - Full comparison data
- `parity-report.csv` - Spreadsheet-friendly format
- `missing-pages.json` - Pages in PSIS but not Heratio
- `missing-forms.json` - Forms in PSIS but not Heratio

### Layer C: Workflow Tests

End-to-end user journeys:

**Access & Navigation:**
- Login/logout
- Dashboard loading
- Navigation menus
- Breadcrumbs

**Browse & Search:**
- Browse records, actors, repositories, terms
- Search functionality
- Filters and sorting
- Pagination

**CRUD Operations:**
- Create records
- Read/view details
- Update records
- Delete records
- Hierarchical relationships

### Layer D: Laravel Backend Tests

Direct Laravel API and model tests:

```bash
php artisan test
php artisan test tests/Feature/Api/
```

## Role-Based Testing

Tests run under multiple identities:

| Role | Access | Restrictions |
|------|--------|--------------|
| guest | Public pages | Admin pages, create/edit/delete |
| authenticated | Basic logged-in access | Admin pages |
| editor | Create and edit | User management, system settings |
| admin | Full access | None |
| researcher | Research features | Admin, delete |

## Generated Reports

Reports are saved to `tests/e2e/reports/`:

- `psis-inventory/` - PSIS crawl data
- `heratio-inventory/` - Heratio crawl data
- `parity-report.json` - Full parity analysis
- `parity-report.csv` - CSV format
- `missing-pages.json` - Missing functionality
- `executive-summary.txt` - Human-readable summary

## CI/CD Integration

Tests run automatically via GitHub Actions:

- **On push to main/develop**: Full test suite
- **On pull requests**: Full test suite
- **Nightly at 2 AM UTC**: Full test suite
- **Manual trigger**: Select specific suites

See `.github/workflows/e2e-tests.yml`

## Adding New Tests

### Playwright E2E Tests

1. Create test file in `tests/e2e/03-workflows/`
2. Use the existing patterns:

```typescript
import { test, expect } from '@playwright/test';

test.describe('Feature Name', () => {
  test.beforeEach(async ({ page }) => {
    // Login setup
    await page.goto('/login');
    await page.fill('input[name="email"]', 'admin@test.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
  });

  test('does something', async ({ page }) => {
    await page.goto('/some-page');
    // assertions...
  });
});
```

### Laravel API Tests

1. Create test file in `tests/Feature/Api/`
2. Use the existing patterns:

```php
public function test_can_do_something(): void
{
    $response = $this->getJson('/api/endpoint');
    
    $response->assertStatus(200);
    $response->assertJsonStructure([...]);
    $response->assertJsonFragment([...]);
}
```

## Troubleshooting

### Playwright errors

```bash
# Reinstall browsers
npx playwright install

# Install system dependencies
npx playwright install-deps
```

### Laravel tests failing

```bash
# Fresh database
php artisan migrate:fresh --seed

# Clear cache
php artisan config:clear
php artisan cache:clear
```

### Connection errors

- Verify `HERATIO_URL` and `PSID_URL` in environment
- Check network access to test environments
- Verify SSL certificates if using HTTPS

## Maintenance

### Updating Seed URLs

Edit `tests/fixtures/seed-urls.json` to add new URLs for crawling.

### Updating Parity Map

Edit `tests/fixtures/parity-map.json` to update URL mappings.

### Adding Test Roles

Edit `tests/fixtures/role-credentials.json` and add users in the application.

## License

This testing suite is part of the Heratio project and follows the same AGPL-3.0 license.
