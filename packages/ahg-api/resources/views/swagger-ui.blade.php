<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Heratio API - Swagger UI' }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/swagger-ui/5.17.14/swagger-ui.css') }}">
    <style>
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="{{ asset('vendor/swagger-ui/5.17.14/swagger-ui-bundle.js') }}"></script>
    <script src="{{ asset('vendor/swagger-ui/5.17.14/swagger-ui-standalone-preset.js') }}"></script>
    <script>
        window.onload = function () {
            window.ui = SwaggerUIBundle({
                url: @json($specUrl),
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [SwaggerUIBundle.plugins.DownloadUrl],
                layout: 'BaseLayout',
                tryItOutEnabled: true,
                persistAuthorization: true
            });
        };
    </script>
</body>
</html>
