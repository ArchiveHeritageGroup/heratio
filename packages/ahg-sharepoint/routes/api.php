<?php

use Illuminate\Support\Facades\Route;

// Phase 3 — M365-side connector feed.
// AAD JWT validation via firebase/php-jwt against AAD JWKS.
// Routes will live under /api/v2/sharepoint/connector/* once the package
// AhgSharePointConnectorController is implemented in Phase 3.
//
// Mirror of atom-ahg-plugins/ahgSharePointPlugin extending the apiv2 module
// in atom-ahg-plugins/ahgAPIPlugin.
//
// Phase 1: file present so the route group registers cleanly; no routes yet.
