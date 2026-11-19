{{--
    HLS quality switching for theme::components.media-player (heratio#1754 / #1755).

    The <video> already lists the HLS master playlist first and the MP4 after
    it, so a browser that cannot play HLS simply plays the MP4. This script
    adds the quality picker:
      - browsers that play HLS themselves (Safari, iOS): Auto = the master
        playlist (adaptive), a fixed quality = that variant's playlist;
      - Video.js (already on the page in videojs mode, otherwise loaded from
        public/vendor/videojs for this one player): its HLS engine plays the
        stream and the picker turns quality levels on and off.
    Plyr mode without native HLS keeps the MP4 (Plyr has no HLS engine).

    Variables: $playerId, $hls (master playlist URL), $playerType.
--}}
<script nonce="{{ csp_nonce() }}">
(function () {
    var el = document.getElementById(@json($playerId));
    var master = @json($hls);
    var playerType = @json($playerType);
    if (!el || !master) return;

    function picker(heights, onPick) {
        if (heights.length < 2 || document.getElementById(el.id + '-quality')) return;
        var wrap = document.createElement('div');
        wrap.className = 'd-flex align-items-center justify-content-end gap-2 mt-2 small';
        var lab = document.createElement('label');
        lab.htmlFor = el.id + '-quality';
        lab.textContent = @json(__('Quality'));
        var sel = document.createElement('select');
        sel.id = el.id + '-quality';
        sel.className = 'form-select form-select-sm w-auto';
        sel.add(new Option(@json(__('Auto')), 'auto'));
        heights.forEach(function (h) { sel.add(new Option(h + 'p', String(h))); });
        sel.addEventListener('change', function () { onPick(sel.value); });
        wrap.appendChild(lab);
        wrap.appendChild(sel);
        var anchor = (el.closest && el.closest('.video-js')) || el;
        anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
    }

    // The variants listed in the master playlist, lowest first.
    function variants(cb) {
        var base = new URL(master, window.location.href);
        fetch(base.href, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : ''; }).then(function (text) {
            var out = [], lines = text.split(/\r?\n/);
            for (var i = 0; i < lines.length - 1; i++) {
                var m = lines[i].indexOf('#EXT-X-STREAM-INF') === 0 && /RESOLUTION=\d+x(\d+)/.exec(lines[i]);
                if (m && lines[i + 1]) out.push({ h: parseInt(m[1], 10), uri: new URL(lines[i + 1].trim(), base).href });
            }
            out.sort(function (a, b) { return a.h - b.h; });
            cb(out);
        }).catch(function () {});
    }

    function nativeHls() {
        variants(function (vs) {
            picker(vs.map(function (v) { return v.h; }), function (val) {
                var t = el.currentTime, playing = !el.paused;
                var v = vs.filter(function (x) { return String(x.h) === val; })[0];
                el.src = v ? v.uri : master;
                el.addEventListener('loadedmetadata', function () {
                    try { el.currentTime = t; } catch (_) {}
                    if (playing) { var p = el.play(); if (p && p.catch) p.catch(function () {}); }
                }, { once: true });
            });
        });
    }

    function videojsHls() {
        var p = window.videojs.getPlayer ? window.videojs.getPlayer(el) : null;
        if (!p) {
            if (!el.classList.contains('video-js')) el.classList.add('video-js');
            p = window.videojs(el, { controls: el.hasAttribute('controls'), fluid: true, preload: 'metadata' });
        }
        p.ready(function () {
            var levels = p.qualityLevels ? p.qualityLevels() : null;
            if (!levels) return;
            variants(function (vs) {
                picker(vs.map(function (v) { return v.h; }), function (val) {
                    for (var i = 0; i < levels.length; i++) {
                        levels[i].enabled = val === 'auto' || String(levels[i].height) === val;
                    }
                });
            });
        });
    }

    function loadVideojs(cb) {
        if (window.videojs) return cb();
        var css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = @json(asset('vendor/videojs/video-js.min.css'));
        document.head.appendChild(css);
        var s = document.createElement('script');
        s.src = @json(asset('vendor/videojs/video.min.js'));
        s.onload = cb;
        document.head.appendChild(s);
    }

    function start() {
        try {
            if (playerType === 'videojs' && window.videojs) return videojsHls();
            if (el.canPlayType('application/vnd.apple.mpegurl')) return nativeHls();
            if (playerType === 'plyr') return;
            loadVideojs(videojsHls);
        } catch (e) {
            if (window.console && console.warn) console.warn('HLS quality switching unavailable; playing the MP4:', e);
        }
    }

    if (document.readyState === 'complete') start(); else window.addEventListener('load', start);
})();
</script>
