> Heratio Help Center article. Category: Library.

# Library Acquisitions API (JSON:API) - User Guide

**Version:** 1.0
**Date:** 2026-05-29
**Module:** ahg-library (heratio#1100)

---

## 1. Overview

The Library Acquisitions API exposes vendors, budgets, purchase orders and order
lines as a machine-readable JSON:API service. It lets external systems and
integrations place and track orders, manage vendor records, and read budget
utilisation without using the web acquisitions desk. It is the data foundation
the other library modules (serials, circulation, ONIX ingestion) build on.

Base URL: `https://<host>/api/library`

## 2. Authentication

Send an API key on every request:

```
X-API-Key: <your key>
            (or)
Authorization: Bearer <your key>
```

Keys are managed under the API settings. An authenticated admin session also
works for browser-based calls. Each key carries a scope:

- **read** - GET (list / show)
- **write** - POST / PATCH / PUT (create / update)
- **delete** - DELETE

Beyond the key scope, the acting account must also hold the matching library
permission (read / create / update / delete). Administrators are always allowed.

## 3. Resources and endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/vendors`, `/vendors/{id}` | List / show vendors. Filters: `?type=local|international`, `?active=1`, `?q=` |
| POST / PATCH / DELETE | `/vendors`, `/vendors/{id}` | Create / update / delete (delete blocked with 409 if the vendor has orders) |
| GET | `/budgets`, `/budgets/{id}` | List / show budgets. Filters: `?fiscal_year=`, `?status=` |
| POST / PATCH / DELETE | `/budgets`, `/budgets/{id}` | Create / update / delete (409 if linked orders) |
| GET | `/orders`, `/orders/{id}` | List / show orders. Filters: `?status=`, `?vendor_id=`, `?budget_code=`. Expand: `?include=lines,vendor,budget` |
| POST / PATCH / DELETE | `/orders`, `/orders/{id}` | Create (optional nested `lines[]`) / update / delete |
| GET / POST | `/orders/{order}/lines` | List / add lines on an order |
| GET / PATCH / DELETE | `/order-lines/{id}` | Show / update / delete a single line |

## 4. Request and response format

Responses follow JSON:API: a resource object is
`{"data": {"type": ..., "id": ..., "attributes": {...}, "relationships": {...}}}`,
and lists are `{"data": [ ... ], "meta": {"total": N, "page": P, "size": S}}`.

Resource types: `library-vendors`, `library-budgets`, `library-orders`,
`library-order-lines`.

Request bodies accept **either** a flat object or a JSON:API envelope:

```json
POST /api/library/vendors
{ "vendor_code": "VEND-01", "name": "Protea Books", "vendor_type": "local" }

# equivalently
{ "data": { "type": "library-vendors",
            "attributes": { "vendor_code": "VEND-01", "name": "Protea Books" } } }
```

## 5. Worked example - create an order with lines

```json
POST /api/library/orders
{
  "vendor_id": 12,
  "budget_code": "BUD-2026-MONO",
  "status": "ordered",
  "lines": [
    { "title": "Introduction to Information Science", "isbn": "9781783302659", "unit_price": 1250.00, "quantity": 1 },
    { "title": "The Discipline of Organizing", "unit_price": 980.00, "quantity": 2 }
  ]
}
```

The response includes the computed `total`, and the linked budget's
`committed_amount` is updated automatically. Adding, editing or deleting lines
later re-computes both the order total and the budget commitment.

## 6. Pagination

Use `?page[number]=2&page[size]=50` or the simpler `?page=2&per_page=50`
(maximum page size 100). The `meta` block reports `total`, `page` and `size`.

## 7. Demo data

For evaluation, seed sample vendors, budgets and an order:

```
php artisan db:seed --class=LibraryDemoSeeder
```

The seeder is idempotent - re-running it does not duplicate records.
