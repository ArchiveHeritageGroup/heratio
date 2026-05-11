<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<title>Share link unavailable</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; margin: 0; background: #f7f7f8; color: #212529; }
  .sl-card { max-width: 540px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: .375rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); text-align: center; }
  .sl-card .icon { font-size: 3rem; color: #dc3545; margin-bottom: 1rem; }
  .sl-card h1 { font-size: 1.5rem; margin: 0 0 1rem; }
  .sl-card p { color: #6c757d; line-height: 1.5; }
  .sl-status { display: inline-block; padding: .25rem .5rem; background: #f8d7da; color: #721c24; border-radius: .25rem; font-family: monospace; font-size: .85rem; margin-top: 1rem; }
</style>
</head>
<body>
<div class="sl-card">
    <div class="icon">⚠</div>
    <h1>Share link unavailable</h1>
    <p>{{ $reason ?? 'This share link is no longer valid.' }}</p>
    <div class="sl-status">HTTP 410 · {{ $result->action }}</div>
</div>
</body>
</html>
