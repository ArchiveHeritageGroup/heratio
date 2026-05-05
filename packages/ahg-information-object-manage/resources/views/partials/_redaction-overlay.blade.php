{{--
  Visual redaction overlay (PSIS parity).
  - Non-admin viewers ($canBypassRedaction=false) see black rectangles painted
    over the preview image at the coordinates stored in privacy_visual_redaction.
  - Admins see no overlay (canBypassRedaction=true) and a "Redactions hidden
    for admin viewing" notice instead.
  Coordinates may be normalised (0..1) when normalized=1, or raw px when
  normalized=0. We support both — the JS shim picks based on the row flag.
--}}
@if(isset($visualRedactions) && count($visualRedactions))
  @php
    $applied = collect($visualRedactions)->where('status', 'applied')->count();
    $pending = collect($visualRedactions)->whereIn('status', ['pending', 'reviewed'])->count();
  @endphp

  {{-- Compact status banner above the image preview. --}}
  <div class="alert alert-{{ ($canBypassRedaction ?? false) ? 'info' : 'warning' }} py-2 px-3 mb-2 small d-flex align-items-center justify-content-between">
    <div>
      <i class="fas fa-mask me-1"></i>
      @if($canBypassRedaction ?? false)
        {{ __(':n redaction(s) on file. Showing un-redacted view (admin).', ['n' => $applied + $pending]) }}
      @else
        {{ __('Some content has been redacted by the institution. :a applied, :p pending.', ['a' => $applied, 'p' => $pending]) }}
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
    <script type="application/json" id="ahg-visual-redactions-{{ $io->id }}">
      @json($visualRedactions->map(fn($r) => [
          'page'   => $r->page_number ?? 1,
          'coords' => $r->coords ?? [],
          'norm'   => (int) ($r->normalized ?? 0),
          'color'  => $r->color ?? '#000000',
      ]))
    </script>
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

      function paint() {
        // Find the first preview image in the digital-object viewer area.
        // We don't try to support PDFs/canvases here — those need a server-
        // side redacted file (see PdfRedactionService roadmap).
        var img = document.querySelector('.digital-object-preview img, .iiif-viewer img, .pdf-viewer-container img, #content img.img-fluid:not([src*="logo"])');
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

      // Paint after the image actually loads, and re-paint on window resize
      // so percentage-width images keep their masks aligned.
      function attach(img) {
        if (img.complete) { paint(); }
        else { img.addEventListener('load', paint, { once: true }); }
      }
      var imgs = document.querySelectorAll('.digital-object-preview img, .iiif-viewer img, .pdf-viewer-container img, #content img.img-fluid:not([src*="logo"])');
      Array.prototype.forEach.call(imgs, attach);
      var rid = null;
      window.addEventListener('resize', function () {
        clearTimeout(rid);
        rid = setTimeout(paint, 100);
      });
      // Re-paint after a short delay too — PDF.js / OpenSeadragon may finish
      // their first render slightly after DOMContentLoaded.
      setTimeout(paint, 400);
      setTimeout(paint, 1500);
    })();
    </script>
  @endunless
@endif
