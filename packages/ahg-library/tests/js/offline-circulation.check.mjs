// Browser check for the offline desk. Run from the repo root:
//   node packages/ahg-library/tests/js/offline-circulation.check.mjs
// Uses the real page markup and scripts against an in-memory stand-in for the
// server on a private port. It writes nothing outside the temporary browser profile.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import http from 'node:http';
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';

const SCOPE = '/library-manage/circulation/offline';
const partial = execFileSync('php', ['artisan', 'tinker', '--execute',
    "echo view('ahg-library::circulation.offline._desk', ['userId' => 7, 'branchId' => null])->render();"], { encoding: 'utf8' });
const markup = partial.slice(partial.indexOf('<div id="od-root"'));
assert.ok(markup.length > 100, 'desk partial did not render');

const js = (name) => readFileSync(`packages/ahg-library/resources/js/${name}`);
const state = { received: [], sessionStatus: 200, seen: new Set() };
const copies = [
    { id: 1, barcode: 'C1', status: 'available', material_type: 'monograph', loan_card: null, loan_due: null, branch_id: null, title: 'One' },
    { id: 2, barcode: 'C2', status: 'checked_out', material_type: 'monograph', loan_card: 'P2', loan_due: '2026-12-01', branch_id: null, title: 'Two' },
];
const patrons = [
    { id: 1, card_number: 'P1', display_name: 'A. One', patron_type: 'public', borrowing_status: 'active', max_checkouts: 5, over_fine_threshold: false, active_loans: 0 },
    { id: 2, card_number: 'P2', display_name: 'B. Two', patron_type: 'public', borrowing_status: 'suspended', max_checkouts: 5, over_fine_threshold: false, active_loans: 1 },
];

const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    const json = (body, status = 200) => { res.writeHead(status, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(body)); };
    const script = (body, headers = {}) => { res.writeHead(200, { 'Content-Type': 'text/javascript', ...headers }); res.end(body); };

    if (url.pathname === SCOPE) {
        res.writeHead(200, { 'Content-Type': 'text/html' });
        return res.end(`<!doctype html><title>Desk</title>${markup}<script src="${SCOPE}/app.js" defer></script>`);
    }
    if (url.pathname === `${SCOPE}/app.js`) return script(js('offline-circulation.js'));
    if (url.pathname === `${SCOPE}/sw.js`) return script(js('offline-circulation-sw.js'), { 'Service-Worker-Allowed': SCOPE });
    if (url.pathname === `${SCOPE}/session`) {
        return state.sessionStatus === 200 ? json({ user_id: 7, token: 'tok' }) : json({}, state.sessionStatus);
    }
    if (url.pathname === `${SCOPE}/package`) {
        const kind = url.searchParams.get('kind');
        if (kind === 'rules') {
            return json({ snapshot_id: 1, branch_id: null, default_loan_days: { 0: 14 }, rules: [{ branch_id: 0, material_type: 'monograph', patron_type: '*', loan_period_days: 10, is_loanable: 1 }] });
        }
        return json({ rows: kind === 'copies' ? copies : patrons, next: null });
    }
    if (url.pathname === `${SCOPE}/sync` && req.method === 'POST') {
        let raw = '';
        req.on('data', (d) => { raw += d; });
        return req.on('end', () => {
            assert.equal(req.headers['x-csrf-token'], 'tok');
            const results = JSON.parse(raw).transactions.map((t) => {
                if (!state.seen.has(t.uuid)) state.received.push(t);
                state.seen.add(t.uuid);
                return { uuid: t.uuid, status: t.copy_barcode === 'C2' ? 'conflict' : 'applied', conflict_code: null, due_date: null };
            });
            json({ results });
        });
    }
    res.writeHead(404);
    res.end();
});

await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const origin = `http://localhost:${server.address().port}`;
const browser = await chromium.launch();
const failures = [];

