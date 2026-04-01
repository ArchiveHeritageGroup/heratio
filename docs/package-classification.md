# Package Classification Policy

**Version:** 1.0  
**Effective Date:** 2026-04-01  
**Owner:** Development Team

---

## 1. Overview

Heratio packages are classified into three tiers based on their impact on the system. Classification determines coverage thresholds and maintenance requirements.

---

## 2. Classification Tiers

### 2.1 Foundational Packages

**Definition:**  
Packages that provide infrastructure, core services, or shared functionality used by multiple other packages. Failure in a foundational package impacts many other packages.

**Characteristics:**
- No direct user-facing functionality
- Provides services to other packages
- Shared by 3+ other packages
- Changes propagate widely

**Examples:**
- `ahg-core` - Core models, base services, shared utilities
- `ahg-acl` - Access control list, permissions infrastructure
- `ahg-audit-trail` - Audit logging infrastructure

**Coverage Target:** 60% (long-term goal)

**Maintenance:**
- Weekly dependency health checks
- Monthly architectural review
- Breaking changes require extended deprecation

---

### 2.2 Critical Packages

**Definition:**  
Packages whose failure directly impacts core user-facing functionality. These are essential for the platform to operate.

**Characteristics:**
- Direct user interaction
- Core archival workflows
- Primary data operations
- Authentication/authorization gates

**Examples:**
- `ahg-core` - Core models and base services
- `ahg-actor-manage` - Actor/authority record management
- `ahg-information-object-manage` - Information object management
- `ahg-search` - Search functionality
- `ahg-term-taxonomy` - Taxonomy and term management
- `ahg-user-manage` - User management and authentication

**Coverage Target:** 50% minimum within 6 months

**Maintenance:**
- Bi-weekly dependency checks
- Required regression tests for all changes
- Mandatory code review

---

### 2.3 Standard Packages

**Definition:**  
Feature packages that provide specialized functionality without direct impact on core platform operation.

**Characteristics:**
- Specialized domain features
- Optional functionality
- Limited dependency impact
- Can be disabled without platform failure

**Examples:**
- `ahg-iiif-collection` - IIIF collection management
- `ahg-ric` - RIC-O serialization
- `ahg-donor-manage` - Donor management
- `ahg-storage-manage` - Physical storage management
- `ahg-export` - Export functionality
- `ahg-oai` - OAI-PMH provider

**Coverage Target:** 30% minimum

**Maintenance:**
- Monthly dependency checks
- Standard code review process

---

## 3. Classification Process

### 3.1 Initial Classification
1. Package author proposes classification in PR
2. Lead developer reviews and approves
3. Classification recorded in this document
4. Coverage target assigned

### 3.2 Reclassification
Packages can be reclassified when:
- Functionality changes significantly
- Dependency graph changes
- User impact assessment changes

Reclassification requires:
1. Written proposal
2. Impact analysis
3. Lead developer approval
4. Timeline adjustment for coverage targets

---

## 4. Current Package Classifications

### Foundational
| Package | Status | Coverage Target | Timeline |
|---------|--------|-----------------|----------|
| ahg-core | Active | 60% | 12 months |
| ahg-acl | Active | 60% | 12 months |
| ahg-audit-trail | Pending | 60% | 12 months |

### Critical
| Package | Status | Coverage Target | Timeline |
|---------|--------|-----------------|----------|
| ahg-actor-manage | Active | 50% | 6 months |
| ahg-information-object-manage | Active | 50% | 6 months |
| ahg-search | Active | 50% | 6 months |
| ahg-term-taxonomy | Active | 50% | 6 months |
| ahg-user-manage | Active | 50% | 6 months |

### Standard
| Package | Status | Coverage Target |
|---------|--------|-----------------|
| ahg-iiif-collection | Active | 30% |
| ahg-ric | Active | 30% |
| ahg-donor-manage | Active | 30% |
| ahg-rights-holder-manage | Active | 30% |
| ahg-storage-manage | Active | 30% |
| ahg-accession-manage | Active | 30% |
| ahg-repository-manage | Active | 30% |
| ahg-function-manage | Active | 30% |
| ahg-export | Active | 30% |
| ahg-import | Pending | 30% |
| ahg-jobs | Active | 30% |
| ahg-statistics | Active | 30% |
| ahg-graphql | Active | 30% |
| ahg-oai | Active | 30% |
| ahg-settings | Active | 30% |

---

## 5. Coverage Timeline

| Quarter | Foundational | Critical | Standard |
|--------|-------------|----------|----------|
| Q2 2026 | 40% | 40% | 25% |
| Q3 2026 | 50% | 50% | 30% |
| Q4 2026 | 60% | 50% | 30% |
| Q1 2027 | 60% | 55% | 30% |

---

## 6. Compliance

All packages must meet their assigned coverage targets. Non-compliance results in:
1. Warning in CI (first 30 days)
2. Blocker in CI (after 30 days)
3. Required remediation plan

---

## 7. Related Documents

- [Test Coverage Policy](./test-coverage-policy.md)
- [Critical Path Tests](./critical-path-tests.md)
- [New Package Requirements](./new-package-requirements.md)
