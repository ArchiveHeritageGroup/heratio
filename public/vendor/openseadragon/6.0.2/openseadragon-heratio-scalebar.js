/**
 * OpenSeadragon Heratio scalebar plugin.
 *
 * Issue #698. Reads physical-pixel metadata from the OSD viewer's
 * tileSource info.json (the IIIF Image API info.json's "service"
 * block exposing the physical-dimensions service). When present we
 * render a real-world distance bar in the OSD canvas.
 *
 * The IIIF physical-dimensions service profile is
 *   http://iiif.io/api/annex/services/physdim
 * and carries two fields: physicalScale (number, units-per-pixel) and
 * physicalUnits (string, "mm" by default, also "cm" / "m" / "in").
 *
 * Usage:
 *   var v = OpenSeadragon({ ... });
 *   v.addHeratioScalebar({ position: 'BOTTOM_LEFT' });
 */
(function () {
    'use strict';
    if (typeof OpenSeadragon === 'undefined') return;
    if (OpenSeadragon.Viewer.prototype.addHeratioScalebar) return;

    var UNIT_TO_MM = { um: 0.001, mm: 1, cm: 10, m: 1000, in: 25.4 };
    var UNIT_LADDER = [
        { label: 'um', factor: 0.001 },
        { label: 'mm', factor: 1 },
        { label: 'cm', factor: 10 },
        { label: 'm',  factor: 1000 }
    ];

    function nicePhysicalLength(mmPerScreenPx, viewerWidthPx) {
        var targetMm = mmPerScreenPx * (viewerWidthPx / 6);
        var pick = UNIT_LADDER[1];
        for (var i = 0; i < UNIT_LADDER.length; i++) {
            var u = UNIT_LADDER[i];
            if (targetMm / u.factor >= 1 && targetMm / u.factor < 1000) pick = u;
        }
        var value = targetMm / pick.factor;
        var mag = Math.pow(10, Math.floor(Math.log10(value)));
        var norm = value / mag;
        var rounded;
        if (norm < 1.5) rounded = 1 * mag;
        else if (norm < 3.5) rounded = 2 * mag;
        else if (norm < 7.5) rounded = 5 * mag;
        else rounded = 10 * mag;
        return {
            units: pick.label,
            physical: rounded,
            pixels: (rounded * pick.factor) / mmPerScreenPx
        };
    }

    function findPhysDimService(tileSource) {
        if (!tileSource) return null;
        // Plain image source has no service block at all.
        var svc = tileSource.service ||
                  (tileSource.tileSources && tileSource.tileSources[0] && tileSource.tileSources[0].service) ||
                  null;
        if (!svc) return null;
        var arr = Array.isArray(svc) ? svc : [svc];
        for (var i = 0; i < arr.length; i++) {
            var s = arr[i];
            var profile = s.profile || s['@profile'] || '';
            var type = s.type || s['@type'] || '';
            if ((typeof profile === 'string' && profile.indexOf('physdim') >= 0) ||
                type === 'PhysicalDimensions' ||
                (s.physicalScale && s.physicalUnits)) {
                return s;
            }
        }
        return null;
    }

    function injectStyles() {
        if (document.getElementById('osd-heratio-scalebar-styles')) return;
        var s = document.createElement('style');
        s.id = 'osd-heratio-scalebar-styles';
        s.textContent =
            '.osd-heratio-scalebar{position:absolute;z-index:1000;color:#fff;font-size:11px;font-family:sans-serif;text-shadow:0 0 3px rgba(0,0,0,.8);pointer-events:none;}' +
            '.osd-heratio-scalebar .bar{height:4px;background:#fff;border:1px solid #000;}' +
            '.osd-heratio-scalebar .label{margin-top:2px;text-align:center;}';
        document.head.appendChild(s);
    }

    OpenSeadragon.Viewer.prototype.addHeratioScalebar = function (opts) {
        opts = opts || {};
        var viewer = this;
        injectStyles();

        var bar = document.createElement('div');
        bar.className = 'osd-heratio-scalebar';
        var pos = (opts.position || 'BOTTOM_LEFT').toUpperCase();
        if (pos === 'BOTTOM_LEFT')       { bar.style.bottom = '10px'; bar.style.left = '10px'; }
        else if (pos === 'BOTTOM_RIGHT') { bar.style.bottom = '10px'; bar.style.right = '10px'; }
        else if (pos === 'TOP_LEFT')     { bar.style.top = '10px';    bar.style.left = '10px'; }
        else                              { bar.style.top = '10px';    bar.style.right = '10px'; }
        bar.innerHTML = '<div class="bar" style="width:100px"></div><div class="label">-</div>';
        viewer.element.appendChild(bar);

        // Allow caller to provide an explicit service object (e.g. from
        // the Heratio manifest endpoint) when info.json doesn't carry
        // the physdim service. This is the common case because
        // Cantaloupe stops short of emitting physdim - we know it
        // server-side via the digital_object metadata.
        var explicit = opts.service || null;
        var info = null;

        function service() {
            if (explicit) return explicit;
            try { info = viewer.source && (viewer.source.info || viewer.source); } catch (e) { info = null; }
            return findPhysDimService(info);
        }

        function update() {
            var svc = service();
            if (!svc) { bar.style.display = 'none'; return; }
            bar.style.display = 'block';
            var physicalScale = parseFloat(svc.physicalScale);
            if (!physicalScale || !isFinite(physicalScale)) { bar.style.display = 'none'; return; }
            var units = (svc.physicalUnits || 'mm').toLowerCase();
            var mmPerImagePx = physicalScale * (UNIT_TO_MM[units] || 1);

            var containerWidth = viewer.viewport.getContainerSize().x;
            var vpZoom = viewer.viewport.getZoom(true);
            var imgZoom = viewer.viewport.viewportToImageZoom(vpZoom);
            var mmPerScreenPx = mmPerImagePx / imgZoom;
            var pick = nicePhysicalLength(mmPerScreenPx, containerWidth);
            bar.querySelector('.bar').style.width = Math.round(pick.pixels) + 'px';
            bar.querySelector('.label').textContent = pick.physical + ' ' + pick.units;
        }

        viewer.addHandler('animation', update);
        viewer.addHandler('zoom', update);
        viewer.addHandler('resize', update);
        viewer.addHandler('open', update);

        return {
            element: bar,
            destroy: function () {
                viewer.removeHandler('animation', update);
                viewer.removeHandler('zoom', update);
                viewer.removeHandler('resize', update);
                viewer.removeHandler('open', update);
                if (bar.parentNode) bar.parentNode.removeChild(bar);
            },
            setService: function (svc) { explicit = svc; update(); }
        };
    };
}());
