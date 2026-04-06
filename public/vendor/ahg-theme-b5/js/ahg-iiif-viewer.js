/**
 * AHG IIIF Viewer - OpenSeadragon + Mirador toggle for images
 */
function initIiifViewer(viewerId, imageUrl, title) {
    var vid = viewerId;
    var osdEl = document.getElementById('osd-' + vid);
    var mirEl = document.getElementById('mirador-' + vid);
    var imgEl = document.getElementById('img-' + vid);
    var osdViewer = null;
    var miradorLoaded = false;

    function showOSD() {
        osdEl.style.display = 'block';
        mirEl.style.display = 'none';
        imgEl.style.display = 'none';
        document.getElementById('btn-osd-' + vid).classList.add('active');
        document.getElementById('btn-mirador-' + vid).classList.remove('active');
        document.getElementById('btn-img-' + vid).classList.remove('active');

        if (!osdViewer && typeof OpenSeadragon !== 'undefined') {
            osdViewer = OpenSeadragon({
                id: 'osd-' + vid,
                tileSources: {
                    type: 'image',
                    url: imageUrl,
                    buildPyramid: false
                },
                showNavigator: true,
                navigatorPosition: 'BOTTOM_RIGHT',
                prefixUrl: '/vendor/ahg-theme-b5/js/vendor/openseadragon/images/',
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
        osdEl.style.display = 'none';
        mirEl.style.display = 'block';
        imgEl.style.display = 'none';
        document.getElementById('btn-mirador-' + vid).classList.add('active');
        document.getElementById('btn-osd-' + vid).classList.remove('active');
        document.getElementById('btn-img-' + vid).classList.remove('active');

        // Always recreate Mirador to avoid stale state
        mirEl.innerHTML = '';

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
                    width: 4000,
                    height: 3000,
                    images: [{
                        '@type': 'oa:Annotation',
                        motivation: 'sc:painting',
                        resource: {
                            '@id': imageUrl,
                            '@type': 'dctypes:Image',
                            format: 'image/jpeg',
                            width: 4000,
                            height: 3000
                        },
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

    function showImg() {
        osdEl.style.display = 'none';
        mirEl.style.display = 'none';
        imgEl.style.display = 'block';
        document.getElementById('btn-img-' + vid).classList.add('active');
        document.getElementById('btn-osd-' + vid).classList.remove('active');
        document.getElementById('btn-mirador-' + vid).classList.remove('active');
    }

    document.getElementById('btn-osd-' + vid).addEventListener('click', showOSD);
    document.getElementById('btn-mirador-' + vid).addEventListener('click', showMirador);
    document.getElementById('btn-img-' + vid).addEventListener('click', showImg);
    document.getElementById('btn-fs-' + vid).addEventListener('click', function () {
        var el = osdEl.style.display !== 'none' ? osdEl : (mirEl.style.display !== 'none' ? mirEl : imgEl);
        if (el.requestFullscreen) el.requestFullscreen();
        else if (el.webkitRequestFullscreen) el.webkitRequestFullscreen();
    });

    // Auto-init OSD
    showOSD();
}
