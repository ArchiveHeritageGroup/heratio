{{--
 | Heratio ahg-scan - archived non-HTML snapshot metadata page.
 |
 | Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems.
 | Licensed under the GNU AGPL v3.
 |
 | Served standalone (no theme layout) because the replay surface runs under a
 | strict Content-Security-Policy and must not pull in live theme assets. Self
 | contained inline styles only.
 |
 | @copyright Plain Sailing Information Systems
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Archived snapshot #{{ $capture->id }}</title>
</head>
<body style="margin:0;font:14px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#222;background:#f4f4f4;">
    <div role="alert" style="background:#5a1f1f;color:#fff;padding:10px 16px;border-bottom:2px solid #2d0f0f;">
        <strong>ARCHIVED SNAPSHOT</strong> &middot; this is a stored copy, not the live site.
    </div>
    <div style="max-width:720px;margin:24px auto;padding:0 16px;">
        <h1 style="font-size:20px;margin:0 0 16px;">Archived non-HTML resource</h1>
        <p style="margin:0 0 16px;">
            This capture is not an HTML page, so it is offered as a download rather than
            rendered inline. The bytes below are served exactly as they were archived.
        </p>
        <table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #ddd;">
            <tbody>
                <tr>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #eee;width:30%;">Source URL</th>
                    <td style="padding:8px 12px;border-bottom:1px solid #eee;word-break:break-all;">
                        {{ $targetUri ?: $capture->url }}
                    </td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #eee;">Content type</th>
                    <td style="padding:8px 12px;border-bottom:1px solid #eee;"><code>{{ $contentType }}</code></td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #eee;">Size</th>
                    <td style="padding:8px 12px;border-bottom:1px solid #eee;">
                        {{ $byteSize !== null ? number_format((int) $byteSize) . ' bytes' : 'unknown' }}
                    </td>
                </tr>
                <tr>
                    <th style="text-align:left;padding:8px 12px;">Capture</th>
                    <td style="padding:8px 12px;">#{{ $capture->id }} &middot; {{ $capture->captured_at ?? $capture->created_at }}</td>
                </tr>
            </tbody>
        </table>
        <p style="margin:20px 0;">
            <a href="{{ $rawUrl }}"
               style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;padding:10px 18px;border-radius:4px;">
                Download archived resource
            </a>
        </p>
        <p style="color:#666;font-size:13px;margin:0;">
            Single-document replay only: embedded resources and links are not replayed, and
            nothing live is fetched.
        </p>
    </div>
</body>
</html>
