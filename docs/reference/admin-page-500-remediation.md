# Admin-page HTTP 500 remediation - diagnosis + common root causes

How the admin-page 500s found by the functional sweep were diagnosed and fixed
(GitHub #1282-#1295, plus the privacy-admin module). Use this as the playbook
when an authenticated admin page returns HTTP 500 on a fresh/standalone install.

## Fast diagnosis: get the real exception as JSON

Production hides the exception (generic 500) and several controllers catch-and-
render without logging, so `storage/logs/laravel.log` is often empty for these.
The reliable way to see the actual exception:

1. Temporarily set `APP_DEBUG=true` in `.env` (revert when done).
2. Authenticate, then request the page with `Accept: application/json` - Laravel
   returns `{"message","exception","file","line"}`:

```
jar=/tmp/d.txt; rm -f $jar
curl -s -c $jar http://HOST/login -o /tmp/l.html
tok=$(grep -oE 'name="_token"[^>]*value="[^"]+"' /tmp/l.html | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
curl -s -b $jar -c $jar -o /dev/null -d "_token=$tok" -d "email=ADMIN" -d "password=PW" http://HOST/login
curl -s -b $jar -H 'Accept: application/json' http://HOST/admin/some/page \
  | python3 -c "import sys,json;d=json.load(sys.stdin);print(d.get('message'),'@',d.get('file'),d.get('line'))"
```

Do NOT scrape the HTML Ignition page - it is a JS SPA and its `<title>` is just
"Laravel"; the visible text often catches unrelated JS strings (e.g. an Alpine
init message), not the exception.

## Recurring root-cause classes (and the fix)

Most admin 500s on a fresh install are NOT missing data - they are controller/
view wiring bugs. Fix in the controller/service/route; the Blade views are
locked (`.locked-paths`), so pass what the view needs rather than editing it.

1. **Wrong view namespace.** `view('library::...')` when the ServiceProvider
   registered `ahg-library`. Symptom: `No hint path defined for [library]`. Fix
   the controller's view() prefix to the registered namespace.

2. **Missing view variables.** A stub method `return view('x::y')` with no data;
   the view references `$foo`. Symptom: `Undefined variable $foo`. Pass every var
   the view uses (grep `\$name` on the blade). Query real data; default to
   `0`/`[]`/`collect()` so empty installs render an empty-state, not a 500.

3. **Wrong table / column name.** Symptom: `SQLSTATE Unknown column 'x'` or
   `Base table or view not found`. Verify against `information_schema` before
   coding. Real cases seen: `actor.class_name` -> `object.class_name` (class-table
   inheritance), `library_vendors` -> `library_vendor`, `code` -> `vendor_code`,
   `ill_number` -> `request_number`, `d.object_id` -> `information_object_id`.

4. **Dot-vs-hyphen route names.** A view calls `route('library.usage.export')`
   but the route is named `library.usage-export`. Symptom: `Route [x] not
   defined`. If only one view consumes it, rename the route; if multiple locked
   views use both spellings, add an alias route (same URI + method, second name).

5. **Missing layout.** `@extends('layouts.admin')` / `@extends('layouts.app')`
   when no such layout exists (canonical is `theme::layouts.1col`/`2col`).
   Symptom: `View [layouts.x] not found`. Add a thin compat wrapper view that
   `@extends('theme::layouts.1col')` (do not edit the locked child blade).

6. **Reserved `$errors` shadowed.** Passing `'errors' => [...]` (a plain array)
   clobbers Laravel's ViewErrorBag, so `$errors->any()` fatals
   (`Call to a member function any() on array`). Drop the key (let the real
   ViewErrorBag flow) or pass a `MessageBag`.

7. **`route()` missing a required param.** A list view links
   `route('actor.show', ['slug' => $row->slug])` and a row has `slug = null`
   (left-join). Symptom: `UrlGenerationException`. Filter nulls in the controller
   query (`whereNotNull`), or make the route param optional.

8. **Object-vs-array in the view.** Controller passes `(object)[...]` but the
   view does `$row['key']` (or vice versa). Symptom: `Cannot use object of type
   stdClass as array` / `Attempt to read property on array`. Match the shape the
   view expects. Note `(array) $collection` mangles a Collection - use
   `$collection->values()->all()`.

9. **Dead auto-stub routes.** Routes commented `Auto-registered stub routes`
   returning `view('ns::name')` for views that never existed, often misplaced and
   duplicating real parameterised routes elsewhere. Symptom: `View [name] not
   found`. Remove the stub block once you confirm the real feature exists at a
   proper route.

10. **GET hitting an action handler with no params.** A GET route bound to a
    method expecting an id/POST body inserts `0`/null and hits an FK or missing
    arg. Guard for the missing param and redirect gracefully (302) instead of
    500ing.

## Deploy + verify loop (standalone test VM)

Edit on the source host, copy to the test VM, clear caches, re-test authenticated;
iterate (each fix often reveals the next missing var):

```
scp file VM:/tmp/x.php
ssh VM 'sudo cp /tmp/x.php /usr/share/nginx/heratio/<path>; cd /usr/share/nginx/heratio \
  && sudo -u www-data php artisan optimize:clear && sudo -u www-data php artisan route:clear'
```

A page that needs a record id is correctly a 302 redirect (to its list/index)
when no id is supplied - that is a pass, not a failure. Only 5xx is a failure.
