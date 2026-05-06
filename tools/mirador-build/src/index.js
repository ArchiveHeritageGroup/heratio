import Mirador from 'mirador';
import { miradorImageToolsPlugin } from 'mirador-image-tools';
import miradorDlPlugin from 'mirador-dl-plugin';
import maePlugins from 'mirador-annotation-editor';
import 'mirador-annotation-editor/dist/index.css';

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
];

const wrappedViewer = (config, extraPlugins) => Mirador.viewer(
  config,
  Array.isArray(extraPlugins) ? plugins.concat(extraPlugins) : plugins,
);

export default {
  ...Mirador,
  viewer: wrappedViewer,
  plugins,
};
