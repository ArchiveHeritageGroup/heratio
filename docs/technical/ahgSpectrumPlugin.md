# ahgSpectrumPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Museum  
**Dependencies:** atom-framework

---

## Overview

UK Collections Trust Spectrum 5.0 procedures implementation for museum collections management including loans, location tracking, valuation, and movement control.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│           spectrum_loan                 │
├─────────────────────────────────────────┤
│ PK id INT                              │
│                                         │
│ -- LOAN DETAILS --                      │
│    loan_number VARCHAR UNIQUE           │
│    loan_type ENUM                       │
│    loan_direction ENUM                  │
│    status ENUM                          │
│                                         │
│ -- BORROWER/LENDER --                   │
│    institution_name VARCHAR             │
│    contact_name VARCHAR                 │
│    contact_email VARCHAR                │
│    contact_phone VARCHAR                │
│    address TEXT                         │
│                                         │
│ -- DATES --                             │
│    request_date DATE                    │
│    approval_date DATE                   │
│    loan_start DATE                      │
│    loan_end DATE                        │
│    actual_return_date DATE              │
│                                         │
│ -- PURPOSE --                           │
│    purpose ENUM                         │
│    exhibition_title VARCHAR             │
│    venue VARCHAR                        │
│                                         │
│ -- INSURANCE --                         │
│    insurance_value DECIMAL              │
│    insurance_type ENUM                  │
│    policy_number VARCHAR                │
│                                         │
│ -- CONDITIONS --                        │
│    special_conditions TEXT              │
│    environmental_requirements JSON      │
│    security_requirements TEXT           │
│                                         │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│         spectrum_loan_item              │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK loan_id INT                         │
│ FK object_id INT                       │
│    condition_out VARCHAR                │
│    condition_in VARCHAR                 │
│    insurance_value DECIMAL              │
│    packing_requirements TEXT            │
│    handling_requirements TEXT           │
│    dispatched_at TIMESTAMP              │
│    returned_at TIMESTAMP                │
│    notes TEXT                           │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         spectrum_location               │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK parent_id INT                       │
│    name VARCHAR                         │
│    code VARCHAR UNIQUE                  │
│    location_type ENUM                   │
│    building VARCHAR                     │
│    floor VARCHAR                        │
│    room VARCHAR                         │
│    unit VARCHAR                         │
│    shelf VARCHAR                        │
│    capacity INT                         │
│    current_count INT                    │
│    environmental_zone VARCHAR           │
│    security_level INT                   │
│    is_active TINYINT                    │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
              │
              │ Referenced by
              ▼
┌─────────────────────────────────────────┐
│       spectrum_object_location          │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │
│ FK location_id INT                     │
│    location_date DATE                   │
│    location_type ENUM                   │
│    moved_by INT                         │
│    reason TEXT                          │
│    is_current TINYINT                   │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         spectrum_movement               │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │
│    movement_number VARCHAR              │
│    movement_type ENUM                   │
│    from_location_id INT                 │
│    to_location_id INT                   │
│    movement_date DATE                   │
│    reason ENUM                          │
│    method VARCHAR                       │
│    authorized_by INT                    │
│    handled_by INT                       │
│    condition_before VARCHAR             │
│    condition_after VARCHAR              │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│         spectrum_valuation              │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK object_id INT                       │
│    valuation_date DATE                  │
│    valuation_type ENUM                  │
│    currency VARCHAR                     │
│    value DECIMAL                        │
│    low_estimate DECIMAL                 │
│    high_estimate DECIMAL                │
│    valuer_name VARCHAR                  │
│    valuer_organization VARCHAR          │
│    valuation_method VARCHAR             │
│    next_review_date DATE                │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

---

## Spectrum Procedures Covered

| Procedure | Description |
|-----------|-------------|
| Object Entry | Receiving objects into custody |
| Acquisition | Taking ownership |
| Loans In | Borrowing objects |
| Loans Out | Lending objects |
| Location | Tracking locations |
| Movement | Moving objects |
| Cataloguing | Recording information |
| Condition | Documenting condition |
| Valuation | Recording value |
| Insurance | Coverage management |
| Dispatch | Sending objects out |
| Loss/Damage | Recording incidents |
| Deaccession | Removing from collection |
| Retrospective | Updating old records |

---

## Service Methods

### SpectrumService

```php
namespace ahgSpectrumPlugin\Service;

class SpectrumService
{
    // Loans
    public function createLoan(array $data): int
    public function updateLoan(int $id, array $data): bool
    public function addLoanItem(int $loanId, int $objectId, array $data): int
    public function dispatchLoanItem(int $itemId): bool
    public function returnLoanItem(int $itemId, array $conditionData): bool
    public function getActiveLoans(): Collection
    public function getOverdueLoans(): Collection
    
    // Locations
    public function createLocation(array $data): int
    public function getLocationHierarchy(): array
    public function getObjectsAtLocation(int $locationId): Collection
    public function updateObjectLocation(int $objectId, int $locationId, array $data): bool
    public function getCurrentLocation(int $objectId): ?array
    
    // Movements
    public function recordMovement(int $objectId, array $data): int
    public function getMovementHistory(int $objectId): Collection
    public function getPendingMovements(): Collection
    
    // Valuations
    public function recordValuation(int $objectId, array $data): int
    public function getCurrentValuation(int $objectId): ?array
    public function getValuationHistory(int $objectId): Collection
    public function getValuationsForReview(int $days = 90): Collection
    public function getTotalCollectionValue(): array
}
```

---

*Part of the AtoM AHG Framework*
