/**
 * OpenSeadragon Heratio magnifier (loupe) plugin.
 *
 * Issue #698. Adds a circular zoom loupe that follows the cursor over
 * the OSD canvas, sampling pixels from the canvas drawer's underlying
 * <canvas>. Requires OSD's "canvas" drawer (the WebGL drawer's GPU
 * surface is not readable via 2d context); ahg-iiif-viewer.js pins the
 * canvas drawer in its OSD config already.
 *
 * Usage:
 *   var v = OpenSeadragon({ ... });
 *   var loupe = v.addHeratioMagnifier({ radius: 90, zoom: 3 });
 *   loupe.enable();   // toggles on
 *   loupe.disable();  // toggles off
 */
(function () {
    'use strict';
    if (typeof OpenSeadragon === 'undefined') return;
    if (OpenSeadragon.Viewer.prototype.addHeratioMagnifier) return;

    function injectStyles() {
        if (document.getElementById('osd-heratio-loupe-styles')) return;
        var s = document.createElement('style');
        s.id = 'osd-heratio-loupe-styles';
        s.textContent =
            '.osd-heratio-loupe{position:absolute;border-radius:50%;border:2px solid #fff;box-shadow:0 0 8px rgba(0,0,0,.6);pointer-events:none;z-index:1200;background:#000;overflow:hidden;}' +
            '.osd-heratio-loupe canvas{display:block;}';
        document.head.appendChild(s);
    }

    OpenSeadragon.Viewer.prototype.addHeratioMagnifier = function (opts) {
        opts = opts || {};
        var viewer = this;
        var radius = opts.radius || 90;
        var zoom = opts.zoom || 3;
        injectStyles();

        var host = viewer.element;
        var loupe = document.createElement('div');
        loupe.className = 'osd-heratio-loupe';
        loupe.style.width = (radius * 2) + 'px';
        loupe.style.height = (radius * 2) + 'px';
        loupe.style.display = 'none';
        var cvs = document.createElement('canvas');
        cvs.width = radius * 2;
        cvs.height = radius * 2;
        loupe.appendChild(cvs);
        host.appendChild(loupe);
        var ctx = cvs.getContext('2d');
        var enabled = false;

        function sourceCanvas() {
            return host.querySelector('.openseadragon-canvas canvas');
        }

        function onMove(e) {
            if (!enabled) return;
            var rect = host.getBoundingClientRect();
            var x = e.clientX - rect.left;
            var y = e.clientY - rect.top;
            if (x < 0 || y < 0 || x > rect.width || y > rect.height) {
                loupe.style.display = 'none';
                return;
            }
            var src = sourceCanvas();
            if (!src) return;
            loupe.style.display = 'block';
            loupe.style.left = (x - radius) + 'px';
            loupe.style.top = (y - radius) + 'px';
            var srcW = (radius * 2) / zoom;
            var srcH = (radius * 2) / zoom;
            var sx = Math.max(0, Math.min(src.width - srcW, x * (src.width / rect.width) - srcW / 2));
            var sy = Math.max(0, Math.min(src.height - srcH, y * (src.height / rect.height) - srcH / 2));
            try {
                ctx.clearRect(0, 0, cvs.width, cvs.height);
                ctx.drawImage(src, sx, sy, srcW, srcH, 0, 0, cvs.width, cvs.height);
            } catch (err) {
                // SecurityError reading a tainted canvas means the tile
                // host did not return CORS headers. We hide the loupe
                // rather than leave a stale frame.
                loupe.style.display = 'none';
            }
        }
        function onLeave() { if (enabled) loupe.style.display = 'none'; }

        host.addEventListener('mousemove', onMove);
        host.addEventListener('mouseleave', onLeave);

        return {
            element: loupe,
            enable:  function () { enabled = true; },
            disable: function () { enabled = false; loupe.style.display = 'none'; },
            toggle:  function () { enabled = !enabled; if (!enabled) loupe.style.display = 'none'; return enabled; },
            destroy: function () {
                host.removeEventListener('mousemove', onMove);
                host.removeEventListener('mouseleave', onLeave);
                if (loupe.parentNode) loupe.parentNode.removeChild(loupe);
            }
        };
    };
}());
