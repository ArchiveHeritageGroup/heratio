/**
 * AHG IIIF Viewer - OpenSeadragon + Mirador + Carousel toggle for images
 */
function initIiifViewer(viewerId, imageUrl, title, initialMode) {
    var vid = viewerId;
    var osdEl = document.getElementById('osd-' + vid);
    var mirEl = document.getElementById('mirador-' + vid);
    var imgEl = document.getElementById('img-' + vid);
    var carEl = document.getElementById('carousel-' + vid);
    var osdViewer = null;
    var miradorLoaded = false;

    // Build IIIF tile source for formats that need Cantaloupe (TIFF, JP2, etc.)
    var needsIiif = /\.(tiff?|jp2|jpx)$/i.test(imageUrl);
    var iiifTileSource = null;
    if (needsIiif) {
        // Convert URL path to Cantaloupe identifier: strip origin, replace / with _SL_
        var urlObj = new URL(imageUrl, window.location.origin);
        var relPath = urlObj.pathname.replace(/^\//, '');
        var iiifId = relPath.replace(/\//g, '_SL_');
        iiifTileSource = window.location.origin + '/iiif/3/' + iiifId + '/info.json';
    }

    function hideAllPanels() {
        osdEl.style.display = 'none';
        mirEl.style.display = 'none';
        imgEl.style.display = 'none';
        if (carEl) carEl.style.display = 'none';
    }

    function showOSD() {
        hideAllPanels();
        osdEl.style.display = 'block';
        document.getElementById('btn-osd-' + vid).classList.add('active');
        document.getElementById('btn-mirador-' + vid).classList.remove('active');
        document.getElementById('btn-img-' + vid).classList.remove('active');

        if (!osdViewer && typeof OpenSeadragon !== 'undefined') {
            osdViewer = OpenSeadragon({
                id: 'osd-' + vid,
                tileSources: iiifTileSource || {
                    type: 'image',
                    url: imageUrl,
                    buildPyramid: false
                },
                showNavigator: true,
                navigatorPosition: 'BOTTOM_RIGHT',
                prefixUrl: '/vendor/openseadragon/6.0.2/images/',
                gestureSettingsMouse: { clickToZoom: true, dblClickToZoom: true },
                gestureSettingsTouch: { pinchToZoom: true },
                animationTime: 0.5,
                zoomPerClick: 1.5,
                maxZoomPixelRatio: 4,
                visibilityRatio: 0.5,
                constrainDuringPan: true,
                immediateRender: true,
                crossOriginPolicy: 'Anonymous'
            });
            buildFilterToolbar();
        }
    }

    // Filter toolbar: brightness / contrast / greyscale / invert / threshold.
    // Requires openseadragon-filtering.js to be loaded — feature-detected so
    // missing plugin just skips the toolbar instead of throwing.
    function buildFilterToolbar() {
        if (!osdViewer || !osdViewer.setFilterOptions || !window.OpenSeadragon || !OpenSeadragon.Filters) return;
        if (document.getElementById('osd-filters-' + vid)) return;
        injectFilterStyles();

        var bar = document.createElement('div');
        bar.id = 'osd-filters-' + vid;
        bar.className = 'osd-filter-toolbar';
        bar.innerHTML =
            '<button type="button" class="osd-filter-toggle" title="Image filters" aria-label="Image filters">' +
                '<i class="fas fa-sliders-h"></i>' +
            '</button>' +
            '<div class="osd-filter-panel" hidden>' +
                '<label>Brightness <span class="osd-filter-val" data-for="brightness">0</span>' +
                    '<input type="range" data-filter="brightness" min="-100" max="100" value="0" step="1">' +
                '</label>' +
                '<label>Contrast <span class="osd-filter-val" data-for="contrast">0</span>' +
                    '<input type="range" data-filter="contrast" min="-50" max="50" value="0" step="1">' +
                '</label>' +
                '<label class="osd-filter-check">' +
                    '<input type="checkbox" data-filter="greyscale"> Greyscale' +
                '</label>' +
                '<label class="osd-filter-check">' +
                    '<input type="checkbox" data-filter="invert"> Invert' +
                '</label>' +
                '<label>Threshold <span class="osd-filter-val" data-for="threshold">off</span>' +
                    '<input type="range" data-filter="threshold" min="0" max="255" value="0" step="1">' +
                '</label>' +
                '<button type="button" class="osd-filter-reset">Reset</button>' +
            '</div>';
        osdEl.appendChild(bar);

        bar.querySelector('.osd-filter-toggle').addEventListener('click', function () {
            var p = bar.querySelector('.osd-filter-panel');
            p.hidden = !p.hidden;
        });

        bar.querySelectorAll('input').forEach(function (inp) {
            inp.addEventListener('input', applyFilters);
            inp.addEventListener('change', applyFilters);
        });

        bar.querySelector('.osd-filter-reset').addEventListener('click', function () {
            bar.querySelectorAll('input[type=range]').forEach(function (r) { r.value = 0; });
            bar.querySelectorAll('input[type=checkbox]').forEach(function (c) { c.checked = false; });
            applyFilters();
        });

        function applyFilters() {
            var brightness = parseFloat(bar.querySelector('[data-filter=brightness]').value);
            var contrastRaw = parseFloat(bar.querySelector('[data-filter=contrast]').value);
            var greyscale = bar.querySelector('[data-filter=greyscale]').checked;
            var invert = bar.querySelector('[data-filter=invert]').checked;
            var threshold = parseInt(bar.querySelector('[data-filter=threshold]').value, 10);

            bar.querySelector('[data-for=brightness]').textContent = brightness;
            bar.querySelector('[data-for=contrast]').textContent = contrastRaw;
            bar.querySelector('[data-for=threshold]').textContent = threshold > 0 ? threshold : 'off';

            var processors = [];
            if (greyscale) processors.push(OpenSeadragon.Filters.GREYSCALE());
            if (brightness) processors.push(OpenSeadragon.Filters.BRIGHTNESS(brightness));
            if (contrastRaw) processors.push(OpenSeadragon.Filters.CONTRAST(1 + contrastRaw / 50));
            if (invert) processors.push(OpenSeadragon.Filters.INVERT());
            if (threshold > 0) processors.push(OpenSeadragon.Filters.THRESHOLDING(threshold));

            osdViewer.setFilterOptions({ filters: processors.length ? { processors: processors } : [] });
        }
    }

    function injectFilterStyles() {
        if (document.getElementById('osd-filter-styles')) return;
        var s = document.createElement('style');
        s.id = 'osd-filter-styles';
        s.textContent =
            '.osd-filter-toolbar{position:absolute;top:8px;right:8px;z-index:1000;font-size:12px;color:#fff;}' +
            '.osd-filter-toggle{width:34px;height:34px;border:0;border-radius:4px;background:rgba(0,0,0,.65);color:#fff;cursor:pointer;}' +
            '.osd-filter-toggle:hover{background:rgba(0,0,0,.85);}' +
            '.osd-filter-panel{position:absolute;top:40px;right:0;background:rgba(0,0,0,.85);padding:10px 12px;border-radius:6px;width:200px;display:flex;flex-direction:column;gap:8px;}' +
            '.osd-filter-panel label{display:flex;flex-direction:column;font-size:11px;line-height:1.3;gap:2px;}' +
            '.osd-filter-panel label.osd-filter-check{flex-direction:row;align-items:center;gap:6px;}' +
            '.osd-filter-panel input[type=range]{width:100%;}' +
            '.osd-filter-val{font-weight:600;color:#9ec1ff;}' +
            '.osd-filter-reset{background:#444;color:#fff;border:0;border-radius:3px;padding:4px 8px;cursor:pointer;font-size:11px;}' +
            '.osd-filter-reset:hover{background:#666;}';
        document.head.appendChild(s);
    }

    function showMirador() {
        hideAllPanels();
        mirEl.style.display = 'block';
        document.getElementById('btn-mirador-' + vid).classList.add('active');
        document.getElementById('btn-osd-' + vid).classList.remove('active');
        document.getElementById('btn-img-' + vid).classList.remove('active');

        // Always recreate Mirador to avoid stale state
        mirEl.innerHTML = '';

        // For IIIF-served images (TIFF etc.), use the IIIF image service in the manifest
        var miradorImageUrl = imageUrl;
        var miradorService = null;
        if (iiifTileSource) {
            var serviceId = iiifTileSource.replace('/info.json', '');
            miradorImageUrl = serviceId + '/full/max/0/default.jpg';
            miradorService = {
                '@id': serviceId,
                '@type': 'ImageService2',
                profile: 'http://iiif.io/api/image/2/level2.json'
            };
        }

        // Probe the real image dimensions before building the manifest. Mirador
        // sizes its canvas from manifest width/height, so a wrong value parks
        // the image in a corner of an oversized empty canvas.
        function buildAndShow(realW, realH) {
            var manifest = {
                '@context': 'http://iiif.io/api/presentation/2/context.json',
                '@type': 'sc:Manifest',
                '@id': imageUrl + '/manifest.json',
                label: title || 'Image',
                sequences: [{
                    '@type': 'sc:Sequence',
                    canvases: [{
                        '@type': 'sc:Canvas',
                        '@id': imageUrl + '/canvas/1',
                        label: title || 'Image',
                        width: realW,
                        height: realH,
                        images: [{
                            '@type': 'oa:Annotation',
                            motivation: 'sc:painting',
                            resource: Object.assign({
                                '@id': miradorImageUrl,
                                '@type': 'dctypes:Image',
                                format: 'image/jpeg',
                                width: realW,
                                height: realH
                            }, miradorService ? { service: miradorService } : {}),
                            on: imageUrl + '/canvas/1'
                        }]
                    }]
                }]
            };

            var manifestBlob = new Blob([JSON.stringify(manifest)], { type: 'application/json' });
            var manifestUrl = URL.createObjectURL(manifestBlob);

            function createMirador() {
                if (typeof Mirador === 'undefined') {
                    mirEl.innerHTML = '<div class="alert alert-warning m-3">Mirador viewer not available.</div>';
                    return;
                }
                Mirador.viewer({
                    id: 'mirador-' + vid,
                    windows: [{ manifestId: manifestUrl }],
                    window: {
                        allowClose: false,
                        allowMaximize: false,
                        allowFullscreen: true,
                        allowTopMenuButton: false,
                        allowWindowSideBar: true,
                        sideBarOpen: false
                    },
                    workspaceControlPanel: { enabled: false },
                    workspace: { type: 'mosaic', allowNewWindows: false }
                });

                // Hide Mirador's own close/minimize/maximize buttons via CSS
                setTimeout(function() {
                    var style = document.createElement('style');
                    style.textContent = '#mirador-' + vid + ' button[aria-label="Close"], ' +
                        '#mirador-' + vid + ' button[aria-label="Minimize window"], ' +
                        '#mirador-' + vid + ' button[aria-label="Maximize window"], ' +
                        '#mirador-' + vid + ' .mirador-window-top-bar-buttons button:first-child { display: none !important; }';
                    document.head.appendChild(style);
                }, 500);
            }

            if (!miradorLoaded) {
                var s = document.createElement('script');
                s.src = '/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js';
                s.onload = function () {
                    miradorLoaded = true;
                    createMirador();
                };
                s.onerror = function () {
                    mirEl.innerHTML = '<div class="alert alert-warning m-3">Could not load Mirador.</div>';
                };
                document.head.appendChild(s);
            } else {
                createMirador();
            }
        }

        var probe = new Image();
        probe.onload = function () {
            buildAndShow(probe.naturalWidth || 4000, probe.naturalHeight || 3000);
        };
        probe.onerror = function () {
            // Fall back to a sane default if probing fails — better to render
            // an oversized canvas than to show nothing.
            buildAndShow(4000, 3000);
        };
        probe.src = miradorImageUrl;
    }

    function showImg() {
        hideAllPanels();
        if (carEl) {
            carEl.style.display = 'block';
        } else {
            imgEl.style.display = 'block';
        }
        document.getElementById('btn-img-' + vid).classList.add('active');
        document.getElementById('btn-osd-' + vid).classList.remove('active');
        document.getElementById('btn-mirador-' + vid).classList.remove('active');
    }

    document.getElementById('btn-osd-' + vid).addEventListener('click', showOSD);
    document.getElementById('btn-mirador-' + vid).addEventListener('click', showMirador);
    document.getElementById('btn-img-' + vid).addEventListener('click', showImg);
    document.getElementById('btn-fs-' + vid).addEventListener('click', function () {
        var el = osdEl.style.display !== 'none' ? osdEl :
                 (mirEl.style.display !== 'none' ? mirEl :
                 (carEl && carEl.style.display !== 'none' ? carEl : imgEl));
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    });

    // Honour initial mode from server settings (defaults to OSD)
    var mode = (initialMode || 'openseadragon').toLowerCase();
    if (mode === 'mirador')              showMirador();
    else if (mode === 'single' ||
             mode === 'carousel')         showImg();
    else                                  showOSD();
}
