# AtoM OAI-PMH blocked by the JavaScript challenge (endpoint_exceptions not matching)

**Summary:** In AtoM 2.9.x with the JavaScript browser-verification challenge enabled (`config/appChallenge.yml`, `activated: true`), automated OAI-PMH harvesting keeps hitting the challenge page even after adding `/oai` and `/;oai` to `endpoint_exceptions`. Root cause: the challenge filter matches exception paths against the full `REQUEST_URI` **including the query string**, and the generated regex cannot match a base path that is immediately followed by `?query` - which is exactly how OAI is requested (`/;oai?verb=...`). So the exception never fires and the challenge always runs.

## Why the exceptions are ignored (from `lib/challenge/filter.php`)

Each `endpoint_exceptions` entry is compiled to:

```
#^<escaped-path>(/.*)?$#
```

and tested against `$_SERVER['REQUEST_URI']` (which includes the query string).

- `/api` **works** because API calls look like `/api/informationobjects?...` - there is a `/subpath` after `/api`, and `(/.*)` greedily swallows the rest including the query string.
- `/oai` / `/;oai` **fail** because a real OAI request is `/;oai?verb=ListRecords&metadataPrefix=oai_ead` - the base path goes straight to `?query` with no subpath. `(/.*)?$` cannot match a `?...` that directly follows the base, so it falls through to the challenge.

Any endpoint accessed as `/base?query` (no subpath) can never be excepted by this mechanism as written.

## Fixes

1. **Bypass by client IP - no code change (recommended for automation).** The filter also bypasses via `QubitUserChallenge::shouldBypassChallenge()`, independent of the URL. Add the harvester's address/subnet to `appChallenge.yml`:

   ```yaml
   cidr_exceptions:
     - '203.0.113.42/32'   # harvesting server (example IP)
   ```

   or use `network_user_agent_exceptions` (a `src_net` + `user_agent` regex pair) to scope by both. Then `php symfony cc` and restart php-fpm.

2. **Make `endpoint_exceptions` work for OAI - one-line code change.** Match on path only, not the full URI. In `lib/challenge/filter.php`, replace `$requestUri = $_SERVER['REQUEST_URI'] ?? '/';` with:

   ```php
   $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
   ```

   Then a `/;oai` exception matches. Confirm the exact base path first.

3. **Confirm the exact OAI base path.** Check what the harvester requests: `/;oai`, `/oai`, or `/index.php/;oai`. The leading segment must match the start of `REQUEST_URI` (the `;` matters; a non-clean-URL setup includes `/index.php`).

## "DC works but EAD doesn't" is a separate question

If both formats go through the same `/;oai` endpoint, the challenge alone cannot let `oai_dc` through while blocking `oai_ead`. Capture the raw response for the failing EAD request:

```bash
curl -i 'https://<host>/;oai?verb=ListRecords&metadataPrefix=oai_ead'
```

- Challenge HTML returned => the exception issue above (a passing DC run was likely using a cached challenge/visited cookie).
- OAI error such as `cannotDisseminateFormat`, or a 200 with empty EAD => a metadata-format issue, not the challenge; check `verb=ListMetadataFormats` offers `oai_ead` and that the target records actually disseminate EAD.
