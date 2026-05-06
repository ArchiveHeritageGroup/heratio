@if(isset($digitalObjects) && $digitalObjects && $digitalObjects['master'])
  @php
    $master = $digitalObjects['master'];
    $mediaType = \AhgCore\Services\DigitalObjectService::getMediaType($master);
    $displayUrl = \AhgCore\Services\DigitalObjectService::getDisplayUrl($digitalObjects);
    $thumbnailUrl = \AhgCore\Services\DigitalObjectService::getThumbnailUrl($digitalObjects);

    // Load IIIF viewer settings from iiif_viewer_settings table
    static $__iiifSettings = null;
    if ($__iiifSettings === null) {
        try {
            $__iiifSettings = \Illuminate\Support\Facades\Schema::hasTable('iiif_viewer_settings')
                ? \DB::table('iiif_viewer_settings')->pluck('setting_value', 'setting_key')->toArray()
                : [];
        } catch (\Throwable $e) { $__iiifSettings = []; }
    }
    $viewerHeight = $__iiifSettings['viewer_height'] ?? '500px';
    $showOnView = ($__iiifSettings['show_on_view'] ?? '1') === '1';
    $showZoom = ($__iiifSettings['show_zoom_controls'] ?? '1') === '1';
    $enableFullscreen = ($__iiifSettings['enable_fullscreen'] ?? '1') === '1';
    $bgColor = $__iiifSettings['background_color'] ?? '#000000';
  @endphp

  @if($showOnView)
  <div class="digital-object mb-3">
    @if($mediaType === 'image')
      <a href="{{ $displayUrl }}" target="_blank">
        <img src="{{ $displayUrl }}"
             class="img-fluid rounded"
             alt="{{ $master->name }}"
             style="max-height: {{ $viewerHeight }}; background: {{ $bgColor }};"
             onerror="this.style.display='none'">
      </a>
      <div class="mt-1 text-muted small">{{ $master->name }}</div>
    @elseif($mediaType === 'audio')
      {{-- Issue #85: media_show_controls / autoplay / loop / download
           driven from /admin/ahgSettings/media. media_default_volume is
           applied via the window.AHG_MEDIA init in master.blade.php. --}}
      @php $__media = \App\Support\MediaSettings::payload(); @endphp
      <audio class="w-100"
        @if($__media['show_controls']) controls @endif
        @if($__media['autoplay']) autoplay @endif
        @if($__media['loop']) loop @endif>
        <source src="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" type="{{ $master->mime_type }}">
        Your browser does not support audio playback.
      </audio>
      <div class="mt-1 text-muted small">
        {{ $master->name }}
        @if($__media['show_download'])
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" download
             class="ms-2 small text-decoration-none"><i class="fas fa-download"></i> {{ __('Download') }}</a>
        @endif
      </div>
    @elseif($mediaType === 'video')
      @php $__media = \App\Support\MediaSettings::payload(); @endphp
      <video class="w-100" style="max-height: {{ $viewerHeight }}; background: {{ $bgColor }};"
        @if($__media['show_controls']) controls @endif
        @if($__media['autoplay']) autoplay @endif
        @if($__media['loop']) loop @endif>
        <source src="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" type="{{ $master->mime_type }}">
        Your browser does not support video playback.
      </video>
      <div class="mt-1 text-muted small">
        {{ $master->name }}
        @if($__media['show_download'])
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" download
             class="ms-2 small text-decoration-none"><i class="fas fa-download"></i> {{ __('Download') }}</a>
        @endif
      </div>
    @elseif($mediaType === 'text')
      <div class="card">
        <div class="card-body">
          <i class="fas fa-file-alt fa-3x text-muted"></i>
          <p class="mt-2">{{ $master->name }}</p>
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" class="btn btn-sm atom-btn-white" target="_blank">
            View document
          </a>
        </div>
      </div>
    @else
      <div class="card">
        <div class="card-body text-center">
          <i class="fas fa-file fa-3x text-muted"></i>
          <p class="mt-2">{{ $master->name }}</p>
          <a href="{{ \AhgCore\Services\DigitalObjectService::getUrl($master) }}" class="btn btn-sm atom-btn-white" target="_blank">
            Download
          </a>
        </div>
      </div>
    @endif

    @if($master->mime_type)
      <span class="badge bg-secondary mt-1">{{ $master->mime_type }}</span>
    @endif
    @if($master->byte_size)
      <span class="badge bg-light text-dark mt-1">{{ number_format($master->byte_size / 1024, 1) }} KB</span>
    @endif
  </div>
  @endif {{-- end show_on_view --}}
@endif
