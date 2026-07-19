{{--
  marketing::layout - standalone, self-contained marketing page layout.
  Deliberately does NOT extend the app theme: these SEO/sales pages own their
  full <head> (title, meta description, canonical, JSON-LD) and ship their own
  minimal responsive CSS with no external CDNs.

  @license AGPL-3.0-or-later
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Heratio')</title>
    <meta name="description" content="@yield('meta_description', 'Heratio - open-source GLAM and archival management platform.')">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
    @endif
    <meta name="robots" content="index,follow">
    <meta property="og:type" content="website">
    <meta property="og:title" content="@yield('title', 'Heratio')">
    <meta property="og:description" content="@yield('meta_description', 'Heratio - open-source GLAM and archival management platform.')">
    @yield('head_extra')
    <style>
        :root {
            --ink: #1a2230;
            --muted: #5a6472;
            --line: #e3e7ee;
            --bg: #ffffff;
            --soft: #f6f8fb;
            --brand: #1f6feb;
            --brand-dark: #1650b0;
            --max: 900px;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--ink);
            background: var(--bg);
            line-height: 1.6;
            font-size: 17px;
        }
        .wrap { max-width: var(--max); margin: 0 auto; padding: 0 20px; }
        header.site {
            border-bottom: 1px solid var(--line);
            background: var(--bg);
        }
        header.site .wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            padding-bottom: 16px;
        }
        .brand {
            font-weight: 700;
            font-size: 20px;
            letter-spacing: -0.01em;
            color: var(--ink);
            text-decoration: none;
        }
        header.site nav a {
            color: var(--muted);
            text-decoration: none;
            margin-left: 18px;
            font-size: 15px;
        }
        header.site nav a:hover { color: var(--brand); }
        main { padding: 32px 0 8px; }
        h1 { font-size: 2.1rem; line-height: 1.2; letter-spacing: -0.02em; margin: 0 0 0.5em; }
        h2 { font-size: 1.5rem; margin: 1.8em 0 0.5em; letter-spacing: -0.01em; }
        h3 { font-size: 1.15rem; margin: 1.4em 0 0.3em; }
        p { margin: 0 0 1em; }
        a { color: var(--brand); }
        ul, ol { margin: 0 0 1em; padding-left: 1.3em; }
        li { margin: 0.3em 0; }
        .lede { font-size: 1.15rem; color: var(--muted); }
        blockquote.callout {
            margin: 1.5em 0;
            padding: 18px 20px;
            background: var(--soft);
            border-left: 4px solid var(--brand);
            border-radius: 6px;
        }
        blockquote.callout p:last-child { margin-bottom: 0; }
        .table-scroll { overflow-x: auto; margin: 1em 0 1.5em; }
        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 560px;
            font-size: 15px;
        }
        th, td {
            border: 1px solid var(--line);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        thead th { background: var(--soft); font-weight: 600; }
        tbody tr:nth-child(even) td { background: #fbfcfe; }
        .btn {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            padding: 12px 22px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover { background: var(--brand-dark); }
        .btn.secondary { background: transparent; color: var(--brand); border: 1px solid var(--brand); }
        .btn.secondary:hover { background: var(--soft); }
        .cta-block {
            margin: 2em 0;
            padding: 24px;
            background: var(--soft);
            border: 1px solid var(--line);
            border-radius: 10px;
        }
        .cta-block h2 { margin-top: 0; }
        form.lead label { display: block; font-weight: 600; margin: 1em 0 0.3em; font-size: 15px; }
        form.lead .hint { font-weight: 400; color: var(--muted); font-size: 13px; }
        form.lead input[type=text],
        form.lead input[type=email],
        form.lead input[type=url],
        form.lead textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
            font: inherit;
            color: var(--ink);
            background: #fff;
        }
        form.lead textarea { min-height: 120px; resize: vertical; }
        form.lead .hp { position: absolute; left: -9999px; top: -9999px; height: 0; width: 0; overflow: hidden; }
        .flash-success {
            margin: 1.2em 0;
            padding: 14px 18px;
            background: #e8f6ec;
            border: 1px solid #b7e0c2;
            color: #1c6b34;
            border-radius: 8px;
        }
        .errors {
            margin: 1.2em 0;
            padding: 14px 18px;
            background: #fdeceb;
            border: 1px solid #f3c3bf;
            color: #9b2c22;
            border-radius: 8px;
        }
        .errors ul { margin: 0; }
        .form-actions { margin-top: 1.5em; }
        footer.site {
            border-top: 1px solid var(--line);
            margin-top: 48px;
            padding: 28px 0;
            color: var(--muted);
            font-size: 14px;
        }
        footer.site a { color: var(--muted); }
        @media (max-width: 640px) {
            body { font-size: 16px; }
            h1 { font-size: 1.7rem; }
            header.site .wrap { flex-direction: column; align-items: flex-start; gap: 8px; }
            header.site nav a { margin-left: 0; margin-right: 16px; }
        }
    </style>
</head>
<body>
    <header class="site">
        <div class="wrap">
            <a class="brand" href="/">Heratio</a>
            <nav>
                <a href="/compare/atom">Compare</a>
                <a href="/migration/assessment">Migrate from AtoM</a>
                <a href="/help">Help</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="wrap">
            @yield('content')
        </div>
    </main>

    <footer class="site">
        <div class="wrap">
            <p>Heratio is developed by The Archive and Heritage Digital Commons Group (Pty) Ltd (The AHG).
            Open source under AGPL-3.0. <a href="https://github.com/ArchiveHeritageGroup/heratio">Source on GitHub</a>.</p>
        </div>
    </footer>
</body>
</html>
