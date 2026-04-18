<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Swagger UI HTML generator. Inlined rather than using Blade so this route
 * works on minimal Laravel deployments without requiring a writable view
 * cache directory. Pairs with AhgRic\Support\OpenApiSpec (the spec source).
 */

namespace AhgRic\Support;

class SwaggerUiHtml
{
    public static function render(string $specUrl, string $baseUrl): string
    {
        $host = htmlspecialchars((string) parse_url($baseUrl, PHP_URL_HOST), ENT_QUOTES);
        $baseEsc = htmlspecialchars($baseUrl, ENT_QUOTES);
        $specEsc = htmlspecialchars($specUrl, ENT_QUOTES);
        $specJs  = json_encode($specUrl);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>OpenRiC API Explorer · {$host}</title>
  <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
  <style>
    body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
    .ric-banner {
      background: #0f172a; color: #e5e7eb; padding: 0.8rem 1.2rem;
      border-bottom: 1px solid #334155; display: flex; align-items: center;
      gap: 0.8rem; flex-wrap: wrap;
    }
    .ric-banner h1 { margin: 0; font-size: 1rem; font-weight: 600; }
    .ric-banner h1 small { color: #9ca3af; font-weight: 400; margin-left: 0.5rem; font-size: 0.8rem; }
    .ric-banner a { color: #60a5fa; text-decoration: none; font-size: 0.85rem; }
    .ric-banner a:hover { text-decoration: underline; }
    .ric-banner .right { margin-left: auto; display: flex; gap: 1rem; flex-wrap: wrap; }
    .ric-banner code { background: #1f2937; color: #e5e7eb; padding: 0.1rem 0.3rem; border-radius: 3px; font-size: 0.8rem; }
    .swagger-ui .topbar { display: none; }
    .swagger-ui .info { margin-top: 1.5rem; }
  </style>
</head>
<body>
  <div class="ric-banner">
    <h1><a href="https://openric.org" style="color:#e5e7eb;">OpenRiC</a> API Explorer
      <small>serving <code>{$baseEsc}</code></small></h1>
    <div class="right">
      <a href="{$specEsc}" target="_blank" rel="noopener">openapi.json ↗</a>
      <a href="https://capture.openric.org" target="_blank" rel="noopener">Capture ↗</a>
      <a href="https://viewer.openric.org" target="_blank" rel="noopener">Viewer ↗</a>
      <a href="https://openric.org/conformance/" target="_blank" rel="noopener">Conformance ↗</a>
      <a href="https://openric.org/" target="_blank" rel="noopener">Spec ↗</a>
    </div>
  </div>

  <div id="swagger-ui"></div>

  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
  <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
  <script>
    window.onload = () => {
      const saved = localStorage.getItem('openric-docs-apikey') || '';
      const ui = SwaggerUIBundle({
        url: {$specJs},
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'BaseLayout',
        tryItOutEnabled: true,
        persistAuthorization: true,
        defaultModelsExpandDepth: 1,
        docExpansion: 'list',
        filter: true,
      });
      if (saved) {
        try { ui.preauthorizeApiKey('ApiKeyAuth', saved); } catch (e) {}
      }
      window.ui = ui;
    };
  </script>
</body>
</html>
HTML;
    }
}
