{{--
  Multi-angle gallery component for 3D objects.
  Displays 6 Blender renders (front, back, left, right, top, detail) as
  a thumbnail row with lightbox modal.

  Usage: @include('ahg-3d-model::_multi-angle-gallery', ['digitalObjectId' => $doId])
--}}
@props(['digitalObjectId' => 0])

@php
  $views = ['front', 'back', 'left', 'right', 'top', 'detail'];
  $doId = $digitalObjectId ?? 0;
  $renders = [];

  if ($doId) {
      $digitalObject = \Illuminate\Support\Facades\DB::table('digital_object')->where('id', $doId)->first();
      if ($digitalObject) {
          $uploadsBase = config('heratio.uploads_path');
          $masterDir = dirname($uploadsBase . $digitalObject->path . $digitalObject->name);
          $multiAngleDir = $masterDir . '/multiangle';

          if (is_dir($multiAngleDir)) {
              foreach ($views as $view) {
                  $png = $multiAngleDir . '/' . $view . '.png';
                  if (file_exists($png) && filesize($png) > 500) {
                      $webPath = str_replace($uploadsBase, '/uploads', $png);
                      $renders[$view] = $webPath;
                  }
              }
          }
      }
  }

  $galleryId = 'multiangle-gallery-' . $doId;
@endphp

@if(!empty($renders))
<div class="card mt-3" id="{{ $galleryId }}">
  <div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff;">
    <i class="fas fa-cube me-2"></i>Multi-Angle Views
  </div>
  <div class="card-body">
    <div class="row g-2">
      @foreach($renders as $view => $webPath)
        <div class="col-4 col-md-2 text-center">
          <a href="{{ $webPath }}"
             data-bs-toggle="modal"
             data-bs-target="#{{ $galleryId }}-modal"
             data-view="{{ $view }}"
             class="multiangle-thumb">
            <img src="{{ $webPath }}"
                 alt="{{ ucfirst($view) }} view"
                 class="img-fluid rounded border"
                 style="max-height: 120px; cursor: pointer;">
          </a>
          <small class="d-block text-muted mt-1">{{ ucfirst($view) }}</small>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- Lightbox Modal --}}
<div class="modal fade" id="{{ $galleryId }}-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $galleryId }}-title">{{ __('View') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body text-center">
        <img id="{{ $galleryId }}-img" src="" alt="" class="img-fluid">
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var gallery = document.getElementById('{{ $galleryId }}');
  if (!gallery) return;
  gallery.querySelectorAll('.multiangle-thumb').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      var src = this.getAttribute('href');
      var view = this.getAttribute('data-view');
      var img = document.getElementById('{{ $galleryId }}-img');
      var title = document.getElementById('{{ $galleryId }}-title');
      if (img) img.src = src;
      if (title) title.textContent = view.charAt(0).toUpperCase() + view.slice(1) + ' View';
    });
  });
})();
</script>
@endif
