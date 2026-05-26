import Mirador from 'mirador';
import { miradorImageToolsPlugin } from 'mirador-image-tools';
import miradorDlPlugin from 'mirador-dl-plugin';
import maePlugins from 'mirador-annotation-editor';
import 'mirador-annotation-editor/dist/index.css';
// Heratio plugins - issue #698. Each module exports a Mirador 4
// plugin-spec array; we concat them onto the base list further down.
import heratioScalebarPlugin from './heratio-scalebar-plugin.js';
import heratioMagnifierPlugin from './heratio-magnifier-plugin.js';
import { resolveHeratioTheme } from './heratio-mui-theme.js';
// Heratio workspace persistence (issue #699) - localStorage auto-save +
// optional DB-backed per-user workspaces. Wraps the viewer factory.
import { installPersistence as installHeratioWorkspacePersistence } from './heratio-workspace-persistence.js';
// Issue #694 - Content Search 2.0 plugin (exposes window.HeratioSearchPlugin
// helpers and reserves a spot in the plugins array for any future custom
// SearchPanel overrides). The actual search UI is provided by Mirador 4
// core, triggered by the SearchService2 service block in the manifest.
import heratioSearchPlugins from './heratio-search-plugin.js';
// ---- Heratio additions (issues #700 + #701) ----
// Comparison glass / dual-pane slider (issue #700) and A/V playback +
// transcript panel (issue #701). Each plugin lives in its own file;
// only the import + the plugins[] append below should be touched when
// either issue gets revised.
import heratioComparisonPlugin from './heratio-comparison-plugin.js';
import heratioAvPlugin from './heratio-av-plugin.js';
// ---- End Heratio additions (#700 + #701) ----

/**
 * HeratioAnnotationAdapter — Annotot-shaped storage adapter that round-
 * trips W3C Web Annotation JSON-LD to /api/annotations on the Heratio
 * backend (closes #100). Implements the interface that
 * mirador-annotation-editor (forked from mirador-annotations) expects:
 *
 *   adapter(canvasId) -> {
 *     all()             -> Promise<AnnotationPage>
 *     create(ann)       -> Promise<Annotation>
 *     update(ann)       -> Promise<Annotation>
 *     delete(annId)     -> Promise<void>
 *     annotationPageId  -> string
 *   }
 *
 * Auth: anonymous reads, session-cookie writes (Laravel session is
 * already on the page; credentials: 'same-origin' attaches it).
 * CSRF: /api/annotations* is exempt from Laravel CSRF (see bootstrap/
 * app.php) since the session-auth gate already blocks cross-site forge.
 */
class HeratioAnnotationAdapter {
  constructor(canvasId) {
    this.canvasId = canvasId;
    this.annotationPageId = `${window.location.origin}/api/annotations/page/${encodeURIComponent(canvasId)}`;
  }

  /**
   * Mirador-annotation-editor calls this on the adapter to attribute new
   * annotations (creator name in the W3C JSON-LD body). Without this
   * method MAE throws "t.getStorageAdapterUser is not a function" and
   * the save aborts before our create() is even reached. The actual
   * creator_id stored on ahg_iiif_annotation is sourced server-side
   * from Auth::id() — this string is just for the embedded creator
   * name when the annotation is rendered later.
   */
  getStorageAdapterUser() {
    // Best-effort: read the logged-in user from a meta tag if present,
    // fall back to a generic label. Heratio doesn't currently emit a
    // user-name meta in the layout, so the fallback is what shows up
    // most of the time. Updating the layout to emit <meta name="user-name">
    // is the natural follow-up to surface the real name.
    const meta = document.querySelector('meta[name="user-name"]');
    return (meta && meta.getAttribute('content')) || 'Heratio User';
  }

  async all() {
    // Always return an AnnotationPage shape — MAE downstream expects to
    // read .items.length even on empty/error states; null trips a
    // "Cannot read properties of undefined (reading 'length')" deeper
    // in the editor's saga.
    const empty = { id: this.annotationPageId, type: 'AnnotationPage', items: [] };
    try {
      const r = await fetch(`/api/annotations/search?targetId=${encodeURIComponent(this.canvasId)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });
      if (!r.ok) return empty;
      const data = await r.json();
      return {
        id: this.annotationPageId,
        type: 'AnnotationPage',
        items: Array.isArray(data.resources) ? data.resources : [],
      };
    } catch (e) {
      console.warn('[HeratioAnnotationAdapter] all() failed:', e);
      return empty;
    }
  }

  async create(annotation) {
    // Make sure the annotation's target is set to our canvas so the
    // search() endpoint can find it. Mirador's editor already does this
    // but we backstop in case the caller forgot.
    const target = (typeof annotation.target === 'object' && annotation.target)
      ? { ...annotation.target, id: annotation.target.id || this.canvasId }
      : this.canvasId;
    const body = { ...annotation, target };

    const r = await fetch('/api/annotations', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
    if (!r.ok) throw new Error('Annotation create failed: ' + r.status);
    // Return the FULL AnnotationPage (not just the newly-created
    // annotation) — MAE's saveAnnotation saga passes the result into
    // the canvas state-update handler `t(canvasId, pageId, r)`, and the
    // canvas only re-renders annotation bodies when `r` is a complete
    // AnnotationPage. Returning just the single annotation made the
    // shape persist server-side but the body text wouldn't show until
    // page reload triggered all() afresh.
    return await this.all();
  }

  async update(annotation) {
    const uuid = this._idFromUrl(annotation.id);
    if (!uuid) throw new Error('Annotation update: no id on annotation');
    const r = await fetch(`/api/annotations/${uuid}`, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(annotation),
    });
    if (!r.ok) throw new Error('Annotation update failed: ' + r.status);
    return await this.all();
  }

  async delete(annotationId) {
    const uuid = this._idFromUrl(annotationId);
    if (!uuid) throw new Error('Annotation delete: no id');
    const r = await fetch(`/api/annotations/${uuid}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    });
    if (!r.ok) throw new Error('Annotation delete failed: ' + r.status);
    return await this.all();
  }

