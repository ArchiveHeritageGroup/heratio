<?php

/**
 * ahg-ric-manage configuration.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems. AGPL-3.0-or-later.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Edit-route middleware (separability seam, #1425)
    |--------------------------------------------------------------------------
    | The same plugin serves two hosts: this platform (Heratio ACL) and the
    | standalone OpenRiC service (token auth). On Heratio the edit route is
    | gated by 'acl:update'; the OpenRiC host sets RIC_MANAGE_EDIT_MIDDLEWARE=
    | api.auth:write in its .env. Keeping this in config - never hard-coded in
    | routes/web.php - is what lets one package boot cleanly on both.
    */
    'edit_middleware' => env('RIC_MANAGE_EDIT_MIDDLEWARE', 'acl:update'),

    /*
    | The RiC-O version string stamped onto information_object.source_standard
    | when a record is saved through this editor.
    */
    'source_standard' => 'RiC-O 1.0',
];
