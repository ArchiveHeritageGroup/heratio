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
        }
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
