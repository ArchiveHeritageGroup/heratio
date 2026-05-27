# Marketplace public REST API (issue #736)

Heratio v1.89+ exposes the marketplace through the standard `/api/v2/` surface used by the AHG mobile auction app and external bidders. All routes live in `packages/ahg-api/routes/api.php` inside the existing `api/v2` group, which means they automatically inherit the `api.cors`, `api.auth:read`, `api.ratelimit`, `api.log`, `api.etag`, and `api.idempotency` middleware that protect every other v2 endpoint.

## Public endpoints

| Method | Path                                              | Auth          | Purpose                                                |
| ------ | ------------------------------------------------- | ------------- | ------------------------------------------------------ |
| GET    | /api/v2/marketplace/search                        | api.auth:read  | Listings search (q, sector, category, sort, page)      |
| POST   | /api/v2/marketplace/bid                           | api.auth:write | Place a bid {listing_id, amount, max_bid?, bidder_id?} |
| GET    | /api/v2/marketplace/auction/{id}/status           | api.auth:read  | Current high bid + time-to-close + reserve_met         |
| POST   | /api/v2/marketplace/favourite                     | api.auth:write | Toggle favourite {listing_id, user_id?}                |
| GET    | /api/v2/marketplace/currencies                    | api.auth:read  | Supported currency codes                               |
| GET    | /api/v2/marketplace/categories                    | api.auth:read  | Listing categories (optionally `?sector=`)             |

### Authentication and identity resolution

The bearer-token middleware sets `api_user_id` from the `ahg_api_key` row. The mobile app calls `/bid` and `/favourite` with `bidder_id` / `user_id` in the body so it can act on behalf of an end-user that authenticated against the app. Resolution order inside the controller:

1. Session user (admin call via cookie)
2. Body parameter (`bidder_id` or `user_id`)
3. `api_user_id` attribute attached by the api.auth middleware

If none resolve, the endpoint returns 422 `bidder_required` / `user_required`.

### Search response shape

`paginated()` from `BaseApiController` - so the mobile client gets `{success, data, meta:{total,page,limit,last_page}, links:{self,next,prev}, timestamp}`. Each listing row carries a normalised `image` field (alias for `featured_image_path`) so the mobile client never has to fall back to multiple keys.

### Bid round-trip

```
curl -X POST https://heratio.example.org/api/v2/marketplace/bid \
  -H 'Authorization: Bearer ahg_live_XXXX' \
  -H 'Content-Type: application/json' \
  -d '{"listing_id":42,"amount":1500.00,"bidder_id":17}'
```

Response (201 on success): `{success:true, data:{bid_id, auction:{...status}, listing_id}, timestamp}`. A rejected bid (too low, auction ended, seller bidding on own listing) returns 422 `bid_rejected` with the human-readable reason from `MarketplaceService::placeBid()`.

### Currency / category sources

Per CLAUDE.md the source of truth is `ahg_dropdown` (taxonomy `currency` / `marketplace_category`). The controller falls back to the legacy `marketplace_currency` / `marketplace_category` tables, then to a hard-coded ZAR/USD/EUR/GBP list, so the mobile client never sees an empty array.

## Admin batch payouts (CSV upload + commit)

`POST /admin/marketplace/payouts/batch` accepts a CSV with header `payout_id[,reference,notes]` and runs in two phases:

1. Without `commit=1` the controller parses the CSV, validates each row against `marketplace_payout`, and re-renders the admin payouts page with a `csvPreview` block (rows, ids, errors).
2. With `commit=1` the controller applies any `reference`/`notes` overrides, then calls `MarketplaceService::batchProcessPayouts()` for the resolved IDs and flashes the processed/skipped/errors summary.

This is additive - the existing checkbox-form `POST /admin/marketplace/payouts-batch` route is unchanged so the legacy admin UI keeps working.

## PSIS twin

`atom-ahg-plugins#82` stays open. Reference actions on the PSIS side:

- `ahgMarketplacePlugin/modules/marketplace/actions/apiSearchAction.class.php`
- `apiBidAction.class.php`
- `apiAuctionStatusAction.class.php`
- `apiFavouriteAction.class.php`
- `apiCurrenciesAction.class.php`
- `apiCategoriesAction.class.php`
- `adminPayoutsBatchAction.class.php`

The Heratio implementation mirrors the request/response contract; the only deliberate divergence is that the Laravel controller wraps everything in the standard `BaseApiController` envelope (`success`/`data`/`meta`/`links`/`timestamp`) instead of PSIS's flat top-level keys, because all of Heratio's other v2 endpoints already use that envelope and the mobile client expects it.
