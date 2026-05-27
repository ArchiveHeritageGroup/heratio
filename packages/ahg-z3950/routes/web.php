<?php

// Z39.50 client + SRU server.
//
// All routes for this package are registered in App\Providers\AppServiceProvider
// instead of here because the slug catch-all (`/{slug}`) in
// ahg-information-object-manage runs in the locked tree and matches any
// top-level word not in its exclusion regex. Since `ahg-z3950` sorts after
// `ahg-information-object-manage` alphabetically, package-level route
// registration loses to the catch-all. Hoisting the routes into
// AppServiceProvider::boot() gets them registered before the catch-all.
//
// This file is intentionally empty - the package still ships its routes,
// they just live one tier up.
