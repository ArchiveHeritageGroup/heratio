# Library acquisitions JSON:API (#1100)

**Summary:** A JSON:API layer over the Phase-0 library acquisitions schema -
Eloquent models, REST CRUD endpoints, JSON:API resources, role-based policies,
the previously-missing `library_vendor` entity, and a demo seeder. Lives in the
`ahg-library` package. Mounted at `/api/library`, guarded by the shared ahg-api
key-auth middleware (`api.auth:read|write|delete`); controllers additionally
enforce `AclService` permissions for the acting account (administrators bypass).

## Endpoints

Base: `/api/library`. Auth: `X-API-Key` / `Authorization: Bearer` (or an
authenticated session). Write needs `write` scope + `create`/`update` permission;
delete needs `delete` scope + `delete` permission.

| Method | Path | Action |
|---|---|---|
| GET | `/vendors` `/vendors/{id}` | list / show vendors (`?type=`, `?active=`, `?q=`) |
| POST/PATCH/DELETE | `/vendors` `/vendors/{id}` | create / update / delete (409 if linked orders) |
| GET | `/budgets` `/budgets/{id}` | list / show budgets (`?fiscal_year=`, `?status=`) |
| POST/PATCH/DELETE | `/budgets` `/budgets/{id}` | create / update / delete (409 if linked orders) |
| GET | `/orders` `/orders/{id}` | list / show orders (`?status=`, `?vendor_id=`, `?budget_code=`, `?include=lines,vendor,budget`) |
| POST/PATCH/DELETE | `/orders` `/orders/{id}` | create (optional nested `lines[]`) / update / delete |
| GET/POST | `/orders/{order}/lines` | list / add order lines |
| GET/PATCH/DELETE | `/order-lines/{id}` | show / update / delete a line |

Pagination: `?page[number]`/`?page[size]` or `?page`/`?per_page` (max 100);
collections return `{data, meta:{total,page,size}}`.

## Request / response shape

Responses are JSON:API resource objects: `{data:{type,id,attributes,relationships}}`.
Types: `library-vendors`, `library-budgets`, `library-orders`,
`library-order-lines`. Request bodies accept either a flat object or a JSON:API
`{data:{attributes:{...}}}` envelope.

## Models

`AhgLibrary\Models\{LibraryVendor, LibraryBudget, LibraryOrder, LibraryOrderLine}`
with relationships (`order->vendor/lines/budget`, `vendor->orders`,
`budget->orders` via `budget_code`), `$casts`, scopes, and a computed
`budget.available_amount` (allocated - committed - spent).

## Policies

`LibraryOrderPolicy`, `LibraryBudgetPolicy`, `LibraryVendorPolicy` (extend
`LibraryAclPolicy`) map viewAny/view/create/update/delete to
`AclService::hasPermission` (read/create/update/delete), registered via
`Gate::policy`. Same gate as the web ACL and the API controllers - one source of
truth.

## Budget commitment

Creating/editing orders or lines recalculates the order totals and the budget's
`committed_amount`/`spent_amount` via `LibraryAcquisitionService` (committed =
SUM of non-cancelled order-line totals on that budget_code; spent = received
lines). NB: this fixed a latent bug where `recalculateBudgetByCode` called
`->value()` with no column and wrote to non-existent `spent`/`committed` columns.

## Demo data

`php artisan db:seed --class=LibraryDemoSeeder` - idempotent: 2 vendors, 2
budgets, a demo purchase order with 2 lines.

## Status

Shipped end-to-end (models, resources, policies, controllers, routes, vendor
migration, seeder, feature + smoke tests). PSIS parity twin:
atom-ahg-plugins#103.
