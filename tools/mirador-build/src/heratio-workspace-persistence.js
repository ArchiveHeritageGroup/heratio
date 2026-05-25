/**
 * Heratio Mirador workspace persistence (issue #699).
 *
 * Two-layer persistence on top of Mirador 4:
 *
 *   1. localStorage auto-save - subscribes to the Mirador redux store and
 *      debounces a write of store.getState() to localStorage on every change
 *      (1.5s after the last mutation). Key is per page-identifier so the
 *      record-show viewer and the standalone /iiif/viewer.html viewer don't
 *      clobber each other.
 *
 *   2. Per-user DB-backed save - calls /api/iiif/workspace endpoints with
 *      the user's Laravel session cookie. Opt-in via the workspace dropdown;
 *      anonymous users see only the localStorage layer.
 *
 * The plugin attaches a small API at window.HeratioWorkspaces so admin and
 * toolbar markup (rendered by Heratio's PHP layer, not Mirador) can call
 * load / save / list without needing to import this module.
 *
 * Mirador 4's viewer factory returns { actions, store }. We wrap the factory
 * so the persistence hooks are installed automatically on every viewer
 * created via the Heratio bundle.
 */

const LS_PREFIX = 'heratio-mirador-workspace:';
const DEBOUNCE_MS = 1500;

function lsKey(scope) {
  return LS_PREFIX + (scope || 'default');
}

function readLocal(scope) {
  try {
    const raw = window.localStorage.getItem(lsKey(scope));
    if (!raw) return null;
    return JSON.parse(raw);
  } catch (e) {
    return null;
  }
}

function writeLocal(scope, state) {
  try {
    window.localStorage.setItem(lsKey(scope), JSON.stringify(state));
  } catch (e) {
    // Quota or private-mode: silent best-effort.
  }
}

function clearLocal(scope) {
  try {
    window.localStorage.removeItem(lsKey(scope));
  } catch (e) { /* ignore */ }
}

function csrfToken() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

async function apiCall(method, url, body) {
  const opts = {
    method,
    credentials: 'same-origin',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
    },
  };
  if (body) {
    opts.headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(body);
  }
  const r = await fetch(url, opts);
  if (r.status === 401) {
    return { ok: false, status: 401, data: null };
  }
  if (!r.ok) {
    return { ok: false, status: r.status, data: null };
  }
  const data = await r.json();
  return { ok: true, status: 200, data };
}

/**
 * Subscribe to a redux store and dispatch `cb(state)` after activity quiets.
 * Returns an unsubscribe function.
 */
function debouncedSubscribe(store, cb, wait) {
  let timer = null;
  return store.subscribe(() => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      try { cb(store.getState()); } catch (e) { /* swallow */ }
    }, wait);
  });
}

/**
 * Best-effort restore: dispatch importMiradorState if the action exists in
 * this Mirador build. We hand the action a plain object so it works whether
 * Mirador expects { exportableState: ... } or the raw state shape (the
 * action creator handles both in current 4.x builds).
 */
function tryRestore(miradorInstance, state) {
  if (!state || !miradorInstance) return false;
  try {
    const { actions, store } = miradorInstance;
    if (actions && typeof actions.importMiradorState === 'function') {
      store.dispatch(actions.importMiradorState(state));
      return true;
    }
  } catch (e) { /* ignore */ }
  return false;
}

/**
 * Install the persistence hooks on a freshly-built Mirador instance.
 *
 * @param {object} miradorInstance - return value of Mirador.viewer()
 * @param {object} opts - { scope?: string, autoRestore?: boolean }
 */
