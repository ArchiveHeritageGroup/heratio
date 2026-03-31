# Heratio -- Library Order System REST API

**Version:** 1.0
**Author:** The Archive and Heritage Group (Pty) Ltd
**Plugin:** ahgLibraryPlugin
**Base URL:** `https://psis.theahg.co.za`
**Content-Type:** `application/json`

---

## Table of Contents

- [Quick Start](#quick-start)
- [Authentication](#authentication)
- [Response Format](#response-format)
- [HTTP Status Codes](#http-status-codes)
- [Error Codes](#error-codes)
- [Endpoints](#endpoints)
  - [Orders](#orders)
    - [List Orders](#list-orders)
    - [Create Order](#create-order)
    - [Get Order](#get-order)
    - [Update Order](#update-order)
    - [Cancel Order](#cancel-order)
  - [Order Lines](#order-lines)
    - [Add Line](#add-line)
    - [Update Line](#update-line)
    - [Delete Line](#delete-line)
    - [Receive Line](#receive-line)
  - [Budgets](#budgets)
    - [List Budgets](#list-budgets)
    - [Create Budget](#create-budget)
  - [Batch Operations](#batch-operations)
    - [Batch ISBN Lookup](#batch-isbn-lookup)
    - [Batch Capture](#batch-capture)
- [Rate Limiting](#rate-limiting)
- [Versioning](#versioning)

---

## Quick Start

1. **Obtain an API key** from [keys.theahg.co.za](https://keys.theahg.co.za).
2. **Include the key** in every request as the `X-API-Key` header.
3. **Send JSON** in the request body for POST and PUT requests.
4. **All responses** are JSON with a consistent envelope: `success`, `data`, `meta`, `error`, `code`.

```bash
# Test connectivity -- list orders
curl -s -H "X-API-Key: YOUR_KEY" \
  https://psis.theahg.co.za/api/library/orders | python3 -m json.tool
```

---

## Authentication

All endpoints require the `X-API-Key` HTTP header. Keys are managed at [keys.theahg.co.za](https://keys.theahg.co.za).

| Detail | Value |
|--------|-------|
| Header | `X-API-Key` |
| Format | Plaintext key (server hashes with SHA-256 before lookup) |
| Storage | `ahg_api_key` table (`api_key` column stores the SHA-256 hash) |
| Expiry | Keys may have an `expires_at` timestamp; expired keys are rejected |
| Inactive | Keys with `is_active = 0` are rejected |

A missing or invalid key returns HTTP 401:

```json
{
  "success": false,
  "error": "Unauthorized",
  "code": "AUTH_REQUIRED"
}
```

---

## Response Format

Every response follows a consistent JSON envelope:

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "total": 42,
    "page": 1,
    "pages": 2
  }
}
```

On error:

```json
{
  "success": false,
  "error": "Human-readable error message",
  "code": "ERROR_CODE"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `success` | boolean | `true` on success, `false` on error |
| `data` | object/array | Response payload (omitted on some errors) |
| `meta` | object | Pagination and summary metadata (where applicable) |
| `error` | string | Error message (only on failure) |
| `code` | string | Machine-readable error code (only on failure) |

---

## HTTP Status Codes

| Code | Meaning | When Returned |
|------|---------|---------------|
| 200 | OK | Successful GET, PUT, DELETE |
| 201 | Created | Successful POST that creates a resource |
| 400 | Bad Request | Receive operation failed (e.g., quantity exceeds ordered) |
| 401 | Unauthorized | Missing or invalid API key |
| 404 | Not Found | Resource does not exist |
| 405 | Method Not Allowed | HTTP method not supported on this endpoint |
| 409 | Conflict | Order already cancelled, duplicate budget code, etc. |
| 422 | Unprocessable Entity | Validation error (missing required fields) |
| 500 | Internal Server Error | Unexpected server-side error |

---

## Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `AUTH_REQUIRED` | 401 | API key missing or invalid |
| `VALIDATION_ERROR` | 422 | Required field missing or invalid input |
| `NOT_FOUND` | 404 | Order, line, or budget not found |
| `ORDER_CANCELLED` | 409 | Attempted to modify or add lines to a cancelled order |
| `ALREADY_CANCELLED` | 409 | Order is already in cancelled status |
| `DUPLICATE_BUDGET` | 409 | Budget code already exists for the given fiscal year |
| `RECEIVE_FAILED` | 400 | Line receive operation failed |
| `METHOD_NOT_ALLOWED` | 405 | HTTP method not supported for this endpoint |
| `INTERNAL_ERROR` | 500 | Unexpected server error |

---

## Endpoints

### Orders

#### List Orders

Retrieve a paginated list of orders with optional search and filters.

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/api/library/orders` |

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `q` | string | (empty) | Free-text search across order fields |
| `status` | string | (all) | Filter by order status (e.g., `pending`, `approved`, `cancelled`) |
| `order_type` | string | (all) | Filter by order type (e.g., `purchase`, `standing_order`) |
| `page` | integer | 1 | Page number (minimum 1) |
| `limit` | integer | 25 | Results per page (1--100) |

**Example:**

```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  "https://psis.theahg.co.za/api/library/orders?status=pending&page=1&limit=10"
```

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "vendor_name": "Juta & Co",
      "order_date": "2026-03-01",
      "order_type": "purchase",
      "order_status": "pending",
      "total_amount": 1250.00,
      "currency": "ZAR"
    }
  ],
  "meta": {
    "total": 47,
    "page": 1,
    "pages": 5
  }
}
```

---

#### Create Order

Create a new acquisition order.

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/orders` |

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `vendor_name` | string | Yes | -- | Vendor/supplier name |
| `vendor_account` | string | No | null | Vendor account number |
| `order_date` | string | No | today | Order date (YYYY-MM-DD) |
| `order_type` | string | No | `purchase` | Order type (e.g., `purchase`, `standing_order`, `gift`, `exchange`) |
| `budget_id` | integer | No | null | FK to `library_budget.id` |
| `budget_code` | string | No | null | Budget code reference |
| `currency` | string | No | `USD` | ISO 4217 currency code |
| `notes` | string | No | null | Free-text notes |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "vendor_name": "Juta & Co",
    "vendor_account": "JUTA-001",
    "order_date": "2026-03-09",
    "order_type": "purchase",
    "budget_id": 5,
    "currency": "ZAR",
    "notes": "Annual legal reference order"
  }' \
  https://psis.theahg.co.za/api/library/orders
```

**Response (201):**

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 42,
      "vendor_name": "Juta & Co",
      "vendor_account": "JUTA-001",
      "order_date": "2026-03-09",
      "order_type": "purchase",
      "order_status": "pending",
      "total_amount": 0,
      "currency": "ZAR",
      "notes": "Annual legal reference order",
      "created_at": "2026-03-09 10:30:00",
      "updated_at": "2026-03-09 10:30:00"
    },
    "lines": []
  }
}
```

**Error (422):**

```json
{
  "success": false,
  "error": "vendor_name is required",
  "code": "VALIDATION_ERROR"
}
```

---

#### Get Order

Retrieve a single order with all its line items.

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/api/library/orders/:id` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |

**Example:**

```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  https://psis.theahg.co.za/api/library/orders/42
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 42,
      "vendor_name": "Juta & Co",
      "vendor_account": "JUTA-001",
      "order_date": "2026-03-09",
      "order_type": "purchase",
      "order_status": "pending",
      "total_amount": 750.00,
      "currency": "ZAR",
      "notes": "Annual legal reference order"
    },
    "lines": [
      {
        "id": 101,
        "order_id": 42,
        "title": "Constitutional Law of South Africa",
        "isbn": "9780702199684",
        "author": "Currie, I.",
        "publisher": "Juta",
        "quantity": 2,
        "unit_price": 375.00,
        "line_total": 750.00,
        "quantity_received": 0,
        "material_type": "book",
        "fund_code": "LAW-2026",
        "notes": null
      }
    ]
  }
}
```

**Error (404):**

```json
{
  "success": false,
  "error": "Order not found",
  "code": "NOT_FOUND"
}
```

---

#### Update Order

Update an existing order. Only provided fields are changed.

| | |
|---|---|
| **Method** | `PUT` |
| **URL** | `/api/library/orders/:id` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |

**Request Body (all fields optional):**

| Field | Type | Description |
|-------|------|-------------|
| `vendor_name` | string | Vendor/supplier name |
| `vendor_account` | string | Vendor account number |
| `order_date` | string | Order date (YYYY-MM-DD) |
| `order_type` | string | Order type |
| `budget_id` | integer | FK to `library_budget.id` |
| `currency` | string | ISO 4217 currency code |
| `notes` | string | Free-text notes |

**Example:**

```bash
curl -s -X PUT -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "notes": "Updated: expedite delivery requested",
    "currency": "ZAR"
  }' \
  https://psis.theahg.co.za/api/library/orders/42
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 42,
      "vendor_name": "Juta & Co",
      "notes": "Updated: expedite delivery requested",
      "currency": "ZAR",
      "updated_at": "2026-03-09 11:00:00"
    },
    "lines": [ ... ]
  }
}
```

**Error (409) -- cancelled order:**

```json
{
  "success": false,
  "error": "Cannot update a cancelled order",
  "code": "ORDER_CANCELLED"
}
```

---

#### Cancel Order

Cancel an order by setting its status to `cancelled`. This is a soft delete -- the order record is preserved.

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `/api/library/orders/:id` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |

**Example:**

```bash
curl -s -X DELETE -H "X-API-Key: YOUR_KEY" \
  https://psis.theahg.co.za/api/library/orders/42
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 42,
    "order_status": "cancelled"
  }
}
```

**Error (409) -- already cancelled:**

```json
{
  "success": false,
  "error": "Order is already cancelled",
  "code": "ALREADY_CANCELLED"
}
```

---

### Order Lines

#### Add Line

Add a line item to an existing order. Cannot add lines to cancelled orders.

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/orders/:id/lines` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `title` | string | Yes | -- | Item title |
| `isbn` | string | No | null | ISBN-10 or ISBN-13 |
| `author` | string | No | null | Author name(s) |
| `publisher` | string | No | null | Publisher name |
| `quantity` | integer | No | 1 | Quantity ordered (minimum 1) |
| `unit_price` | float | No | 0 | Unit price |
| `material_type` | string | No | null | Material type (e.g., `book`, `serial`, `dvd`) |
| `fund_code` | string | No | null | Fund/budget code for this line |
| `notes` | string | No | null | Free-text notes |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Constitutional Law of South Africa",
    "isbn": "9780702199684",
    "author": "Currie, I.",
    "publisher": "Juta",
    "quantity": 2,
    "unit_price": 375.00,
    "material_type": "book",
    "fund_code": "LAW-2026"
  }' \
  https://psis.theahg.co.za/api/library/orders/42/lines
```

**Response (201):**

```json
{
  "success": true,
  "data": {
    "id": 101,
    "order_id": 42,
    "title": "Constitutional Law of South Africa",
    "isbn": "9780702199684",
    "author": "Currie, I.",
    "publisher": "Juta",
    "quantity": 2,
    "unit_price": 375.00,
    "line_total": 750.00,
    "quantity_received": 0,
    "material_type": "book",
    "fund_code": "LAW-2026",
    "notes": null,
    "created_at": "2026-03-09 10:35:00",
    "updated_at": "2026-03-09 10:35:00"
  }
}
```

---

#### Update Line

Update an existing line item. Only provided fields are changed. Changing `quantity` or `unit_price` automatically recalculates `line_total` and the parent order's `total_amount`.

| | |
|---|---|
| **Method** | `PUT` |
| **URL** | `/api/library/orders/:id/lines/:line_id` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |
| `line_id` | integer | Line item ID |

**Request Body (all fields optional):**

| Field | Type | Description |
|-------|------|-------------|
| `title` | string | Item title |
| `isbn` | string | ISBN |
| `author` | string | Author name(s) |
| `publisher` | string | Publisher name |
| `quantity` | integer | Quantity ordered (minimum 1) |
| `unit_price` | float | Unit price |
| `material_type` | string | Material type |
| `fund_code` | string | Fund code |
| `notes` | string | Notes |

**Example:**

```bash
curl -s -X PUT -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity": 5,
    "unit_price": 350.00
  }' \
  https://psis.theahg.co.za/api/library/orders/42/lines/101
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 101,
    "order_id": 42,
    "title": "Constitutional Law of South Africa",
    "quantity": 5,
    "unit_price": 350.00,
    "line_total": 1750.00,
    "quantity_received": 0,
    "updated_at": "2026-03-09 11:15:00"
  }
}
```

---

#### Delete Line

Permanently remove a line item from an order. The order's `total_amount` is recalculated.

| | |
|---|---|
| **Method** | `DELETE` |
| **URL** | `/api/library/orders/:id/lines/:line_id` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |
| `line_id` | integer | Line item ID |

**Example:**

```bash
curl -s -X DELETE -H "X-API-Key: YOUR_KEY" \
  https://psis.theahg.co.za/api/library/orders/42/lines/101
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "id": 101,
    "deleted": true
  }
}
```

---

#### Receive Line

Record receipt of items for a line. Increments `quantity_received` on the line item.

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/orders/:id/lines/:line_id/receive` |

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Order ID |
| `line_id` | integer | Line item ID |

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `quantity_received` | integer | No | 1 | Number of items received (minimum 1) |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "quantity_received": 2
  }' \
  https://psis.theahg.co.za/api/library/orders/42/lines/101/receive
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "line": {
      "id": 101,
      "order_id": 42,
      "title": "Constitutional Law of South Africa",
      "quantity": 5,
      "quantity_received": 2,
      "unit_price": 350.00,
      "line_total": 1750.00
    },
    "status": "partially_received",
    "quantity_received": 2
  }
}
```

**Error (400) -- receive failed:**

```json
{
  "success": false,
  "error": "Quantity received exceeds quantity ordered",
  "code": "RECEIVE_FAILED"
}
```

---

### Budgets

#### List Budgets

Retrieve all budgets, optionally filtered by fiscal year.

| | |
|---|---|
| **Method** | `GET` |
| **URL** | `/api/library/budgets` |

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `fiscal_year` | string | (all) | Filter by fiscal year (e.g., `2026`) |

**Example:**

```bash
curl -s -H "X-API-Key: YOUR_KEY" \
  "https://psis.theahg.co.za/api/library/budgets?fiscal_year=2026"
```

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "budget_code": "LAW-2026",
      "budget_name": "Legal Reference Materials",
      "fiscal_year": "2026",
      "allocated_amount": 50000.00,
      "spent_amount": 12500.00,
      "remaining_amount": 37500.00,
      "currency": "ZAR",
      "category": "acquisitions"
    }
  ],
  "meta": {
    "total": 1
  }
}
```

---

#### Create Budget

Create a new budget allocation. Budget codes must be unique within a fiscal year.

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/budgets` |

**Request Body:**

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `budget_code` | string | Yes | -- | Unique budget code |
| `fund_name` | string | Yes | -- | Fund/budget name |
| `fiscal_year` | string | No | current year | Fiscal year (e.g., `2026`) |
| `allocated_amount` | float | No | 0 | Total budget allocation |
| `currency` | string | No | `USD` | ISO 4217 currency code |
| `category` | string | No | `general` | Budget category |
| `notes` | string | No | null | Free-text notes |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "budget_code": "HIST-2026",
    "fund_name": "Historical Research Materials",
    "fiscal_year": "2026",
    "allocated_amount": 25000.00,
    "currency": "ZAR",
    "category": "acquisitions",
    "notes": "Approved by library committee 2026-02-15"
  }' \
  https://psis.theahg.co.za/api/library/budgets
```

**Response (201):**

```json
{
  "success": true,
  "data": {
    "id": 8,
    "budget_code": "HIST-2026",
    "budget_name": "Historical Research Materials",
    "fiscal_year": "2026",
    "allocated_amount": 25000.00,
    "spent_amount": 0,
    "remaining_amount": 25000.00,
    "currency": "ZAR",
    "category": "acquisitions",
    "notes": "Approved by library committee 2026-02-15"
  }
}
```

**Error (409) -- duplicate:**

```json
{
  "success": false,
  "error": "Budget code already exists for this fiscal year",
  "code": "DUPLICATE_BUDGET"
}
```

---

### Batch Operations

#### Batch ISBN Lookup

Look up metadata for multiple ISBNs in a single request. Uses the framework's `IsbnLookupService` (Open Library, Google Books).

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/batch/isbn-lookup` |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `isbns` | array of strings | Yes | List of ISBN-10 or ISBN-13 values (maximum 50) |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "isbns": ["9780702199684", "9780199541171", "0000000000000"]
  }' \
  https://psis.theahg.co.za/api/library/batch/isbn-lookup
```

**Response (200):**

```json
{
  "success": true,
  "data": [
    {
      "isbn": "9780702199684",
      "found": true,
      "data": {
        "title": "Constitutional Law of South Africa",
        "author": "Currie, Iain; De Waal, Johan",
        "publisher": "Juta",
        "publication_date": "2013",
        "pages": 832
      }
    },
    {
      "isbn": "9780199541171",
      "found": true,
      "data": {
        "title": "The Oxford Handbook of Archival Science",
        "author": "Eastwood, Terry; MacNeil, Heather",
        "publisher": "Oxford University Press",
        "publication_date": "2017",
        "pages": 576
      }
    },
    {
      "isbn": "0000000000000",
      "found": false,
      "error": "ISBN not found"
    }
  ],
  "meta": {
    "total": 3,
    "found": 2
  }
}
```

---

#### Batch Capture

Create multiple library items in a single request. Optionally link all items to an existing order as new order lines.

| | |
|---|---|
| **Method** | `POST` |
| **URL** | `/api/library/batch/capture` |

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `items` | array of objects | Yes | Library items to create (maximum 100) |
| `order_id` | integer | No | If provided, each item is also added as an order line |

**Item object fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `title` | string | Yes | Item title |
| `isbn` | string | No | ISBN |
| `author` | string | No | Author name(s) |
| `publisher` | string | No | Publisher name |
| `publication_date` | string | No | Publication date |
| `edition` | string | No | Edition (e.g., `3rd`) |
| `call_number` | string | No | Library call number |
| `material_type` | string | No | Material type |
| `subject` | string | No | Subject heading(s) |
| `language` | string | No | Language code (e.g., `en`, `af`) |
| `notes` | string | No | Notes |
| `quantity` | integer | No | Quantity (used when linking to order, default 1) |
| `unit_price` | float | No | Unit price (used when linking to order, default 0) |

**Example:**

```bash
curl -s -X POST -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": 42,
    "items": [
      {
        "title": "Archival Principles and Practice",
        "isbn": "9781856049382",
        "author": "Shepherd, Elizabeth",
        "publisher": "Facet Publishing",
        "material_type": "book",
        "quantity": 1,
        "unit_price": 420.00
      },
      {
        "title": "Preserving Digital Materials",
        "isbn": "9781856048347",
        "author": "Harvey, Ross",
        "publisher": "Facet Publishing",
        "material_type": "book",
        "quantity": 3,
        "unit_price": 380.00
      },
      {
        "title": "",
        "isbn": "0000000000"
      }
    ]
  }' \
  https://psis.theahg.co.za/api/library/batch/capture
```

**Response (200):**

```json
{
  "success": true,
  "data": {
    "created": [
      {
        "index": 0,
        "id": 501,
        "title": "Archival Principles and Practice"
      },
      {
        "index": 1,
        "id": 502,
        "title": "Preserving Digital Materials"
      }
    ],
    "errors": [
      {
        "index": 2,
        "error": "title is required"
      }
    ]
  },
  "meta": {
    "total_submitted": 3,
    "total_created": 2,
    "total_errors": 1,
    "order_id": 42
  }
}
```

---

## Rate Limiting

There is currently no rate limiting enforced on the API. Clients should implement reasonable request throttling on their side to avoid overloading the server. Future versions may introduce rate limiting with standard `X-RateLimit-*` headers.

**Recommended client-side limits:**

| Operation | Suggested Limit |
|-----------|----------------|
| List / Get requests | 60 requests per minute |
| Create / Update / Delete | 30 requests per minute |
| Batch ISBN lookup | 10 requests per minute |
| Batch capture | 5 requests per minute |

---

## Versioning

The current API version is **v1**, implied by the `/api/library/` path prefix. There is no explicit version segment in the URL at this time.

When breaking changes are introduced in a future release, the API will move to a versioned path scheme (e.g., `/api/v2/library/`). The v1 endpoints will remain available during a deprecation period.

**Backward compatibility guarantees for v1:**

- Existing fields will not be removed or renamed.
- New optional fields may be added to request and response objects.
- New endpoints may be added under the same prefix.
- Error codes will not change meaning.
