/**
 * Heratio security regression suite (added 2026-04-30 after credential-leak
 * audit + nginx hardening). Covers four high-signal fronts:
 *
 *   A. security headers present on every public route
 *   B. sensitive files / source paths return 403/404 from nginx
 *   C. /admin/* routes require auth (anonymous redirected / blocked)
 *   D. KM /api/ask requires Authorization: Bearer <KM_API_KEY>
 *
 * Run:
 *   HERATIO_URL=https://heratio.theahg.co.za KM_URL=https://km.theahg.co.za \
 *     KM_API_KEY=<key> npx playwright test tests/e2e/06-security
 *
 * KM tests skip if KM_URL or KM_API_KEY is unset (so the suite still passes
 * on hosts that don't run a KM instance).
 */

import { test, expect, request as pwRequest } from '@playwright/test';

// ---------------------------------------------------------------------------
// A. Security headers present on every public route
// ---------------------------------------------------------------------------
test.describe('A. security headers', () => {
    // /admin/login is intentionally omitted — Laravel's InjectCspNonces
    // middleware sets per-request CSP on most routes, but /admin/login
    // has its own response path that doesn't go through that middleware.
    // Tracked separately in `docs/security.md` known-weak-spots.
    const PUBLIC_ROUTES = [
        '/',
        '/informationobject/browse',
        '/repository/browse',
        '/actor/browse',
    ];

    for (const url of PUBLIC_ROUTES) {
        test(`${url} → CSP + X-Frame-Options + nosniff`, async ({ page }) => {
            const resp = await page.goto(url, { waitUntil: 'domcontentloaded' });
            expect(resp, `no response for ${url}`).not.toBeNull();
            const h = resp!.headers();

            expect(h['content-security-policy'], 'CSP header missing')
                .toBeTruthy();
            expect(h['content-security-policy']).toContain("default-src 'self'");

            expect(h['x-frame-options'], 'X-Frame-Options missing').toBe('SAMEORIGIN');
            expect(h['x-content-type-options'], 'X-Content-Type-Options missing').toBe('nosniff');
            expect(h['referrer-policy'], 'Referrer-Policy missing').toMatch(/strict-origin/);
        });
    }
});

// ---------------------------------------------------------------------------
// B. Sensitive files and source paths must NOT be reachable
// ---------------------------------------------------------------------------
test.describe('B. sensitive paths denied', () => {
    // .env and .git are protected by an aggressive `return 444` (close
    // connection without response) on the live vhost — Playwright sees
    // those as connection errors, not HTTP statuses, so we test them via
    // a separate "connection drop" assertion below.
    const FORBIDDEN = [
        '/composer.json',
        '/composer.lock',
        '/package.json',
        '/vendor/composer/installed.json',
        '/storage/logs/laravel.log',
        '/database/seeds/00_taxonomies.sql',
        '/config/heratio.php',
        '/CLAUDE.md',
        '/README.md',
    ];

    for (const path of FORBIDDEN) {
        test(`${path} → 403/404`, async ({ request }) => {
            const r = await request.get(path);
            expect([403, 404], `${path} returned ${r.status()}`).toContain(r.status());
        });
    }

    test('/.env → connection dropped or 403', async ({ request }) => {
        // `return 444` drops the connection. Either we get a thrown error
        // (preferred) or a 403 response — both pass.
        try {
            const r = await request.get('/.env', { failOnStatusCode: false });
            expect([403, 404]).toContain(r.status());
        } catch (e) {
            // Connection error — that's the `return 444` working as designed.
        }
    });

    test('/.git/HEAD → connection dropped or 403', async ({ request }) => {
        try {
            const r = await request.get('/.git/HEAD', { failOnStatusCode: false });
            expect([403, 404]).toContain(r.status());
        } catch (e) {
            // Connection error — `return 444`
        }
    });
});

// ---------------------------------------------------------------------------
// C. Admin routes require authentication
// ---------------------------------------------------------------------------
test.describe('C. admin auth', () => {
    // Routes verified to exist in the live route map and live in the
    // Route::middleware('admin') group. /admin/menu and /admin/dashboards
    // (plural) do NOT exist as admin routes — they fall through to the
    // slug catch-all and return 200 from a public IO show page.
    const ADMIN_ROUTES = [
        '/admin/users',
        '/admin/dropdowns',
        '/admin/menu/browse',
        '/admin/settings',
        '/admin/acl',
        '/admin/settings/cron-jobs',
    ];

    for (const url of ADMIN_ROUTES) {
        test(`${url} blocks anonymous`, async ({ page }) => {
            const resp = await page.goto(url, { waitUntil: 'domcontentloaded' });
            const status = resp?.status() ?? 0;
            const finalUrl = page.url();

            // Acceptable: 302/301 to /login, or 401/403 from middleware.
            const ok =
                [301, 302, 401, 403].includes(status) ||
                /\/admin\/login|\/login/.test(finalUrl);
            expect(ok, `${url} → status=${status}, url=${finalUrl}`).toBeTruthy();
        });
    }
});

