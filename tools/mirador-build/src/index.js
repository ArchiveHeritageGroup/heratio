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

  async all() {
    try {
      const r = await fetch(`/api/annotations/search?targetId=${encodeURIComponent(this.canvasId)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
      });
      if (!r.ok) return null;
      const data = await r.json();
      return {
        id: this.annotationPageId,
        type: 'AnnotationPage',
        items: Array.isArray(data.resources) ? data.resources : [],
      };
    } catch (e) {
      console.warn('[HeratioAnnotationAdapter] all() failed:', e);
      return null;
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
    return await r.json();
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
    return await r.json();
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
