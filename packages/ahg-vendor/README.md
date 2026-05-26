# ahg-vendor

Vendor and supplier management for Heratio - vendor master records, service types, transactions, transaction items, and per-vendor contact rolodex. Migrated from the AtoM `ahgVendorPlugin`.

## Purpose

- Vendor CRUD (`/admin/vendor/{slug}`) with status tracking via `VendorStatusService`
- Service-type taxonomy (`/admin/vendor/service-types`) - dropdown source for purchase orders
- Transactions ledger (`/admin/vendor/transactions/browse`) - per-vendor invoices / POs
- Transaction items (line-item CRUD inside a transaction)
- Per-vendor contact list (add / update / delete)
- ACL-gated mutations (`acl:create`, `acl:update`, `acl:delete`)

## Install

Auto-discovered. The ServiceProvider registers routes and the `vendor` view namespace. Tables (`ahg_vendor`, `ahg_vendor_transaction`, `ahg_vendor_transaction_item`, `ahg_vendor_contact`) are installed by `database/install.sql`.

## Routes

All under `/admin/vendor` (`web` + `auth` middleware):

- `GET /admin/vendor` - dashboard
- `GET /admin/vendor/browse` (and `/list`)
- `GET|POST /admin/vendor/add`
- `GET /admin/vendor/{slug}` - view
- `GET|POST /admin/vendor/{slug}/edit`
- `POST /admin/vendor/{slug}/delete` (`acl:delete`)
- `GET|POST /admin/vendor/service-types`
- `GET /admin/vendor/transactions/browse`
- `GET|POST /admin/vendor/transactions/add`
- `GET|POST /admin/vendor/transactions/{id}/edit`
- `GET /admin/vendor/transactions/{id}`
- `POST /admin/vendor/transactions/{id}/status` (`acl:update`)
- `POST /admin/vendor/transactions/{txId}/item/add` (`acl:create`)
- `POST /admin/vendor/transactions/{txId}/item/{itemId}/update` (`acl:update`)
- `POST /admin/vendor/transactions/{txId}/item/{itemId}/remove` (`acl:delete`)
- `POST /admin/vendor/{slug}/contact/add` (`acl:create`)
- `POST /admin/vendor/{slug}/contact/{contactId}/update` (`acl:update`)
- `POST /admin/vendor/{slug}/contact/{contactId}/delete` (`acl:delete`)

Legacy redirects: `/vendor` -> `/admin/vendor`, `/vendor/transactions` -> `/admin/vendor/transactions/browse`.

## Key classes

| Class | Role |
|---|---|
| `Controllers\VendorController` | All HTTP endpoints (dashboard, CRUD, transactions, contacts) |
| `Services\VendorStatusService` | Status normalisation + transition rules |
| `Providers\AhgVendorServiceProvider` | Registers routes + the `vendor` view namespace |

## Notes

- Slug-based routes (`/{slug}/edit`, `/{slug}`) sit AFTER all literal paths in `routes/web.php` so they do not swallow `add`, `browse`, `transactions`, or `service-types`.
- `Route::match(['get','post'], ...)` is used wherever the controller renders the form on GET and persists on POST; the ACL check happens inside the controller for those endpoints.