  _idFromUrl(id) {
    if (!id || typeof id !== 'string') return null;
    // Accept either "https://host/api/annotations/<uuid>" or bare "<uuid>"
    const parts = id.split('/');
    return parts[parts.length - 1] || null;
  }
}

// Expose the adapter on the global so initIiifViewer can pass it into the
// Mirador config without re-importing from this bundle.
if (typeof window !== 'undefined') {
  window.HeratioAnnotationAdapter = HeratioAnnotationAdapter;
}

const plugins = [
  ...miradorImageToolsPlugin,
  ...miradorDlPlugin,
  ...maePlugins,
  // ---- Heratio additions (issue #698) ----
  ...heratioScalebarPlugin,
  ...heratioMagnifierPlugin,
  // ---- End Heratio additions ----
  // Issue #694 - search-plugin contribution (currently empty array; the
  // helpers are exposed on window.HeratioSearchPlugin for the host page
  // to call before Mirador.viewer()).
  ...heratioSearchPlugins,
  // ---- Heratio additions (issues #700 + #701) ----
  ...heratioComparisonPlugin,
  ...heratioAvPlugin,
  // ---- End Heratio additions (#700 + #701) ----
];

/**
 * Patch the OSD viewer's prototype so that every viewer Mirador
 * spins up registers itself against window.__heratioMiradorOsdRegistry
 * keyed by Mirador windowId. The scalebar + magnifier plugins look the
 * viewer up there because the Mirador 4 redux state shape changed
 * across minor versions and we don't want to chase the selector path.
 *
 * OSD is bundled inside Mirador; we monkey-patch via the Mirador module
 * export which re-exports the OSD constructor on Mirador.OpenSeadragon.
 */
(function patchOsdRegistry() {
  if (typeof window === 'undefined') return;
  if (!window.__heratioMiradorOsdRegistry) window.__heratioMiradorOsdRegistry = {};
  // Mirador's OpenSeadragonOSD wrapper appends a data-window-id to the
  // viewer's root element. We sniff it from the OSD element on open.
  const tryBind = (osdViewer) => {
    if (!osdViewer || !osdViewer.element) return;
    let el = osdViewer.element;
    let wid = null;
    while (el && el !== document.body) {
      if (el.dataset && el.dataset.windowId) { wid = el.dataset.windowId; break; }
      // Mirador uses data-test-id="window-<id>"
      const tid = el.getAttribute && el.getAttribute('data-test-id');
      if (tid && tid.indexOf('window-') === 0) { wid = tid.replace('window-', ''); break; }
      el = el.parentElement;
    }
    if (wid) {
      window.__heratioMiradorOsdRegistry[wid] = osdViewer;
    }
  };
  // We hook OSD viewer's 'open' once it's exposed on the page. Mirador
  // exposes the constructor lazily; poll briefly.
  let tries = 0;
  const i = setInterval(() => {
    tries++;
    if (window.OpenSeadragon && window.OpenSeadragon.Viewer && !window.OpenSeadragon.Viewer.__heratioHooked) {
      const origAddHandler = window.OpenSeadragon.Viewer.prototype.addHandler;
      const origOpen = window.OpenSeadragon.Viewer.prototype.open;
      window.OpenSeadragon.Viewer.prototype.open = function (...args) {
        const out = origOpen.apply(this, args);
        try { tryBind(this); } catch (e) { /* swallow */ }
        return out;
      };
      window.OpenSeadragon.Viewer.__heratioHooked = true;
      clearInterval(i);
    }
    if (tries > 40) clearInterval(i); // ~10s timeout
  }, 250);
})();

const wrappedViewer = (config, extraPlugins) => {
  // Inject the resolved theme (window.AHG_IIIF.theme -> Mirador theme
  // override). The caller's config.theme wins if supplied so explicit
  // per-page overrides are still possible.
  const themedConfig = Object.assign({},
    config,
    { theme: Object.assign({}, resolveHeratioTheme(), config && config.theme) }
  );
  // Stash the redux store reference on window so the scalebar plugin
  // can read the current canvas's PhysicalDimensions service block.
  // Mirador.viewer returns an object containing the store.
  const instance = Mirador.viewer(
    themedConfig,
    Array.isArray(extraPlugins) ? plugins.concat(extraPlugins) : plugins,
  );
  if (instance && instance.store && typeof window !== 'undefined') {
    window.__heratioMiradorStore = instance.store;
  }
  // Wire up workspace persistence (issue #699). Safe no-op if the
  // instance failed to build (installPersistence checks for .store).
  try {
    installHeratioWorkspacePersistence(instance, {
      scope: (config && config.id) || (window.location.pathname || 'default'),
    });
  } catch (e) {
    // Persistence is best-effort - never block viewer creation on it.
    console.warn('[HeratioWorkspacePersistence] install failed:', e);
  }
  return instance;
};

export default {
  ...Mirador,
  viewer: wrappedViewer,
  plugins,
};
