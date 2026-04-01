# Heratio Test Coverage Policy

**Version:** 1.0  
**Effective Date:** 2026-04-01  
**Owner:** Development Team

---

## 1. Policy Statement

Heratio maintains a comprehensive testing strategy that prioritizes behavioral correctness over numeric coverage metrics. Coverage serves as a safety floor, not a success criterion.

---

## 2. Primary Gates (Non-Negotiable)

These gates must always pass regardless of coverage metrics:

### 2.1 Critical-Path Tests
**Status:** Mandatory  
**Rule:** Cannot be skipped under any circumstances  
**Scope:** All tests in `tests/CriticalPath/` directory

### 2.2 Regression Tests
**Status:** Mandatory for bug fixes  
**Rule:** Every bug fix PR must include a regression test  
**Scope:** Tests that reproduce and verify the bug fix

### 2.3 Authorization Tests
**Status:** Mandatory  
**Rule:** All role-based access must be tested  
**Scope:** Tests covering guest, authenticated, editor, admin, researcher roles

### 2.4 Workflow Tests
**Status:** Mandatory  
**Rule:** Core user journeys must be fully tested  
**Scope:** Playwright E2E tests for browse, search, CRUD operations

---

## 3. Coverage Thresholds

### 3.1 Application Code (app/)
| Metric | Threshold | Enforcement |
|--------|-----------|-------------|
| Global coverage | 50% minimum | CI fails below this |

### 3.2 Package Code (packages/)
| Package Type | Threshold | Timeline |
|--------------|-----------|----------|
| Standard packages | 30% minimum | Immediate |
| Critical packages | 50% minimum | Within 6 months |
| Foundational packages | 60% target | Over time |

### 3.3 Changed Files (Pull Requests)
| Metric | Threshold | Enforcement |
|--------|-----------|-------------|
| Changed PHP files | 60% minimum | CI fails below this |
| Zero coverage on changed files | Prohibited | Auto-blocked |

---

## 4. Regression Policy

### 4.1 Changed Files
- **Rule:** No unexplained coverage regression on changed files
- **Action:** CI fails if coverage drops on any changed PHP file

### 4.2 Critical Packages
- **Rule:** No meaningful coverage drop on critical packages
- **Action:** Requires explicit approval if regression occurs

### 4.3 Global Coverage
- **Rule:** Only allowed in exceptional cases
- **Action:** Requires explicit approval with justification

---

## 5. New Package Requirements

Every new package must include:

1. **Required directory structure:**
   - `packages/{name}/tests/TestCase.php`
   - `packages/{name}/tests/bootstrap.php`
   - `packages/{name}/tests/Unit/`
   - `packages/{name}/tests/Feature/`

2. **Required test outcomes:**
   - Test suite runs successfully (exit code 0)
   - Bootstrap/load test passes
   - Happy-path test passes
   - Failure-path test passes

3. **Coverage target:**
   - 30% minimum coverage baseline
   - Or documented exception approved by lead developer

---

## 6. 100% Coverage Policy

100% coverage is only required for:

- Small utility classes (< 50 lines)
- Serializer and formatter classes
- Validator classes
- Deterministic mapper classes
- Isolated helper functions

---

## 7. Enforcement

| Rule | CI Status | Blocker |
|------|-----------|---------|
| Critical-path tests fail | ❌ Failed | Yes |
| Regression test missing | ❌ Failed | Yes |
| Authorization tests fail | ❌ Failed | Yes |
| Workflow tests fail | ❌ Failed | Yes |
| app/ coverage < 50% | ❌ Failed | Yes |
| packages/ coverage < 30% | ❌ Failed | Yes |
| Changed files coverage < 60% | ❌ Failed | Yes |
| Zero coverage on changed file | ❌ Failed | Yes |

---

## 8. Exceptions

Exceptions require:
1. Written justification
2. Approval from lead developer
3. Documentation in PR description
4. Scheduled remediation plan

---

## 9. Review

This policy is reviewed quarterly or when significant architectural changes occur.

---

## 10. Related Documents

- [Package Classification](./package-classification.md)
- [Critical Path Tests](./critical-path-tests.md)
- [New Package Requirements](./new-package-requirements.md)
