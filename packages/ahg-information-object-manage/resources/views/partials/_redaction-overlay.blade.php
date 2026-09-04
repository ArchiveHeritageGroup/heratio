{{--
  Visual redaction overlay (PSIS parity).
  - Non-admin viewers ($canBypassRedaction=false) see black rectangles painted
    over the preview image at the coordinates stored in privacy_visual_redaction.
  - Admins see no overlay (canBypassRedaction=true) and a "Redactions hidden
    for admin viewing" notice instead.
  Coordinates may be normalised (0..1) when normalized=1, or raw px when
  normalized=0. We support both - the JS shim picks based on the row flag.
--}}
@if(isset($visualRedactions) && count($visualRedactions))
  @php
    $totalRedactions = collect($visualRedactions)->count();
  @endphp

  {{-- Compact status banner above the image preview. --}}
  <div class="alert alert-{{ ($canBypassRedaction ?? false) ? 'info' : 'warning' }} py-2 px-3 mb-2 small d-flex align-items-center justify-content-between">
    <div>
      <i class="fas fa-mask me-1"></i>
      @if($canBypassRedaction ?? false)
        {{ trans_choice(':n redaction on file. Showing un-redacted view (admin).|:n redactions on file. Showing un-redacted view (admin).', $totalRedactions, ['n' => $totalRedactions]) }}
      @else
        {{ trans_choice(':n region of this record has been redacted by the institution.|:n regions of this record have been redacted by the institution.', $totalRedactions, ['n' => $totalRedactions]) }}
      @endif
    </div>
    @auth
      <a href="{{ route('io.privacy.redaction', $io->slug) }}" class="text-decoration-none small">
        <i class="fas fa-pencil-alt me-1"></i>{{ __('Manage redactions') }}
      </a>
    @endauth
  </div>

  {{-- Overlay shim. Looks up the preview <img> + redactions via data attrs and
       paints absolutely-positioned <div> rectangles over it. Re-runs on
       window.resize so coordinates re-scale when the layout reflows. --}}
  @unless($canBypassRedaction ?? false)
    @php
      // Build the JSON payload in PHP - Blade's @json directive arg-parser
      // mis-counts brackets when given a nested array literal + arrow-fn
      // inside the directive args, causing a parse error at the closing ')'.
      $__redactionPayload = collect($visualRedactions)->map(function ($r) {
          return [
              'page'   => $r->page_number ?? 1,
              'coords' => $r->coords ?? [],
              'norm'   => (int) ($r->normalized ?? 0),
              'color'  => $r->color ?? '#000000',
          ];
      })->values();
    @endphp
    <script type="application/json" id="ahg-visual-redactions-{{ $io->id }}">@json($__redactionPayload)</script>
    <script nonce="{{ csp_nonce() }}">
    (function () {
      'use strict';
      var ioId = {{ (int) $io->id }};
      var dataEl = document.getElementById('ahg-visual-redactions-' + ioId);
      if (!dataEl) return;
      var redactions;
      try { redactions = JSON.parse(dataEl.textContent || '[]'); }
      catch (e) { return; }
      if (!redactions.length) return;

      var IMG_SELECTOR = '.digital-object-preview img, .iiif-viewer img, .pdf-viewer-container img, #content img.img-fluid:not([src*="logo"])';

      // ── Deep-zoom (OpenSeadragon) ──────────────────────────────────────
      //
      // A tiled viewer has no <img> to measure and its own pan/zoom/rotation,
      // so the img path below could never mask it: it found no element and
      // returned, and a viewer showed the unredacted scan to the public. That
      // is the exact failure this whole partial exists to prevent, and it hit
      // the viewer used for high-resolution archival scans.
      //
      // OSD places overlays itself, in viewport coordinates, and keeps them
      // aligned through every transform - so the fix is to hand it the rect
      // rather than compute pixels. imageToViewportRectangle() takes IMAGE
      // pixels, which is also the only stable frame a stored coordinate can
      // mean, so a legacy normalized=0 row is read as image pixels.
      var osdOverlays = [];

      function osdEl() {
        return document.getElementById('osd-iiif-viewer-' + ioId);
      }

      function osdViewer() {
        var el = osdEl();
        if (!el || typeof OpenSeadragon === 'undefined' || !OpenSeadragon.getViewer) return null;
        try { return OpenSeadragon.getViewer(el) || null; } catch (e) { return null; }
      }

      // Returns 'absent' (no viewer - that mode is not showing), 'pending'
      // (viewer up but no image open yet), 'ok', or 'failed'. Only 'failed'
      // may hide the viewer: a viewer that has not opened yet is displaying
      // nothing, so there is nothing to leak, and treating it as a failure
      // would hide a healthy viewer in the moment between the mode button and
      // the first tile.
      function paintOsd(viewer) {
        if (!viewer || !viewer.world) return 'absent';
        if (viewer.world.getItemCount() === 0) return 'pending';
        var item = viewer.world.getItemAt(0);
        if (!item) return 'pending';
        var size = item.getContentSize();
        if (!size || !size.x || !size.y) return 'pending';

        // Ours only - annotations add overlays to the same viewer.
        osdOverlays.forEach(function (el) {
          try { viewer.removeOverlay(el); } catch (e) { /* already gone */ }
        });
        osdOverlays = [];

        var placed = 0;
        redactions.forEach(function (r) {
          var c = r.coords || {};
          if (c.width == null || c.height == null) return;
          if (c.width <= 0 || c.height <= 0) return;

          var px = (r.norm === 1)
            ? new OpenSeadragon.Rect(c.left * size.x, c.top * size.y, c.width * size.x, c.height * size.y)
            : new OpenSeadragon.Rect(c.left, c.top, c.width, c.height);

          var mask = document.createElement('div');
          mask.className = 'ahg-redaction-mask';
          mask.style.cssText = 'background:' + (r.color || '#000') + ';opacity:0.95;pointer-events:none;';
          viewer.addOverlay({ element: mask, location: viewer.viewport.imageToViewportRectangle(px) });
          osdOverlays.push(mask);
          placed++;
        });

        return placed > 0 ? 'ok' : 'failed';
      }

      // Bind once per viewer. OSD re-emits `open` for every image it loads,
      // including the plain-image fallback it uses when a tile source fails,
      // and overlays do not survive that.
      function bindOsd(viewer) {
        if (!viewer || viewer.__ahgRedactionBound) return;
        viewer.__ahgRedactionBound = true;
        viewer.addHandler('open', function () { paintOsd(viewer); });
      }

      function paint() {
        // Find the first preview image in the digital-object viewer area.
        // We don't try to support PDFs here - those need a server-side
        // redacted file (see PdfRedactionService roadmap).
        var img = document.querySelector(IMG_SELECTOR);
        if (!img || !img.complete || img.naturalWidth === 0) return;

        // Set up the parent as a positioning context if it isn't one.
        var parent = img.parentElement;
        if (!parent) return;
        var cs = window.getComputedStyle(parent);
        if (cs.position === 'static') parent.style.position = 'relative';

        // Drop any previous overlays we drew so resize doesn't double-paint.
        Array.prototype.forEach.call(
          parent.querySelectorAll('.ahg-redaction-mask'),
          function (el) { el.remove(); }
        );

        var rect = img.getBoundingClientRect();
        var sx = rect.width  / img.naturalWidth;
        var sy = rect.height / img.naturalHeight;
        var imgOffsetTop  = img.offsetTop;
        var imgOffsetLeft = img.offsetLeft;

        redactions.forEach(function (r) {
          var c = r.coords || {};
          var top = c.top, left = c.left, w = c.width, h = c.height;
          if (w == null || h == null) return;
          if (w <= 0 || h <= 0) return; // zero-sized (cataloguer didn't draw)
          var pxTop, pxLeft, pxW, pxH;
          if (r.norm === 1) {
            pxTop  = top  * rect.height;
            pxLeft = left * rect.width;
            pxW    = w    * rect.width;
            pxH    = h    * rect.height;
          } else {
            pxTop  = top  * sy;
            pxLeft = left * sx;
            pxW    = w    * sx;
            pxH    = h    * sy;
          }
          var mask = document.createElement('div');
          mask.className = 'ahg-redaction-mask';
          mask.style.cssText = 'position:absolute;'
            + 'top:'  + (imgOffsetTop  + pxTop)  + 'px;'
            + 'left:' + (imgOffsetLeft + pxLeft) + 'px;'
            + 'width:'  + pxW + 'px;'
            + 'height:' + pxH + 'px;'
            + 'background:' + (r.color || '#000') + ';'
            + 'opacity:0.95;'
            + 'pointer-events:none;'
            + 'z-index:50;';
          parent.appendChild(mask);
        });
      }

      // ── Fail closed ────────────────────────────────────────────────────
      //
      // A canvas viewer this shim cannot mask must not simply show the record
      // unredacted. Mirador cannot be masked from here - it builds its own
      // OpenSeadragon from an element rather than an id, so it never enters
      // OpenSeadragon's registry - but it does not need to be: the page hands
      // it the server-side burnt-in derivative instead, which also stops the
      // underlying tiles being downloadable. Where that could not be arranged,
      // which today is a record with more than one digital object, it is
      // hidden rather than left open. OSD is only hidden if placing its
      // overlays actually failed.
      function hide(el, why) {
        if (!el || el.dataset.ahgRedactionHidden === '1') return;
        el.dataset.ahgRedactionHidden = '1';
        el.style.display = 'none';
        var note = document.createElement('div');
        note.className = 'alert alert-secondary py-2 px-3 small';
        note.textContent = @json(__('This viewer cannot display the redactions on file, so it has been disabled for this record.'));
        el.parentNode.insertBefore(note, el);
        if (window.console && console.warn) { console.warn('[ahg-redaction] ' + why); }
      }

      function visible(el) {
        return !!el && el.offsetParent !== null;
      }

      function enforce() {
        var viewer = osdViewer();
        if (viewer) {
          bindOsd(viewer);
          if (paintOsd(viewer) === 'failed' && visible(osdEl())) {
            hide(osdEl(), 'deep-zoom viewer has an image open but no redaction overlay could be placed');
          }
        }

        // Mirador is safe when it was given the burnt-in derivative; hide it
        // only when it was not, so it is still showing the original.
        var servedRedacted = !!(window.AHG_REDACTED_ASSET || {})['iiif-viewer-' + ioId];
        var mirador = document.getElementById('mirador-iiif-viewer-' + ioId);
        if (! servedRedacted && visible(mirador)) {
          hide(mirador, 'Mirador was not given a redacted derivative for this record');
        }

        paint();
      }

      // Paint after the image actually loads, and re-paint on window resize
      // so percentage-width images keep their masks aligned.
      function attach(img) {
        if (img.complete) { enforce(); }
        else { img.addEventListener('load', enforce, { once: true }); }
      }
      Array.prototype.forEach.call(document.querySelectorAll(IMG_SELECTOR), attach);
      var rid = null;
      window.addEventListener('resize', function () {
        clearTimeout(rid);
        rid = setTimeout(enforce, 100);
      });
      // The viewer is created lazily and only for the mode actually showing,
      // so poll briefly for it rather than assuming it exists at load. Giving
      // up is safe: switching mode re-runs enforce() through the button
      // handler below, which binds and paints then.
      (function waitForOsd(tries) {
        var viewer = osdViewer();
        if (viewer) { bindOsd(viewer); paintOsd(viewer); return; }
        if (tries > 0) { setTimeout(function () { waitForOsd(tries - 1); }, 250); }
      })(20);

      // The viewer-mode buttons swap which element is displayed without
      // reloading, and nothing else re-runs this.
      ['btn-osd-', 'btn-mirador-', 'btn-img-'].forEach(function (p) {
        var b = document.getElementById(p + 'iiif-viewer-' + ioId);
        if (b) { b.addEventListener('click', function () { setTimeout(enforce, 150); }); }
      });

      // Re-paint after a short delay too - PDF.js / OpenSeadragon may finish
      // their first render slightly after DOMContentLoaded.
      setTimeout(enforce, 400);
      setTimeout(enforce, 1500);
    })();
    </script>
  @endunless
@endif
