{{--
 | Heratio ahg-scan - "snapshot unavailable" replay notice.
 |
 | Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems.
 | Licensed under the GNU AGPL v3.
 |
 | Shown when a capture's WARC file is missing, outside the storage root, or
 | could not be parsed. Served standalone under the strict replay CSP; self
 | contained inline styles only. Always a clean 200, never a 500.
 |
 | @copyright Plain Sailing Information Systems
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Snapshot unavailable #{{ $capture->id ?? '' }}</title>
</head>
<body style="margin:0;font:14px/1.6 -apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#222;background:#f4f4f4;">
    <div role="alert" style="background:#5a1f1f;color:#fff;padding:10px 16px;border-bottom:2px solid #2d0f0f;">
        <strong>ARCHIVED SNAPSHOT</strong> &middot; this snapshot could not be replayed.
    </div>
    <div style="max-width:680px;margin:32px auto;padding:0 16px;">
        <h1 style="font-size:20px;margin:0 0 12px;">Snapshot unavailable</h1>
        <p style="margin:0 0 16px;">{{ $message }}</p>
        @if(isset($capture->url))
            <p style="margin:0 0 16px;color:#666;font-size:13px;word-break:break-all;">
                Original URL: {{ $capture->url }}
            </p>
        @endif
        <p style="color:#666;font-size:13px;margin:0;">
            The capture record is still available in the admin web-archive list, and the WARC
            file (if present) can be downloaded for inspection in a WARC-aware tool.
        </p>
    </div>
</body>
</html>
