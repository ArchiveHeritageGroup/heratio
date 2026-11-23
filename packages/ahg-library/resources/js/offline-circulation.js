/* Offline circulation desk (#1474 item 4): a queue in IndexedDB, replayed in order on reconnect. */
(function () {
    'use strict';

    const root = document.getElementById('od-root');
    if (!root) return;

    const t = JSON.parse(root.dataset.strings);
    // Path only: the desk must call the origin it was opened from, whatever host the server thinks it has.
    const base = new URL(root.dataset.baseUrl, location.href).pathname;
    const userId = Number(root.dataset.userId);
    const $ = (id) => document.getElementById(id);
    const say = (text, kind) => {
        const el = $('od-message');
        el.textContent = text || '';
        el.className = 'small mt-2' + (kind ? ' text-' + kind : '');
    };
    const fill = (text, values) => Object.entries(values).reduce((s, [k, v]) => s.replace(':' + k, v), text);

    // The cached page proves nothing about who is at the computer. Capture opens
    // only after the server confirms this user, or with the PIN they set online.
    let unlocked = false;
    let idleTimer;
    const IDLE_MS = 600000;

    // Storage: one database per user, so a shared desk computer never mixes queues.

    let dbPromise;
    function db() {
        dbPromise = dbPromise || new Promise((resolve, reject) => {
            const open = indexedDB.open('heratio-offline-circ-' + userId, 1);
            open.onupgradeneeded = () => {
                const d = open.result;
                d.createObjectStore('meta');
                d.createObjectStore('copies', { keyPath: 'barcode' });
                d.createObjectStore('patrons', { keyPath: 'card_number' });
                d.createObjectStore('queue', { keyPath: 'uuid' }).createIndex('seq', 'seq', { unique: true });
            };
            open.onsuccess = () => resolve(open.result);
            open.onerror = () => reject(open.error);
        });
        return dbPromise;
    }

    async function tx(stores, mode, work) {
        const d = await db();
        return new Promise((resolve, reject) => {
            const trans = d.transaction(stores, mode);
            let result;
            trans.oncomplete = () => resolve(result);
            trans.onerror = () => reject(trans.error);
            trans.onabort = () => reject(trans.error);
            Promise.resolve(work(trans)).then((value) => { result = value; }, reject);
        });
    }

    const req = (r) => new Promise((resolve, reject) => { r.onsuccess = () => resolve(r.result); r.onerror = () => reject(r.error); });
    const getMeta = (key) => tx(['meta'], 'readonly', (x) => req(x.objectStore('meta').get(key)));
    const queueAll = () => tx(['queue'], 'readonly', (x) => req(x.objectStore('queue').index('seq').getAll()));

    async function deviceId() {
        let id = await getMeta('device_id');
        if (!id) {
            id = crypto.randomUUID();
            await tx(['meta'], 'readwrite', (x) => { x.objectStore('meta').put(id, 'device_id'); });
        }
        return id;
    }

    // Local unlock

    async function pinHash(pin, salt) {
        const key = await crypto.subtle.importKey('raw', new TextEncoder().encode(pin), 'PBKDF2', false, ['deriveBits']);
        const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations: 210000 }, key, 256);
        return Array.from(new Uint8Array(bits)).join(',');
    }

    function touch() {
        clearTimeout(idleTimer);
        if (unlocked) idleTimer = setTimeout(lock, IDLE_MS);
    }

    async function lock() {
        if (!(await getMeta('pin'))) { say(t.pinNeeded, 'danger'); return; }
        unlocked = false;
        say(t.lockedPrompt);
        await render();
    }

    async function unlock(pin) {
        const stored = await getMeta('pin');
        if (!stored || (await pinHash(pin, stored.salt)) !== stored.hash) { say(t.pinWrong, 'danger'); return; }
        unlocked = true;
        touch();
        say('');
        await render();
    }

    function savePin(pin) {
        return exclusive(async () => {
            if (pin.length < 6) { say(t.pinShort, 'danger'); return; }
            try {
                await session();
                const salt = crypto.getRandomValues(new Uint8Array(16));
                const hash = await pinHash(pin, salt);
                await tx(['meta'], 'readwrite', (x) => { x.objectStore('meta').put({ salt, hash }, 'pin'); });
                $('od-pin-new').value = '';
                say(t.pinSaved, 'success');
            } catch (error) {
                if (error.auth || error.wrongUser) syncProblem(error);
                else say(t.needOnline, 'danger');
            }
        });
    }

    /** On sign-out: the page and desk data go; a waiting queue never does. */
    async function forgetLocal() {
        unlocked = false;
        try {
            for (const key of await caches.keys()) {
                if (key.startsWith('heratio-offline-desk-')) await caches.delete(key);
            }
            if (!(await queueAll()).length) {
                await tx(['meta', 'copies', 'patrons'], 'readwrite', (x) => {
                    x.objectStore('copies').clear();
                    x.objectStore('patrons').clear();
                    x.objectStore('meta').delete('rules');
                    x.objectStore('meta').delete('cache');
                });
            }
        } catch (error) { /* signing out must still go ahead */ }
    }

    // Capture

    function indicativeDue(rules, defaults, copy, patron, branchId, when) {
        const exact = branchId > 0 ? branchId : null;
        const matches = rules.filter((r) => r.material_type === (copy.material_type || 'monograph')
            && (r.patron_type === patron.patron_type || r.patron_type === '*')
            && Number(r.is_loanable) === 1
            && (Number(r.branch_id) === 0 || Number(r.branch_id) === exact));
        matches.sort((a, b) => (Number(b.branch_id) === exact) - (Number(a.branch_id) === exact)
            || (b.patron_type === patron.patron_type) - (a.patron_type === patron.patron_type));
        const days = matches.length ? Number(matches[0].loan_period_days) : Number(defaults[String(exact || 0)] ?? defaults['0'] ?? 14);
        const due = new Date(when.getTime());
        due.setUTCDate(due.getUTCDate() + days);
        return due.toISOString().slice(0, 10);
    }

    async function capture(action, copyBarcode, patronCard) {
        const [copy, patron, pack] = await Promise.all([
            tx(['copies'], 'readonly', (x) => req(x.objectStore('copies').get(copyBarcode))),
            patronCard ? tx(['patrons'], 'readonly', (x) => req(x.objectStore('patrons').get(patronCard))) : null,
            getMeta('rules'),
        ]);

        const warnings = [];
        if (!copy) warnings.push(t.unknownCopy);
        if (action === 'checkout') {
            if (!patron) warnings.push(t.unknownPatron);
            if (copy && copy.status !== 'available') warnings.push(t.copyOut);
            if (patron && (patron.borrowing_status !== 'active' || patron.over_fine_threshold)) warnings.push(t.patronBlocked);
            if (patron && patron.active_loans >= patron.max_checkouts) warnings.push(t.atLimit);
        } else if (copy && !copy.loan_card) {
            warnings.push(t.noLoan);
        }
        if (warnings.length && !window.confirm(warnings.join('\n') + '\n\n' + t.confirm)) return null;

        const when = new Date();
        const device = await deviceId();
        const branchRaw = root.dataset.branchId;
        const entry = {
            uuid: crypto.randomUUID(),
            device_id: device,
            operator_id: userId,
            action,
            copy_barcode: copyBarcode,
            patron_card: action === 'checkout' ? patronCard : null,
            expected_patron_card: action === 'return' && copy ? (copy.loan_card || null) : null,
            branch_id: branchRaw ? Number(branchRaw) : null,
            occurred_at: when.toISOString(),
        };

        // The sequence number, the queue entry and the local view change together or not at all.
        await tx(['meta', 'queue', 'copies', 'patrons'], 'readwrite', async (x) => {
            const meta = x.objectStore('meta');
            const seq = ((await req(meta.get('seq'))) || 0) + 1;
            meta.put(seq, 'seq');
            entry.seq = seq;
            x.objectStore('queue').add(entry);
            if (copy) {
                const out = action === 'checkout';
                x.objectStore('copies').put({ ...copy, status: out ? 'checked_out' : 'available', loan_card: out ? patronCard : null });
                const card = out ? patronCard : copy.loan_card;
                const holder = card ? await req(x.objectStore('patrons').get(card)) : null;
                if (holder) x.objectStore('patrons').put({ ...holder, active_loans: Math.max(0, holder.active_loans + (out ? 1 : -1)) });
            }
        });

        if (action === 'checkout' && copy && patron && pack) {
            return fill(t.due, { date: indicativeDue(pack.rules, pack.default_loan_days, copy, patron, Number(pack.branch_id || copy.branch_id || 0), when) });
        }
        return t.queued;
    }

    // Network

    async function fetchJson(url, options) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            redirect: 'manual',
            cache: 'no-store',
            ...options,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options && options.headers) },
        });
        if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 403) {
            const error = new Error('auth');
            error.auth = true;
            throw error;
        }
        if (!response.ok) {
            const error = new Error('http ' + response.status);
            error.status = response.status;
            throw error;
        }
        return response.json();
    }

    async function session() {
        let s;
        try {
            s = await fetchJson(base + '/session');
        } catch (error) {
            if (error.auth) unlocked = false;
            throw error;
        }
        if (Number(s.user_id) !== userId) {
            unlocked = false;
            const error = new Error('user');
            error.wrongUser = true;
            throw error;
        }
        unlocked = true;
        touch();
        return s.token;
    }

    let busy = false;

    async function exclusive(work) {
        if (busy) return;
        busy = true;
        try {
            if (navigator.locks) {
                await navigator.locks.request('heratio-offline-circ-' + userId, { ifAvailable: true }, (lock) => (lock ? work() : null));
            } else {
                await work();
            }
        } finally {
            busy = false;
            await render();
        }
    }

    function syncProblem(error) {
        if (error.auth) say(t.signIn, 'danger');
        else if (error.wrongUser) say(t.wrongUser, 'danger');
        else say(t.syncFailed, 'danger');
    }

    function sync() {
        return exclusive(async () => {
            let pending = await queueAll();
            if (!pending.length) return;
            if (!navigator.onLine) { say(t.needOnline, 'danger'); return; }

            say(t.syncing);
            let applied = 0;
            let conflicts = 0;
            try {
                let token = await session();
                while (pending.length) {
                    const batch = pending.slice(0, 100);
                    const post = () => fetchJson(base + '/sync', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ desk_clock: new Date().toISOString(), transactions: batch }),
                    });
                    let body;
                    try {
                        body = await post();
                    } catch (error) {
                        if (error.status !== 419) throw error;
                        token = await session();
                        body = await post();
                    }

                    const refused = body.results.filter((r) => !['applied', 'conflict', 'rejected'].includes(r.status));
                    const settled = body.results.filter((r) => !refused.includes(r));
                    await tx(['queue'], 'readwrite', (x) => { settled.forEach((r) => x.objectStore('queue').delete(r.uuid)); });
                    applied += settled.filter((r) => r.status === 'applied').length;
                    conflicts += settled.length - settled.filter((r) => r.status === 'applied').length;

                    if (refused.length) { say(t.refused, 'danger'); return; }
                    if (!settled.length) { say(t.syncFailed, 'danger'); return; }
                    pending = await queueAll();
                }
                say(fill(t.synced, { applied, conflicts }), conflicts ? 'warning' : 'success');
            } catch (error) {
                syncProblem(error);
            }
        });
    }

    function refresh() {
        return exclusive(async () => {
            if (!navigator.onLine) { say(t.needOnline, 'danger'); return; }
            if ((await queueAll()).length) { say(t.needEmpty, 'danger'); return; }

            say(t.downloading);
            try {
                await session();
                const rules = await fetchJson(base + '/package?kind=rules');
                const pull = async (kind) => {
                    const all = [];
                    let after = 0;
                    for (;;) {
                        const page = await fetchJson(base + '/package?kind=' + kind + '&after=' + after);
                        all.push(...page.rows);
                        if (page.next === null) return all;
                        after = page.next;
                    }
                };
                const copies = await pull('copies');
                const patrons = await pull('patrons');

                // Replaced in one step, so a failed download leaves the old data whole.
                await tx(['meta', 'copies', 'patrons'], 'readwrite', (x) => {
                    x.objectStore('copies').clear();
                    x.objectStore('patrons').clear();
                    copies.forEach((row) => x.objectStore('copies').put(row));
                    patrons.forEach((row) => x.objectStore('patrons').put(row));
                    x.objectStore('meta').put(rules, 'rules');
                    x.objectStore('meta').put({ at: Date.now(), copies: copies.length, patrons: patrons.length }, 'cache');
                });
                say(t.downloaded, 'success');
            } catch (error) {
                syncProblem(error);
            }
        });
    }

    function clearData() {
        return exclusive(async () => {
            if (!unlocked) { say(t.lockedPrompt, 'danger'); return; }
            if ((await queueAll()).length) { say(t.needEmpty, 'danger'); return; }
            if (!window.confirm(t.clearConfirm)) return;
            await tx(['meta', 'copies', 'patrons'], 'readwrite', (x) => {
                x.objectStore('copies').clear();
                x.objectStore('patrons').clear();
                x.objectStore('meta').delete('rules');
                x.objectStore('meta').delete('cache');
            });
            say(t.cleared, 'success');
        });
    }

    // Screen

    async function render() {
        const online = navigator.onLine;
        const net = $('od-net');
        net.textContent = online ? t.online : t.offline;
        net.className = 'badge ' + (online ? 'bg-success' : 'bg-warning text-dark');

        const cache = await getMeta('cache');
        if (cache) {
            const old = Date.now() - cache.at > 86400000;
            $('od-cache').textContent = fill(t.cacheInfo, { when: new Date(cache.at).toLocaleString(), copies: cache.copies, patrons: cache.patrons })
                + (old ? ' ' + t.cacheOld : '');
        } else {
            $('od-cache').textContent = t.noCache;
        }

        const hasPin = Boolean(await getMeta('pin'));
        $('od-form').hidden = !unlocked;
        $('od-pin-setup').hidden = !unlocked;
        $('od-lock').hidden = !unlocked || !hasPin;
        $('od-locked').hidden = unlocked;
        $('od-unlock').hidden = !hasPin;
        $('od-locked-text').textContent = hasPin ? t.lockedPrompt : t.noPin;
        $('od-pin-state').textContent = hasPin ? t.pinIsSet : t.pinNeeded;

        const queue = await queueAll();
        $('od-count').textContent = String(queue.length);
        const body = $('od-queue');
        // Waiting entries show patron cards, so they stay hidden while locked.
        body.replaceChildren(...(unlocked ? queue : []).map((entry) => {
            const row = document.createElement('tr');
            [new Date(entry.occurred_at).toLocaleString(), t[entry.action], entry.copy_barcode, entry.patron_card || entry.expected_patron_card || ''].forEach((text) => {
                const cell = document.createElement('td');
                cell.textContent = text;
                row.appendChild(cell);
            });
            return row;
        }));
    }

    function currentAction() {
        return document.querySelector('input[name="od-action"]:checked').value;
    }

    function applyMode() {
        const isCheckout = currentAction() === 'checkout';
        $('od-patron-wrap').hidden = !isCheckout;
        $(isCheckout ? 'od-patron' : 'od-copy').focus();
    }

    document.querySelectorAll('input[name="od-action"]').forEach((el) => el.addEventListener('change', applyMode));
    $('od-refresh').addEventListener('click', refresh);
    $('od-sync').addEventListener('click', sync);
    $('od-clear').addEventListener('click', clearData);
    $('od-lock').addEventListener('click', lock);
    $('od-pin-save').addEventListener('click', () => savePin($('od-pin-new').value));
    $('od-unlock').addEventListener('submit', async (event) => {
        event.preventDefault();
        const pin = $('od-pin').value;
        $('od-pin').value = '';
        await unlock(pin);
    });
    ['pointerdown', 'keydown'].forEach((name) => document.addEventListener(name, touch, { passive: true }));

    // Sign-out from this page, by the theme's form or a single sign-on link.
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!/\/logout(\?|$)/.test(form.action || '')) return;
        event.preventDefault();
        await forgetLocal();
        form.submit();
    }, true);
    document.addEventListener('click', async (event) => {
        const link = event.target.closest && event.target.closest('a[href*="logout"]');
        if (!link) return;
        event.preventDefault();
        await forgetLocal();
        location.href = link.href;
    }, true);

    window.addEventListener('online', () => { render(); sync(); });
    window.addEventListener('offline', render);

    $('od-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!unlocked) { say(t.lockedPrompt, 'danger'); return; }
        if (!navigator.onLine && !(await getMeta('pin'))) { say(t.noPin, 'danger'); return; }
        const action = currentAction();
        const copy = $('od-copy').value.trim();
        const patron = $('od-patron').value.trim();
        if (action === 'checkout' && !patron) { say(t.needPatron, 'danger'); $('od-patron').focus(); return; }
        if (!copy) { say(t.needCopy, 'danger'); $('od-copy').focus(); return; }

        try {
            const message = await capture(action, copy, action === 'checkout' ? patron : null);
            if (message === null) return;
            say(message, 'success');
            $('od-copy').value = '';
            $('od-copy').focus();
            await render();
            if (navigator.onLine) sync();
        } catch (error) {
            say(t.syncFailed, 'danger');
        }
    });

    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register(base + '/sw.js', { scope: base }).then(() => navigator.serviceWorker.ready).then((reg) => {
            const urls = [location.pathname].concat(performance.getEntriesByType('resource').map((e) => e.name));
            if (reg.active) reg.active.postMessage({ type: 'precache', urls });
        }).catch(() => { /* the queue still works while this page stays open */ });
    }

    (navigator.onLine ? session().catch(() => null) : Promise.resolve(null))
        .then(render)
        .then(() => { if (navigator.onLine) sync(); });
})();
