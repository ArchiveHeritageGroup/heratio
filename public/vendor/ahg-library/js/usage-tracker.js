/**
 * usage-tracker.js - per-event COUNTER R5 instrumentation.
 *
 * Listens for:
 *   - page view on library-item show pages (one beacon per pageshow)
 *   - clicks on download / link-out anchors with data-library-item-id
 *   - clicks on anchors inside .ahg-media-player wrappers tied to a library-item
 *   - OPAC search submissions (PR1 "successful searches") - heratio#1096
 *
 * Posts to /api/library/usage-event with {library_item_id, event}.
 *
 * Auto-detects the current library_item_id from a meta tag the host page
 * supplies: <meta name="library-item-id" content="123">. Search capture is
 * enabled by <meta name="library-usage-search" content="1"> on the OPAC page.
 * Pages with neither meta tag silently no-op.
 *
 * Issue: heratio#766, heratio#1096. Auto-injected by AhgLibrary
 * InjectUsageTracker middleware.
 */
(function () {
    'use strict';
    if (window.__ahgUsageTrackerLoaded) return;
    window.__ahgUsageTrackerLoaded = true;

    var meta = document.querySelector('meta[name="library-item-id"]');
    var libraryItemId = meta ? parseInt(meta.getAttribute('content'), 10) : 0;
    if (isNaN(libraryItemId)) libraryItemId = 0;

    var searchMeta = document.querySelector('meta[name="library-usage-search"]');
    var searchEnabled = !!searchMeta;

    // Nothing to instrument on this page.
    if (!libraryItemId && !searchEnabled) return;

    var endpoint = '/api/library/usage-event';
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function beacon(event, opts) {
        var body = {event: event};
        if (libraryItemId) body.library_item_id = libraryItemId;
        if (opts && typeof opts === 'object') {
            for (var k in opts) { if (opts.hasOwnProperty(k)) body[k] = opts[k]; }
        }
        var payload = JSON.stringify(body);
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

    // 1. Page view event - fires once on initial pageshow (item pages only).
    if (libraryItemId) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { beacon('view'); });
        } else {
            beacon('view');
        }
    }

    // 2. Download / link-out click event - delegated to document so we catch
    // dynamically-added anchors too. Only fires once per anchor visit. Item only.
    if (libraryItemId) {
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
    }

    // 3. OPAC search event (COUNTER PR1 "successful searches") - heratio#1096.
    // Fires on submit of the OPAC search form, only when a non-empty query is
    // present, so empty/whitespace submits do not inflate the search count.
    if (searchEnabled) {
        var bind = function () {
            var form = document.getElementById('opac-search-form');
            if (!form || form.__ahgSearchBound) return;
            form.__ahgSearchBound = true;
            form.addEventListener('submit', function () {
                var q = form.querySelector('input[name="q"]');
                var term = q ? (q.value || '').trim() : '';
                if (term === '') return; // empty search - do not count
                beacon('search');
            });
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bind);
        } else {
            bind();
        }
    }
})();
