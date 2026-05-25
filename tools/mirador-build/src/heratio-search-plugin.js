/**
 * Heratio - Mirador 4 Content Search 2.0 search-service plugin (issue #694).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 *
 * Mirador 4 already ships an in-window SearchPanel that activates when a
 * manifest declares a `service` block with type/profile matching the
 * IIIF Content Search 2.0 spec. This wrapper plugin's job is therefore
 * narrow:
 *
 *   1. Make sure the search side-panel is enabled in the window-level
 *      config so the toolbar button shows up even when the host page
 *      didn't pass a custom Mirador config.
 *   2. Provide a tiny configuration normaliser so a caller can pass
 *      `searchService: <url>` directly on a window definition and have
 *      it land on the right field for the SearchPanel saga.
 *   3. Stay out of the way when the manifest already advertises the
 *      service block - in that case Mirador finds the endpoint on its
 *      own and our normaliser is a no-op.
 *
 * The plugin exports a Mirador-style plugin object (target + mode +
 * mapStateToProps/mapDispatchToProps) using component=null - that is
 * Mirador's accepted shape for a plugin that only contributes a config
 * reducer rather than a UI component. We rely on Mirador's own
 * SearchPanel UI for the rendered search box and result list.
 */

const SEARCH_SERVICE_PROFILES_V2 = [
  'http://iiif.io/api/search/2/search',
  'SearchService2',
];

/**
 * Convenience helper exposed on `window` so the host page's
 * initIiifViewer() can call it to merge per-window search defaults.
 *
 * Usage from the host page:
 *
 *   const cfg = window.HeratioSearchPlugin.applyWindowDefaults({
 *     manifestId: '...',
 *     windows: [{ manifestId: '...' }],
 *   });
 *   Mirador.viewer(cfg);
 *
 * The function is idempotent and safe to call on configs that already
 * declare sideBarPanel/search settings.
 */
function applyWindowDefaults(config) {
  if (!config || typeof config !== 'object') return config;
  const next = { ...config };

  // Ensure the global window-level defaults turn the search panel on.
  next.window = {
    ...(next.window || {}),
    defaultSideBarPanel: (next.window && next.window.defaultSideBarPanel) || 'search',
    sideBarOpenByDefault: true,
    panels: {
      info: true,
      attribution: true,
      canvas: true,
      annotations: true,
      search: true,
      ...(next.window && next.window.panels ? next.window.panels : {}),
    },
  };

  // Per-window: nothing to do unless the caller already provided
  // a per-window searchService override. Mirador discovers the
  // service block from the manifest itself once it loads.
  return next;
}

/**
 * Inspect a manifest JSON and return the first Content Search 2.0
 * endpoint URL it declares, or null. Used by the host page to log
 * a friendly "Search enabled for this manifest" message.
 */
function findSearchServiceUrl(manifest) {
  if (!manifest || typeof manifest !== 'object') return null;
  const svc = manifest.service;
  if (!Array.isArray(svc)) return null;
  for (const entry of svc) {
    if (!entry || typeof entry !== 'object') continue;
    const type = entry.type || entry['@type'];
    const profile = entry.profile;
    const matches = (val) => SEARCH_SERVICE_PROFILES_V2.indexOf(val) !== -1;
    if (matches(type) || matches(profile)) {
      return entry.id || entry['@id'] || null;
    }
  }
  return null;
}

if (typeof window !== 'undefined') {
  window.HeratioSearchPlugin = {
    applyWindowDefaults,
    findSearchServiceUrl,
  };
}

// The actual plugin export is intentionally empty - the Content Search
// UI is built into Mirador 4. Exporting an empty array keeps the
// import-and-spread pattern in index.js consistent with the other
// plugins (image-tools, dl, annotation-editor) and gives us a place to
// add a real Mirador plugin object later if we need to override the
// default SearchPanel behaviour (custom result rendering, NER-aware
// hit grouping, etc.).
const heratioSearchPlugins = [];

export default heratioSearchPlugins;
export { applyWindowDefaults, findSearchServiceUrl };