try {
    const context = await browser.newContext();
    const page = await context.newPage();
    page.on('pageerror', (e) => { failures.push(e.message); console.error('pageerror', e.message); });
    page.on('console', (m) => { if (m.type() === 'error') console.error('console', m.text()); });
    page.on('dialog', (d) => d.accept());
    const count = () => page.locator('#od-count').textContent();
    const scan = async (action, copy, patron) => {
        await page.check(`#od-action-${action}`);
        if (patron) await page.fill('#od-patron', patron);
        await page.fill('#od-copy', copy);
        await page.click('#od-form button[type=submit]');
    };

    await page.goto(origin + SCOPE);
    await page.evaluate(() => navigator.serviceWorker.ready);
    await page.waitForFunction(() => navigator.serviceWorker.controller !== null);
    await page.waitForTimeout(300);
    await page.click('#od-refresh');
    await page.waitForFunction(() => document.getElementById('od-cache').textContent.includes('2 copies'));

    await page.fill('#od-pin-new', 'desk-pin-1');
    await page.click('#od-pin-save');
    await page.waitForFunction(() => document.getElementById('od-pin-state').textContent.includes('is set'));

    const unlock = async (pin) => {
        await page.fill('#od-pin', pin);
        await page.click('#od-unlock button[type=submit]');
    };

    // The page reopens with no network, from the worker, locked.
    await context.setOffline(true);
    await page.reload();
    await page.waitForSelector('#od-root');
    await page.waitForSelector('#od-unlock', { state: 'visible' });
    assert.match(await page.locator('#od-net').textContent(), /Offline/);
    assert.match(await page.locator('#od-cache').textContent(), /2 copies/);
    assert.equal(await page.locator('#od-form').isVisible(), false, 'capture is closed until unlocked');

    await unlock('wrong-pin');
    await page.waitForFunction(() => document.getElementById('od-message').textContent.includes('not correct'));
    assert.equal(await page.locator('#od-form').isVisible(), false, 'a wrong PIN does not open capture');
    await unlock('desk-pin-1');
    await page.waitForSelector('#od-form', { state: 'visible' });

    await scan('checkout', 'C1', 'P1');
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '1');
    assert.match(await page.locator('#od-message').textContent(), /due date: \d{4}-\d{2}-\d{2}/);
    const expected = new Date(Date.now() + 10 * 86400000).toISOString().slice(0, 10);
    assert.ok((await page.locator('#od-message').textContent()).includes(expected), 'indicative due date uses the cached rule');

    await scan('return', 'C1');
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '2');
    await scan('return', 'C2');
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '3');

    // The queue survives a reload while offline.
    await page.reload();
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '3');
    assert.equal(state.received.length, 0);
    await page.waitForSelector('#od-unlock', { state: 'visible' });
    assert.equal(await page.locator('#od-queue tr').count(), 0, 'waiting entries are hidden while locked');
    await unlock('desk-pin-1');
    await page.waitForFunction(() => document.querySelectorAll('#od-queue tr').length === 3);

    // An expired session keeps the queue.
    state.sessionStatus = 401;
    await context.setOffline(false);
    await page.waitForFunction(() => document.getElementById('od-message').textContent.includes('Sign in'));
    assert.equal(await count(), '3');
    assert.equal(state.received.length, 0);

    // Signed in again: everything goes, in capture order, carrying the capture details.
    state.sessionStatus = 200;
    await page.click('#od-sync');
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '0');
    assert.deepEqual(state.received.map((t) => t.seq), [1, 2, 3]);
    assert.deepEqual(state.received.map((t) => t.action), ['checkout', 'return', 'return']);
    assert.equal(state.received[1].expected_patron_card, 'P1');
    assert.equal(state.received[2].expected_patron_card, 'P2');
    assert.ok(state.received.every((t) => t.operator_id === 7), 'each entry names its operator');
    assert.match(await page.locator('#od-message').textContent(), /2 applied, 1 sent/);

    // Patron data is not in the worker's cache.
    const cached = await page.evaluate(async () => {
        const out = [];
        for (const key of await caches.keys()) for (const r of await (await caches.open(key)).keys()) out.push(new URL(r.url).pathname);
        return out;
    });
    assert.ok(cached.includes(SCOPE), 'page is cached');
    assert.ok(!cached.some((p) => /package|session|sync/.test(p)), 'no data endpoints cached');

    await page.click('#od-clear');
    await page.waitForFunction(() => document.getElementById('od-cache').textContent.includes('No desk data'));

    // Signing out keeps a waiting queue but drops the page and, once empty, the desk data.
    const local = () => page.evaluate(async () => {
        const d = await new Promise((ok, no) => { const o = indexedDB.open('heratio-offline-circ-7', 1); o.onsuccess = () => ok(o.result); o.onerror = () => no(o.error); });
        const size = (s) => new Promise((ok) => { const r = d.transaction(s).objectStore(s).count(); r.onsuccess = () => ok(r.result); });
        const out = { queue: await size('queue'), copies: await size('copies'), shell: (await caches.keys()).filter((k) => k.startsWith('heratio-offline-desk-')).length };
        d.close();
        return out;
    });
    const signOut = async () => {
        await page.evaluate(() => {
            const f = document.createElement('form');
            f.method = 'get';
            f.action = '/logout';
            f.innerHTML = '<button id="t-out">out</button>';
            document.body.appendChild(f);
        });
        await Promise.all([page.waitForURL(/\/logout/), page.click('#t-out')]);
    };
    await page.click('#od-refresh');
    await page.waitForFunction(() => document.getElementById('od-cache').textContent.includes('2 copies'));
    state.sessionStatus = 401;
    await page.reload();
    await page.waitForSelector('#od-unlock', { state: 'visible' });
    await unlock('desk-pin-1');
    await page.waitForSelector('#od-form', { state: 'visible' });
    await scan('checkout', 'C1', 'P1');
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '1');
    await signOut();
    assert.deepEqual(await local(), { queue: 1, copies: 2, shell: 0 }, 'a waiting queue survives sign-out');

    state.sessionStatus = 200;
    await page.goto(origin + SCOPE);
    await page.waitForFunction(() => document.getElementById('od-count').textContent === '0');
    await signOut();
    assert.deepEqual(await local(), { queue: 0, copies: 0, shell: 0 }, 'sign-out clears the desk data');

    assert.deepEqual(failures, []);
    console.log('offline desk browser check passed');
} finally {
    await browser.close();
    server.close();
}
