{{--
  Registry-specific layout (cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/layout_registry.php)

  Self-contained HTML doc — does NOT extend theme::layouts.1col. Gives the
  registry section its own navbar, color palette (warm tan/cream + teal),
  notification bell, footer. Per CLAUDE.md "no AtoM references in user-facing
  text" the brand reads "Heratio Registry"; the visual palette stays.

  Notifications wire to ahg_notification (the canonical NotificationService
  table) — replaces AtoM's registry_notification.

  Slots: @yield('title'), @yield('content'), @stack('head'), @stack('scripts').

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $isLoggedIn = auth()->check();
    $user = $isLoggedIn ? auth()->user() : null;
    // group_id 99 = authenticated, 100 = administrator. We want admins only.
    $isAdmin = $isLoggedIn && DB::table('acl_user_group')
        ->where('user_id', $user->id ?? 0)
        ->where('group_id', 100)
        ->exists();

    // Notifications for the bell + top-bar
    $_notifUnread = 0;
    $_notifBar = null;
    if ($isLoggedIn && Schema::hasTable('ahg_notification')) {
        $_notifUnread = (int) DB::table('ahg_notification')
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->where('is_dismissed', 0)
            ->count();
        $_notifBar = DB::table('ahg_notification')
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->where('is_dismissed', 0)
            ->orderByDesc('created_at')
            ->first();
    }

    // Nav-section visibility from registry_settings (defaults: all visible)
    $_navSettings = [];
    if (Schema::hasTable('registry_settings')) {
        try {
            foreach (DB::table('registry_settings')->where('setting_key', 'like', 'nav_show_%')->get() as $r) {
                $_navSettings[$r->setting_key] = ! empty($r->setting_value) && '0' !== $r->setting_value;
            }
        } catch (\Throwable $e) {}
    }
    $_showCommunity   = $_navSettings['nav_show_community']   ?? true;
    $_showStandards   = $_navSettings['nav_show_standards']   ?? true;
    $_showUserGroups  = $_navSettings['nav_show_user_groups'] ?? true;
    $_showBlog        = $_navSettings['nav_show_blog']        ?? true;
    $_showNewsletters = $_navSettings['nav_show_newsletters'] ?? true;
    $_showMap         = $_navSettings['nav_show_map']         ?? true;
    $_showSearch      = $_navSettings['nav_show_search']      ?? true;
    $_hasMoreItems    = $_showUserGroups || $_showBlog || $_showNewsletters || $_showMap || $_showSearch;

    // Footer settings (description, copyright, columns) — fall back to defaults
    $_footerDesc = __('The global community hub for institutions, vendors, and archival software. Connect, collaborate, and discover.');
    $_footerCopyright = '&copy; ' . date('Y') . ' The Archive and Heritage Group (Pty) Ltd.';
    $_footerColumns = [
        ['title' => __('Directory'), 'links' => [
            ['label' => __('Institutions'), 'url' => '/registry/institutions'],
            ['label' => __('Vendors'), 'url' => '/registry/vendors'],
            ['label' => __('Software'), 'url' => '/registry/software'],
            ['label' => __('Map'), 'url' => '/registry/map'],
        ]],
        ['title' => __('Community'), 'links' => [
            ['label' => __('User Groups'), 'url' => '/registry/groups'],
            ['label' => __('Blog'), 'url' => '/registry/blog'],
            ['label' => __('Newsletters'), 'url' => '/registry/newsletters'],
            ['label' => __('Community Hub'), 'url' => '/registry/community'],
        ]],
        ['title' => __('Get Started'), 'links' => [
            ['label' => __('Create Account'), 'url' => '/registry/register'],
            ['label' => __('Register Institution'), 'url' => '/registry/institution/register'],
            ['label' => __('Register as Vendor'), 'url' => '/registry/vendor/register'],
        ]],
        ['title' => __('About'), 'links' => [
            ['label' => __('The AHG'), 'url' => 'https://theahg.co.za'],
            ['label' => __('GitHub'), 'url' => 'https://github.com/ArchiveHeritageGroup/heratio'],
            ['label' => __('API'), 'url' => '/registry/api/directory'],
        ]],
    ];
    if (Schema::hasTable('registry_settings')) {
        try {
            $r = DB::table('registry_settings')->where('setting_key', 'footer_description')->first();
            if ($r && '' !== trim((string) $r->setting_value)) $_footerDesc = $r->setting_value;
            $r = DB::table('registry_settings')->where('setting_key', 'footer_copyright')->first();
            if ($r && '' !== trim((string) $r->setting_value)) {
                $_footerCopyright = str_replace('{year}', date('Y'), html_entity_decode($r->setting_value, ENT_QUOTES, 'UTF-8'));
            }
            $r = DB::table('registry_settings')->where('setting_key', 'footer_columns')->first();
            if ($r && '' !== trim((string) $r->setting_value)) {
                $decoded = json_decode($r->setting_value, true);
                if (is_array($decoded) && count($decoded) > 0) $_footerColumns = $decoded;
            }
        } catch (\Throwable $e) {}
    }

    $nonce = function_exists('csp_nonce') ? (csp_nonce() ?? '') : '';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('Heratio Registry'))</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@300;400;600;700&display=swap" rel="stylesheet" @if ($nonce) nonce="{{ $nonce }}" @endif>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" @if ($nonce) nonce="{{ $nonce }}" @endif>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" @if ($nonce) nonce="{{ $nonce }}" @endif>

    <style @if ($nonce) nonce="{{ $nonce }}" @endif>
        :root {
            --atm-primary: #225b7b;
            --atm-primary-dark: #174a65;
            --atm-primary-light: #2d7da8;
            --atm-accent: #d4a843;
            --atm-bg: #f5f3ef;
            --atm-card-bg: #ffffff;
            --atm-text: #333333;
            --atm-text-muted: #6c757d;
            --atm-navbar-bg: #225b7b;
            --atm-footer-bg: #1a2332;
            --atm-border: #e0dcd5;
        }
        body {
            background: var(--atm-bg);
            color: var(--atm-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-size: 0.95rem;
        }
        h1, h2, h3, h4, h5, h6 { font-weight: 600; color: #2c3e50; }

        .reg-navbar { background: var(--atm-navbar-bg) !important; box-shadow: 0 2px 8px rgba(0,0,0,0.15); padding: 0.5rem 0; min-height: 64px; }
        .reg-navbar .navbar-brand { color: #fff !important; padding: 0; gap: 0.55rem; }
        .reg-navbar .navbar-brand .brand-icon {
            font-size: 1.45rem; color: var(--atm-accent);
        }
        .reg-navbar .navbar-brand .brand-mark {
            font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em;
            font-family: 'Source Sans 3', system-ui, sans-serif;
        }
        .reg-navbar .navbar-brand .brand-sep {
            font-size: 1.35rem; font-weight: 300; opacity: 0.45; padding: 0 0.05rem;
        }
        .reg-navbar .navbar-brand .brand-text {
            font-size: 1.1rem; font-weight: 500; opacity: 0.85; letter-spacing: 0.01em;
        }
        .reg-navbar .nav-link { color: rgba(255,255,255,0.85) !important; font-weight: 500; font-size: 0.88rem; padding: 0.4rem 0.65rem !important; border-radius: 4px; transition: background 0.15s, color 0.15s; }
        .reg-navbar .nav-link:hover, .reg-navbar .nav-link.active { background: rgba(255,255,255,0.12); color: #fff !important; }
        .reg-navbar .nav-link i { margin-right: 3px; }
        .reg-navbar .dropdown-menu { border: 1px solid var(--atm-border); box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 6px; }

        .card { border: 1px solid var(--atm-border); border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
        .card-header { background: #faf9f7; border-bottom: 1px solid var(--atm-border); font-weight: 600; font-size: 0.9rem; }

        /* Logo containment: registry cards/lists never let an institution/vendor/software
           logo balloon past 48px regardless of inline overrides or natural image dimensions. */
        .card .card-body > .d-flex img,
        .list-group-item img.rounded,
        .reg-main img.rounded {
            max-width: 48px !important;
            max-height: 48px !important;
            object-fit: contain;
        }

        .btn-primary { background: var(--atm-primary) !important; border-color: var(--atm-primary) !important; }
        .btn-primary:hover { background: var(--atm-primary-dark) !important; border-color: var(--atm-primary-dark) !important; }
        .btn-outline-primary { color: var(--atm-primary) !important; border-color: var(--atm-primary) !important; }
        .btn-outline-primary:hover { background: var(--atm-primary) !important; color: #fff !important; }
        .text-primary { color: var(--atm-primary) !important; }
        .bg-primary { background-color: var(--atm-primary) !important; }
        .border-primary { border-color: var(--atm-primary) !important; }

        .badge { font-weight: 500; font-size: 0.78rem; letter-spacing: 0.02em; }

        .reg-footer { background: var(--atm-footer-bg); color: #94a3b8; padding: 2rem 0; margin-top: auto; }
        .reg-footer h6 { color: #e2e8f0 !important; font-size: 0.85rem; }
        .reg-footer a { color: #93c5fd; text-decoration: none; }
        .reg-footer a:hover { color: #fff; }
        .reg-footer .small { font-size: 0.82rem; }

        .reg-main { flex: 1; padding-top: 1.25rem; padding-bottom: 2rem; }

        .breadcrumb { font-size: 0.82rem; }
        .breadcrumb-item a { color: var(--atm-primary); }

        .table { font-size: 0.9rem; }
        .table thead { font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.04em; }

        .form-control:focus, .form-select:focus { border-color: var(--atm-primary-light); box-shadow: 0 0 0 0.2rem rgba(34,91,123,0.15); }

        .hero-banner { background: linear-gradient(135deg, var(--atm-primary) 0%, var(--atm-primary-dark) 100%); border-radius: 8px; }

        a { color: var(--atm-primary); }
        a:hover { color: var(--atm-primary-dark); }

        .page-link { color: var(--atm-primary); }
        .page-item.active .page-link { background: var(--atm-primary); border-color: var(--atm-primary); }

        .reg-notif-bell .nav-link i { margin-right: 0; font-size: 1rem; }
        .reg-notif-badge { position: absolute; top: 0; right: 0; transform: translate(25%, -10%); font-size: 0.62rem; padding: 0.18em 0.4em; border-radius: 999px; line-height: 1; }
        .reg-notif-menu { width: 360px; max-width: 95vw; padding: 0; }
        .reg-notif-list { max-height: 400px; overflow-y: auto; }
        .reg-notif-item { display: block; padding: 0.6rem 0.9rem; border-bottom: 1px solid #f0eeea; color: #333 !important; text-decoration: none !important; transition: background 0.1s; }
        .reg-notif-item:hover { background: #f8f9fa; }
        .reg-notif-item.unread { background: #f5fbff; border-left: 3px solid var(--atm-primary); }
        .reg-notif-item .reg-notif-title { font-weight: 600; font-size: 0.85rem; line-height: 1.3; }
        .reg-notif-item .reg-notif-msg { font-size: 0.78rem; color: #6c757d; margin-top: 2px; line-height: 1.3; }
        .reg-notif-item .reg-notif-time { font-size: 0.72rem; color: #999; margin-top: 2px; }
        .reg-notif-empty { text-align: center; padding: 2rem 1rem; color: #999; font-size: 0.85rem; }

        .reg-notif-bar { background: linear-gradient(135deg, #fff8e1 0%, #fff3cd 100%); border-bottom: 1px solid #f0d97a; padding: 0.55rem 0; font-size: 0.88rem; color: #715a16; }
        .reg-notif-bar a { color: #715a16; text-decoration: underline; font-weight: 600; }
        .reg-notif-bar a:hover { color: #4a3a0a; }
        .reg-notif-bar .reg-notif-bar-close { background: transparent; border: 0; color: #715a16; opacity: 0.7; font-size: 1rem; padding: 0 0.5rem; cursor: pointer; }
        .reg-notif-bar .reg-notif-bar-close:hover { opacity: 1; }

        .atom-btn-white { background: #fff; color: var(--atm-primary); border: 1px solid #fff; }
        .atom-btn-white:hover { background: rgba(255,255,255,0.9); }
    </style>
    @stack('head')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark reg-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="/registry/">
            <i class="fas fa-landmark brand-icon"></i>
            <span class="brand-mark">Heratio</span>
            <span class="brand-sep">/</span>
            <span class="brand-text">{{ __('Registry') }}</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#regNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="regNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="/registry/institutions"><i class="fas fa-university"></i> {{ __('Institutions') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="/registry/vendors"><i class="fas fa-building"></i> {{ __('Vendors') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="/registry/software"><i class="fas fa-cube"></i> {{ __('Software') }}</a></li>
                @if ($_showStandards)
                    <li class="nav-item"><a class="nav-link" href="/registry/standards"><i class="fas fa-balance-scale"></i> {{ __('Standards') }}</a></li>
                @endif
                @if ($_showCommunity)
                    <li class="nav-item"><a class="nav-link" href="/registry/community"><i class="fas fa-users"></i> {{ __('Community') }}</a></li>
                @endif
                @if ($_hasMoreItems)
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-ellipsis-h"></i> {{ __('More') }}
                        </a>
                        <ul class="dropdown-menu">
                            @if ($_showUserGroups)<li><a class="dropdown-item" href="/registry/groups"><i class="fas fa-user-friends me-2"></i>{{ __('User Groups') }}</a></li>@endif
                            @if ($_showBlog)<li><a class="dropdown-item" href="/registry/blog"><i class="fas fa-rss me-2"></i>{{ __('Blog') }}</a></li>@endif
                            @if ($_showNewsletters)<li><a class="dropdown-item" href="/registry/newsletters"><i class="fas fa-envelope me-2"></i>{{ __('Newsletters') }}</a></li>@endif
                            @if ($_showMap)<li><a class="dropdown-item" href="/registry/map"><i class="fas fa-map me-2"></i>{{ __('Map') }}</a></li>@endif
                            @if ($_showSearch)<li><a class="dropdown-item" href="/registry/search"><i class="fas fa-search me-2"></i>{{ __('Search') }}</a></li>@endif
                        </ul>
                    </li>
                @endif
            </ul>

            <form class="d-flex me-3" method="get" action="/registry/search">
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" name="q" placeholder="{{ __('Search...') }}" style="max-width: 180px;">
                    <button class="btn btn-outline-light" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <ul class="navbar-nav">
                @if ($isLoggedIn)
                    <li class="nav-item dropdown reg-notif-bell">
                        <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifications') }}">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger reg-notif-badge @if ($_notifUnread === 0) d-none @endif">
                                {{ $_notifUnread > 99 ? '99+' : $_notifUnread }}
                            </span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end reg-notif-menu">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                                <strong>{{ __('Notifications') }}</strong>
                                <a href="/registry/notifications" class="small">{{ __('View all') }}</a>
                            </div>
                            <div class="reg-notif-list">
                                @php
                                    $_notifList = $isLoggedIn && Schema::hasTable('ahg_notification')
                                        ? DB::table('ahg_notification')
                                            ->where('user_id', $user->id)
                                            ->where('is_dismissed', 0)
                                            ->orderByDesc('created_at')->limit(10)->get()
                                        : collect();
                                @endphp
                                @forelse ($_notifList as $n)
                                    <a href="{{ $n->link ?? '/registry/notifications' }}" class="reg-notif-item @if (! $n->is_read) unread @endif">
                                        <div class="reg-notif-title">{{ $n->title }}</div>
                                        @if ($n->message)
                                            <div class="reg-notif-msg">{{ \Illuminate\Support\Str::limit($n->message, 100) }}</div>
                                        @endif
                                        <div class="reg-notif-time">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</div>
                                    </a>
                                @empty
                                    <div class="reg-notif-empty">{{ __('No notifications') }}</div>
                                @endforelse
                            </div>
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {{ $user->username ?? __('Account') }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item d-flex justify-content-between align-items-center" href="/registry/notifications">
                                    <span><i class="fas fa-bell me-2"></i>{{ __('Notifications') }}</span>
                                    @if ($_notifUnread > 0)
                                        <span class="badge bg-danger">{{ $_notifUnread > 99 ? '99+' : $_notifUnread }}</span>
                                    @endif
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/registry/my-favorites"><i class="fas fa-star me-2"></i>{{ __('My Favorites') }}</a></li>
                            <li><a class="dropdown-item" href="/registry/my-institution"><i class="fas fa-university me-2"></i>{{ __('My Institution') }}</a></li>
                            <li><a class="dropdown-item" href="/registry/my-vendor"><i class="fas fa-building me-2"></i>{{ __('My Vendor') }}</a></li>
                            <li><a class="dropdown-item" href="/registry/my-groups"><i class="fas fa-user-friends me-2"></i>{{ __('My Groups') }}</a></li>
                            @if ($isAdmin)
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/registry/my-blog"><i class="fas fa-rss me-2"></i>{{ __('Blog Posts') }}</a></li>
                                <li><a class="dropdown-item" href="/registry/admin"><i class="fas fa-cog me-2"></i>{{ __('Admin') }}</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt me-2"></i>{{ __('Logout') }}</a></li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item"><a class="nav-link" href="/login"><i class="fas fa-sign-in-alt"></i> {{ __('Login') }}</a></li>
                    <li class="nav-item"><a class="btn btn-sm btn-outline-light ms-2 mt-1" href="/registry/register">{{ __('Register') }}</a></li>
                @endif
            </ul>
        </div>
    </div>
</nav>

@if ($isLoggedIn && $_notifBar)
<div class="reg-notif-bar" data-id="{{ $_notifBar->id }}">
    <div class="container d-flex align-items-center">
        <i class="fas fa-bell me-2"></i>
        <div class="flex-grow-1">
            <strong>{{ $_notifBar->title }}</strong>
            @if (! empty($_notifBar->message))
                <span class="ms-2">{{ \Illuminate\Support\Str::limit($_notifBar->message, 200) }}</span>
            @endif
            @if (! empty($_notifBar->link))
                <a href="{{ $_notifBar->link }}" class="ms-2">{{ __('View') }}</a>
            @endif
        </div>
        <button type="button" class="reg-notif-bar-close" aria-label="{{ __('Dismiss') }}">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>
@endif

<main class="reg-main">
    <div class="container">
        @yield('content')
    </div>
</main>

<footer class="reg-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-3 mb-md-0">
                <h6 class="mb-2"><i class="fas fa-landmark me-1"></i> {{ __('Heratio Registry') }}</h6>
                <p class="small mb-0">{{ $_footerDesc }}</p>
            </div>
            @foreach ($_footerColumns as $i => $col)
                <div class="col-md-2 @if ($i < count($_footerColumns) - 1) mb-3 mb-md-0 @endif">
                    <h6 class="mb-2">{{ $col['title'] ?? '' }}</h6>
                    @if (! empty($col['links']) && is_array($col['links']))
                        <ul class="list-unstyled small">
                            @foreach ($col['links'] as $link)
                                @php
                                    $url = $link['url'] ?? '#';
                                    $isExternal = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
                                @endphp
                                <li><a href="{{ $url }}" @if ($isExternal) target="_blank" @endif>{{ $link['label'] ?? '' }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
        <hr class="border-secondary mt-3 mb-2">
        <p class="text-center small mb-0">{!! $_footerCopyright !!}</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" @if ($nonce) nonce="{{ $nonce }}" @endif></script>
@stack('scripts')

</body>
</html>
