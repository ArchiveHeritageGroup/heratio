/**
 * usage-tracker.js - per-event COUNTER R5 instrumentation.
 *
 * Listens for:
 *   - page view on library-item show pages (one beacon per pageshow)
 *   - clicks on download / link-out anchors with data-library-item-id
 *   - clicks on anchors inside .ahg-media-player wrappers tied to a library-item
 *
 * Posts to /api/library/usage-event with {library_item_id, event}.
 *
 * Auto-detects the current library_item_id from a meta tag the host page
 * supplies: <meta name="library-item-id" content="123">. Pages without this
 * meta tag silently no-op.
 *
 * Issue: heratio#766. Auto-injected by AhgLibrary InjectUsageTracker middleware.
 */
(function () {
    'use strict';
    if (window.__ahgUsageTrackerLoaded) return;
    window.__ahgUsageTrackerLoaded = true;

    var meta = document.querySelector('meta[name="library-item-id"]');
    var libraryItemId = meta ? parseInt(meta.getAttribute('content'), 10) : 0;
    if (!libraryItemId || isNaN(libraryItemId)) return;

    var endpoint = '/api/library/usage-event';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function beacon(event) {
        var payload = JSON.stringify({library_item_id: libraryItemId, event: event});
        // Prefer sendBeacon - survives page unload + does not delay navigation.
        if (navigator.sendBeacon) {
            try {
                var blob = new Blob([payload], {type: 'application/json'});
                navigator.sendBeacon(endpoint, blob);
                return;
            } catch (e) { /* fall through */ }
        }
        // Fallback: fire-and-forget fetch.
        try {
            fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload,
                keepalive: true
            }).catch(function () {});
        } catch (e) { /* swallow */ }
    }

    // 1. Page view event - fires once on initial pageshow.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { beacon('view'); });
    } else {
        beacon('view');
    }

    // 2. Download / link-out click event - delegated to document so we catch
    // dynamically-added anchors too. Only fires once per anchor visit.
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || t.nodeType !== 1) return;
        var a = t.closest && t.closest('a');
        if (!a) return;
        // Heuristic: track if anchor has download attribute, or its href is
        // outside our origin (link-out), or it's a media file extension.
        var isDownload = a.hasAttribute('download');
        var isExternal = a.href && a.host && a.host !== window.location.host;
        var isMedia = /\.(pdf|epub|mp3|mp4|wav|tif{1,2}|jp2|jpe?g|png)(\?|$)/i.test(a.href || '');
        if (!isDownload && !isExternal && !isMedia) return;

        // Per-anchor de-dupe: same anchor only beacons once per pageshow.
        if (a.__ahgUsageBeaconed) return;
        a.__ahgUsageBeaconed = true;

        beacon('link_click');
    }, true);
})();
