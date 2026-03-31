# ahgVendorPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** Administration  
**Dependencies:** atom-framework

---

## Overview

Vendor and supplier management for conservation services, digitization, storage, and other archival service providers.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────────────────┐
│              vendor                     │
├─────────────────────────────────────────┤
│ PK id INT                              │
│                                         │
│ -- IDENTITY --                          │
│    name VARCHAR                         │
│    trading_name VARCHAR                 │
│    registration_number VARCHAR          │
│    vat_number VARCHAR                   │
│    bee_level VARCHAR                    │
│    bee_certificate VARCHAR              │
│                                         │
│ -- CONTACT --                           │
│    contact_person VARCHAR               │
│    email VARCHAR                        │
│    phone VARCHAR                        │
│    mobile VARCHAR                       │
│    website VARCHAR                      │
│                                         │
│ -- ADDRESS --                           │
│    address_line1 VARCHAR                │
│    address_line2 VARCHAR                │
│    city VARCHAR                         │
│    province VARCHAR                     │
│    postal_code VARCHAR                  │
│    country VARCHAR                      │
│                                         │
│ -- SERVICES --                          │
│    services JSON                        │
│    specializations JSON                 │
│                                         │
│ -- BANKING --                           │
│    bank_name VARCHAR                    │
│    bank_branch VARCHAR                  │
│    account_number VARCHAR               │
│    account_type VARCHAR                 │
│                                         │
│ -- STATUS --                            │
│    status ENUM                          │
│    rating INT (1-5)                     │
│    notes TEXT                           │
│    is_preferred TINYINT                 │
│                                         │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│           vendor_contract               │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK vendor_id INT                       │
│    contract_number VARCHAR              │
│    title VARCHAR                        │
│    description TEXT                     │
│    service_type ENUM                    │
│    start_date DATE                      │
│    end_date DATE                        │
│    value DECIMAL                        │
│    payment_terms VARCHAR                │
│    status ENUM                          │
│    document_path VARCHAR                │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘
              │
              │ 1:N
              ▼
┌─────────────────────────────────────────┐
│           vendor_job                    │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK vendor_id INT                       │
│ FK contract_id INT                     │
│    job_number VARCHAR                   │
│    title VARCHAR                        │
│    description TEXT                     │
│    service_type ENUM                    │
│    status ENUM                          │
│    priority ENUM                        │
│    start_date DATE                      │
│    due_date DATE                        │
│    completed_date DATE                  │
│    estimated_cost DECIMAL               │
│    actual_cost DECIMAL                  │
│    assigned_to INT                      │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
│    updated_at TIMESTAMP                 │
└─────────────────────────────────────────┘
              │
              │ N:M
              ▼
┌─────────────────────────────────────────┐
│          vendor_job_item                │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK job_id INT                          │
│ FK object_id INT                       │
│    object_type VARCHAR                  │
│    quantity INT                         │
│    unit_cost DECIMAL                    │
│    notes TEXT                           │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│          vendor_evaluation              │
├─────────────────────────────────────────┤
│ PK id INT                              │
│ FK vendor_id INT                       │
│ FK job_id INT                          │
│    evaluation_date DATE                 │
│    quality_rating INT (1-5)             │
│    timeliness_rating INT (1-5)          │
│    communication_rating INT (1-5)       │
│    value_rating INT (1-5)               │
│    overall_rating INT (1-5)             │
│    comments TEXT                        │
│    evaluated_by INT                     │
│    created_at TIMESTAMP                 │
└─────────────────────────────────────────┘
```

---

## Service Types

| Type | Description |
|------|-------------|
| conservation | Paper/book conservation |
| digitization | Scanning, photography |
| binding | Bookbinding services |
| storage | Offsite storage |
| transport | Moving/courier |
| pest_control | IPM services |
| environmental | Climate monitoring |
| it_services | Technical support |
| consulting | Advisory services |

---

## Service Methods

### VendorService

```php
namespace ahgVendorPlugin\Service;

class VendorService
{
    // Vendors
    public function createVendor(array $data): int
    public function updateVendor(int $id, array $data): bool
    public function getVendor(int $id): ?array
    public function listVendors(array $filters): Collection
    public function searchVendors(string $query): Collection
    public function getVendorsByService(string $serviceType): Collection
    
    // Contracts
    public function createContract(int $vendorId, array $data): int
    public function updateContract(int $id, array $data): bool
    public function getActiveContracts(int $vendorId): Collection
    public function getExpiringContracts(int $days = 30): Collection
    
    // Jobs
    public function createJob(int $vendorId, array $data): int
    public function updateJob(int $id, array $data): bool
    public function addJobItems(int $jobId, array $items): bool
    public function getJobsByStatus(string $status): Collection
    public function completeJob(int $id, array $data): bool
    
    // Evaluations
    public function evaluateVendor(int $vendorId, int $jobId, array $ratings): int
    public function getVendorRating(int $vendorId): float
    public function getEvaluationHistory(int $vendorId): Collection
}
```

---

*Part of the AtoM AHG Framework*
