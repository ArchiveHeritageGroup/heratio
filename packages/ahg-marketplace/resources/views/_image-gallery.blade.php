{{--
  Partial: image-gallery (ported from atom-ahg-plugins/ahgMarketplacePlugin/_imageGallery.php)

  Variables:
    $images (array) Array of image objects, each with: file_path, caption, is_primary
--}}
@php
  $primaryImage = null;
  $thumbs = [];
  if (!empty($images)) {
      foreach ($images as $img) {
          if (!empty($img->is_primary)) {
              $primaryImage = $img;
          }
          $thumbs[] = $img;
      }
      if (!$primaryImage && count($thumbs) > 0) {
          $primaryImage = $thumbs[0];
      }
  }
  $galleryId = 'mkt-gallery-' . mt_rand(1000, 9999);
@endphp
<div class="mkt-gallery" id="{{ $galleryId }}">

  {{-- Main image --}}
  <div class="mkt-gallery-main border rounded overflow-hidden bg-light text-center mb-2" data-bs-toggle="modal" data-bs-target="#{{ $galleryId }}-modal" role="button">
    @if ($primaryImage)
      <img src="{{ $primaryImage->file_path }}" alt="{{ $primaryImage->caption ?? '' }}" class="img-fluid" id="{{ $galleryId }}-main-img">
    @else
      <div class="d-flex align-items-center justify-content-center py-5">
        <i class="fas fa-image fa-5x text-muted"></i>
      </div>
    @endif
  </div>

  {{-- Thumbnail strip --}}
  @if (count($thumbs) > 1)
    <div class="mkt-gallery-thumbs d-flex flex-nowrap gap-2 overflow-auto pb-1">
      @foreach ($thumbs as $idx => $thumb)
        <div class="mkt-gallery-thumb border rounded overflow-hidden flex-shrink-0"
             data-src="{{ $thumb->file_path }}"
             data-gallery="{{ $galleryId }}"
             role="button">
          <img src="{{ $thumb->file_path }}"
               alt="{{ $thumb->caption ?? __('Image') . ' ' . ($idx + 1) }}"
               class="w-100 h-100" style="object-fit: cover;">
        </div>
      @endforeach
    </div>
  @endif
</div>

{{-- Lightbox modal --}}
@if ($primaryImage)
  <div class="modal fade" id="{{ $galleryId }}-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content bg-dark border-0">
        <div class="modal-header border-0 pb-0">
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body text-center p-2">
          <img src="{{ $primaryImage->file_path }}" alt="" class="img-fluid" id="{{ $galleryId }}-lightbox-img" style="max-height: 80vh;">
        </div>
      </div>
    </div>
  </div>
@endif

<script @cspNonce>
(function() {
  var gid = {!! json_encode($galleryId) !!};

  function init() {
    var container = document.getElementById(gid);
    if (!container) return;

    container.querySelectorAll('.mkt-gallery-thumb').forEach(function(thumb) {
      thumb.addEventListener('click', function() {
        var src = this.getAttribute('data-src');
        var mainImg = document.getElementById(gid + '-main-img');
        var lbImg = document.getElementById(gid + '-lightbox-img');
        if (mainImg) mainImg.src = src;
        if (lbImg) lbImg.src = src;
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