function installPersistence(miradorInstance, opts) {
  if (!miradorInstance || !miradorInstance.store) return miradorInstance;
  const o = opts || {};
  const scope = o.scope || (window.location.pathname || 'default');
  const autoRestore = o.autoRestore !== false;
  const store = miradorInstance.store;

  // ----- Auto-restore on mount -----
  // Try the DB-backed default first (only triggers if the user is logged in
  // and has flagged a workspace is_default=1). Fall back to localStorage.
  if (autoRestore) {
    // Defer one tick so Mirador's initial render completes before we
    // dispatch a state-replacement action.
    setTimeout(async () => {
      try {
        const remote = await apiCall('GET', '/api/iiif/workspace');
        if (remote.ok && remote.data && Array.isArray(remote.data.data)) {
          const def = remote.data.data.find((w) => Number(w.is_default) === 1);
          if (def && def.id) {
            const full = await apiCall('GET', '/api/iiif/workspace/' + def.id);
            if (full.ok && full.data && full.data.data && full.data.data.config_json) {
              if (tryRestore(miradorInstance, full.data.data.config_json)) return;
            }
          }
        }
      } catch (e) { /* ignore - fall through to localStorage */ }

      const cached = readLocal(scope);
      if (cached) tryRestore(miradorInstance, cached);
    }, 50);
  }

  // ----- Debounced auto-save to localStorage -----
  debouncedSubscribe(store, (state) => writeLocal(scope, state), DEBOUNCE_MS);

  // ----- Expose imperative API on window -----
  // Keep a registry keyed by scope so multiple Mirador instances on the same
  // page (record show + compare) each address their own persistence target.
  if (!window.HeratioWorkspaces) {
    window.HeratioWorkspaces = {
      _instances: {},
      list: async function () {
        const r = await apiCall('GET', '/api/iiif/workspace');
        return r.ok ? (r.data && r.data.data) || [] : [];
      },
      saveLocal: function (scopeArg) {
        const inst = this._instances[scopeArg] || this._instances[Object.keys(this._instances)[0]];
        if (!inst) return false;
        writeLocal(scopeArg || inst._scope, inst.store.getState());
        return true;
      },
      saveRemote: async function (name, opts) {
        const o = opts || {};
        const inst = this._instances[o.scope] || this._instances[Object.keys(this._instances)[0]];
        if (!inst) return null;
        const r = await apiCall('POST', '/api/iiif/workspace', {
          name: name || ('Workspace ' + new Date().toISOString().slice(0, 19).replace('T', ' ')),
          config_json: inst.store.getState(),
          is_default: !!o.isDefault,
        });
        return r.ok ? r.data && r.data.data : null;
      },
      overwriteRemote: async function (id, opts) {
        const o = opts || {};
        const inst = this._instances[o.scope] || this._instances[Object.keys(this._instances)[0]];
        if (!inst) return null;
        const body = { config_json: inst.store.getState() };
        if (o.name) body.name = o.name;
        const r = await apiCall('PUT', '/api/iiif/workspace/' + id, body);
        return r.ok ? r.data && r.data.data : null;
      },
      loadRemote: async function (id, opts) {
        const o = opts || {};
        const inst = this._instances[o.scope] || this._instances[Object.keys(this._instances)[0]];
        if (!inst) return false;
        const r = await apiCall('GET', '/api/iiif/workspace/' + id);
        if (!r.ok || !r.data || !r.data.data) return false;
        return tryRestore(inst, r.data.data.config_json);
      },
      setDefault: async function (id) {
        const r = await apiCall('POST', '/api/iiif/workspace/' + id + '/load', {});
        return r.ok;
      },
      deleteRemote: async function (id) {
        const r = await apiCall('DELETE', '/api/iiif/workspace/' + id);
        return r.ok;
      },
      clearLocal: function (scopeArg) {
        const inst = this._instances[scopeArg] || this._instances[Object.keys(this._instances)[0]];
        clearLocal(scopeArg || (inst && inst._scope) || 'default');
      },
    };
  }
  // Stash the instance under its scope so the API methods can reach it.
  miradorInstance._scope = scope;
  window.HeratioWorkspaces._instances[scope] = miradorInstance;

  return miradorInstance;
}

export { installPersistence };
