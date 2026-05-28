/**
 * thumb-fallback.js - swap broken card thumbnails for the type icon.
 *
 * Listens for the 'error' event on every <img> inside a .card-img-top
 * wrapper. When the underlying file 404s (common for DAM items whose
 * derivative DB row exists but the JPG was never written to disk), hide
 * the broken <img> and surface a fallback icon block.
 *
 * Loaded as an external script so CSP nonce-strict deployments don't block
 * inline event handlers. Bound via capture-phase delegation on the document
 * so dynamically-loaded cards (AJAX paging) also get the fallback.
 *
 * Issue: heratio#763 / DAM browse UX
 */
(function () {
    'use strict';
    if (window.__ahgThumbFallbackLoaded) return;
    window.__ahgThumbFallbackLoaded = true;

    // Map glam types to Font Awesome icon names. Mirrors the typeConfig in
    // _browse_content.blade.php so the icon picked here matches the server-
    // rendered icon used when $objThumb is null at render time.
    var ICON_MAP = {
        archive:    'fa-archive',
        dam:        'fa-photo-film',
        library:    'fa-book',
        museum:     'fa-landmark',
        gallery:    'fa-palette',
        default:    'fa-image'
    };

    function pickIcon(img) {
        // Walk up to find the card wrapper, then check its badges / classes
        // for a hint about the GLAM type. Default to fa-image.
        var card = img.closest('.card');
        if (!card) return ICON_MAP.default;
        var badge = card.querySelector('.badge');
        if (badge) {
            var label = (badge.textContent || '').trim().toLowerCase();
            if (ICON_MAP[label]) return ICON_MAP[label];
        }
        return ICON_MAP.default;
    }

    function swapToIcon(img) {
        if (img.__ahgThumbFallbackApplied) return;
        img.__ahgThumbFallbackApplied = true;

        var iconName = pickIcon(img);
        var height = img.style.height || (img.height ? img.height + 'px' : '200px');

        var div = document.createElement('div');
        div.className = 'card-img-top bg-light d-flex align-items-center justify-content-center text-secondary';
        div.style.height = height;
        div.innerHTML = '<i class="fas ' + iconName + ' fa-3x"></i>';

        if (img.parentNode) {
            img.parentNode.insertBefore(div, img);
            img.style.display = 'none';
        }
    }

    // Delegate via capture phase so all img errors get caught, including
    // those rendered after initial DOMContentLoaded (AJAX-loaded result sets).
    document.addEventListener('error', function (e) {
        var t = e.target;
        if (!t || t.tagName !== 'IMG') return;
        if (!t.className || (t.className.indexOf('card-img-top') === -1
                          && t.className.indexOf('card-img-browse') === -1
                          && t.className.indexOf('grid-img') === -1)) return;
        swapToIcon(t);
    }, true);

    // Catch images that errored before this script loaded (rare but possible
    // with eager-loaded images above the fold).
    function sweep() {
        var imgs = document.querySelectorAll(
            'img.card-img-top, img.card-img-browse, img.grid-img, img.img-fluid'
        );
        for (var i = 0; i < imgs.length; i++) {
            var img = imgs[i];
            // naturalWidth === 0 with complete === true means load failed.
            if (img.complete && img.naturalWidth === 0) swapToIcon(img);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', sweep);
    } else {
        sweep();
    }
})();
