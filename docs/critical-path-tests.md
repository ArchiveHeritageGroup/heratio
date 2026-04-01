# Critical Path Tests

**Version:** 1.0  
**Effective Date:** 2026-04-01  
**Owner:** Development Team

---

## 1. Overview

Critical path tests are non-negotiable tests that must always pass. They cover the essential functionality that the platform must provide at all times.

**Location:** `tests/CriticalPath/`

---

## 2. Explicit Test Coverage Requirements

### 2.1 Authentication & Session

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Login | User can log in with valid credentials | `Auth/LoginTest.php` |
| Logout | User can log out successfully | `Auth/LogoutTest.php` |
| Session Persistence | Session persists across requests | `Auth/SessionPersistenceTest.php` |
| Failed Login | Invalid credentials are rejected | `Auth/FailedLoginTest.php` |
| Password Reset | Password reset flow works | `Auth/PasswordResetTest.php` |

### 2.2 Browse & Search

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Actor Browse | Actors can be browsed | `Browse/ActorBrowseTest.php` |
| IO Browse | Information objects can be browsed | `Browse/InformationObjectBrowseTest.php` |
| Term Browse | Terms can be browsed | `Browse/TermBrowseTest.php` |
| Global Search | Search returns relevant results | `Search/GlobalSearchTest.php` |
| Filter Search | Filters narrow results correctly | `Search/FilterSearchTest.php` |

### 2.3 Actor CRUD

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Create Actor | Actor can be created | `Crud/ActorCreateTest.php` |
| Read Actor | Actor details are displayed | `Crud/ActorReadTest.php` |
| Update Actor | Actor can be updated | `Crud/ActorUpdateTest.php` |
| Delete Actor | Actor can be deleted | `Crud/ActorDeleteTest.php` |
| Actor Relations | Actor relations work | `Crud/ActorRelationsTest.php` |

### 2.4 Information Object CRUD

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Create IO | Information object can be created | `Crud/IOCreateTest.php` |
| Read IO | IO details are displayed | `Crud/IOReadTest.php` |
| Update IO | IO can be updated | `Crud/IOUpdateTest.php` |
| Delete IO | IO can be deleted | `Crud/IODeleteTest.php` |
| IO Hierarchy | Parent-child relationships work | `Crud/IOHierarchyTest.php` |

### 2.5 Term & Taxonomy

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Term Browse | Terms can be browsed | `Taxonomy/TermBrowseTest.php` |
| Term Assignment | Terms can be assigned to records | `Taxonomy/TermAssignmentTest.php` |
| Taxonomy Integrity | Taxonomy tree is consistent | `Taxonomy/TaxonomyIntegrityTest.php` |

### 2.6 Accession Workflows

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Accession Create | Accession can be created | `Workflows/AccessionCreateTest.php` |
| Accession Status | Status transitions work | `Workflows/AccessionStatusTest.php` |
| Accession Link | Accession links to IO | `Workflows/AccessionLinkTest.php` |

### 2.7 Authorization Boundaries

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Guest Access | Guests see public content only | `Authorization/GuestAccessTest.php` |
| Authenticated Access | Authenticated users see permitted content | `Authorization/AuthenticatedAccessTest.php` |
| Editor Access | Editors can create/edit | `Authorization/EditorAccessTest.php` |
| Admin Access | Admins have full access | `Authorization/AdminAccessTest.php` |
| Unauthorized Block | Unauthorized actions are blocked | `Authorization/UnauthorizedBlockTest.php` |

### 2.8 Route & Package Boot

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Package Boot | All packages boot successfully | `Boot/PackageBootTest.php` |
| Routes Registered | Core routes are registered | `Boot/RoutesRegisteredTest.php` |
| Service Providers | Service providers load | `Boot/ServiceProviderTest.php` |
| Config Accessible | Package configs are accessible | `Boot/ConfigAccessibleTest.php` |

### 2.9 Admin Configuration

| Test Area | Description | Test File |
|-----------|-------------|-----------|
| Settings Page | Settings page loads | `Admin/SettingsPageTest.php` |
| User Management | User management works | `Admin/UserManagementTest.php` |
| System Config | System configuration saves | `Admin/SystemConfigTest.php` |

---

## 3. Test Execution Rules

### 3.1 Mandatory Execution
- Critical path tests **must** run on every PR
- They **cannot** be skipped with `@skip` annotations
- They **cannot** be marked as optional

### 3.2 Failure Handling
- Any critical path test failure **blocks** the PR
- Failures require immediate attention
- Bug fix PRs must include critical path test passes

### 3.3 Coverage
- Critical path functionality must maintain 80%+ coverage
- Regression in coverage triggers review

---

## 4. Test Template

```php
<?php

namespace Tests\CriticalPath\{Category};

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Critical path test: {Description}
 * 
 * @group critical
 * @group {category}
 */
class {TestName}Test extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function {description}(): void
    {
        // Arrange
        // ...

        // Act
        // ...

        // Assert
        // $this->assert...
    }
}
```

---

## 5. Adding New Critical Path Tests

To add a new critical path test:

1. Create file in appropriate `tests/CriticalPath/{Category}/` directory
2. Use `@group critical` annotation
3. Include comprehensive test documentation
4. Add to this document's test matrix
5. Ensure test passes before PR merge

---

## 6. Related Documents

- [Test Coverage Policy](./test-coverage-policy.md)
- [Package Classification](./package-classification.md)
- [New Package Requirements](./new-package-requirements.md)