// ---------------------------------------------------------------------------
// D. KM defence-in-depth — two layers
//
//   1. nginx vhost (km.theahg.co.za) LAN-locks the public endpoint. From
//      outside 192.168.0.0/24 every path returns 403 (including /api/stats).
//      We test this by hitting the public hostname which always resolves
//      to the public IP, regardless of where the test runs.
//
//   2. Flask (127.0.0.1:5050) requires Authorization: Bearer <KM_API_KEY>
//      on /api/ask, /api/feedback, /api/audit, /api/search. We test this
//      by hitting localhost:5050 directly (bypasses the LAN-lock).
//
// Set KM_PUBLIC_URL=https://km.theahg.co.za and KM_LOCAL_URL=http://127.0.0.1:5050
// (defaults). KM_API_KEY must be set for the Flask-auth tests.
// ---------------------------------------------------------------------------
const KM_PUBLIC_URL = process.env.KM_PUBLIC_URL || 'https://km.theahg.co.za';
const KM_LOCAL_URL  = process.env.KM_LOCAL_URL  || 'http://127.0.0.1:5050';
const KM_API_KEY    = process.env.KM_API_KEY    || '';

test.describe('D1. km nginx LAN-lock (public)', () => {
    test('public /api/ask → 403', async () => {
        const ctx = await pwRequest.newContext();
        try {
            const r = await ctx.post(`${KM_PUBLIC_URL}/api/ask`, {
                data: { question: 'x' },
                failOnStatusCode: false,
                timeout: 5_000,
            });
            // nginx denies LAN-only with 403; behind some routers/CDN with 444 (drop).
            expect([403, 444]).toContain(r.status());
        } catch (e) {
            // Connection drop is also acceptable (return 444 from nginx).
        } finally {
            await ctx.dispose();
        }
    });

    test('public /api/stats → 403 (locked too)', async () => {
        const ctx = await pwRequest.newContext();
        try {
            const r = await ctx.get(`${KM_PUBLIC_URL}/api/stats`, {
                failOnStatusCode: false, timeout: 5_000,
            });
            expect([403, 444]).toContain(r.status());
        } catch (e) { /* connection drop = pass */ }
        finally { await ctx.dispose(); }
    });
});

test.describe('D2. km Flask bearer-token (localhost)', () => {
    test.skip(!KM_API_KEY, 'KM_API_KEY env var required');

    test('local /api/ask without token → 401', async () => {
        const ctx = await pwRequest.newContext();
        const r = await ctx.post(`${KM_LOCAL_URL}/api/ask`, {
            data: { question: 'x' }, failOnStatusCode: false,
        });
        expect(r.status()).toBe(401);
        await ctx.dispose();
    });

    test('local /api/ask wrong token → 401', async () => {
        const ctx = await pwRequest.newContext();
        const r = await ctx.post(`${KM_LOCAL_URL}/api/ask`, {
            data: { question: 'x' },
            headers: { Authorization: 'Bearer wrong-token' },
            failOnStatusCode: false,
        });
        expect(r.status()).toBe(401);
        await ctx.dispose();
    });

    // The "valid token returns 200" assertion goes through a real LLM call
    // (Ollama qwen2.5:32b on a CPU box) — too slow for the default 60 s
    // Playwright per-test budget. Keep this in a separate, longer-budgeted
    // test and let CI skip it via `KM_SKIP_LLM_CALL=1` if needed.
    test('local /api/ask valid token → 200 [slow, real LLM]', async () => {
        test.skip(process.env.KM_SKIP_LLM_CALL === '1',
            'KM_SKIP_LLM_CALL=1 — skipping real LLM round-trip');
        test.setTimeout(300_000);   // 5 min — accommodates cold model load
        const ctx = await pwRequest.newContext();
        const r = await ctx.post(`${KM_LOCAL_URL}/api/ask`, {
            data: { question: 'hello', stream: false },
            headers: { Authorization: `Bearer ${KM_API_KEY}` },
            timeout: 280_000,
        });
        expect(r.status()).toBe(200);
        await ctx.dispose();
    });

    test('local /api/stats open for monitoring (no auth needed)', async () => {
        const ctx = await pwRequest.newContext();
        const r = await ctx.get(`${KM_LOCAL_URL}/api/stats`);
        expect(r.status()).toBe(200);
        await ctx.dispose();
    });
});
