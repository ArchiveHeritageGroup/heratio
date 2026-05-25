@inject('branding', \App\Services\TenantEmailBranding::class)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? $branding->tenantName() }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; padding: 0; background: #fff; }
        .header { background: {{ $branding->primaryColor() }}; padding: 20px; text-align: center; }
        .header img { max-height: 48px; max-width: 240px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; font-weight: 600; }
        .body { padding: 24px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: {{ $branding->primaryColor() }}; color: #fff !important; text-decoration: none; border-radius: 4px; }
        .muted { color: {{ $branding->secondaryColor() }}; }
        .footer { padding: 16px 24px; font-size: 12px; color: #999; background: #fafafa; text-align: center; border-top: 1px solid #eee; }
        a { color: {{ $branding->primaryColor() }}; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            @if ($branding->logoUrl())
                <img src="{{ $branding->logoUrl() }}" alt="{{ $branding->tenantName() }}">
            @else
                <h1>{{ $branding->tenantName() }}</h1>
            @endif
        </div>
        <div class="body">
            @yield('content')
            {{ $slot ?? '' }}
        </div>
        <div class="footer">
            {!! $branding->footerHtml() !!}
        </div>
    </div>
</body>
</html>
