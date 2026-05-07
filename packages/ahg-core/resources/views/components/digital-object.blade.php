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
      {{-- #106 Phase 1+4: shared Heratio audio player component. Replaces
           the previous bare <audio controls> rendering used by the sector
           show pages (museum / actor / repository / dam etc.) so audio
           chrome is consistent across every surface. The component
           respects media_show_download via $showDownload + the media_*
           runtime hooks set up by master.blade.php's window.AHG_MEDIA. --}}
      @php
        $__media = \App\Support\MediaSettings::payload();
        $__masterUrl = \AhgCore\Services\DigitalObjectService::getUrl($master);
      @endphp
      @include('theme::components.media-player', [
          'type' => 'audio',
          'playerId' => 'ahg-audio-' . ($master->id ?? uniqid()),
          'src' => $__masterUrl,
          'mime' => $master->mime_type,
          'name' => $master->name ?? '',
          'masterUrl' => $__masterUrl,
          'masterMime' => $master->mime_type,
          'byteSize' => $master->byte_size ?? null,
          'needsStreaming' => false,
          'showDownload' => $__media['show_download'] ?? true,
      ])
    @elseif($mediaType === 'video')
      {{-- #106 Phase 2+4: shared Heratio video player component. Replaces
           the previous bare <video controls> rendering used by sector
           show pages (museum / actor / repository / dam etc.) so video
           chrome is consistent across every surface. The component
           respects media_show_download via $showDownload and reads the
           operator's media_player_type to pick the right layout. --}}
      @php
        $__media     = \App\Support\MediaSettings::payload();
        $__masterUrl = \AhgCore\Services\DigitalObjectService::getUrl($master);
      @endphp
      @include('theme::components.media-player', [
          'type'           => 'video',
          'playerId'       => 'ahg-video-' . ($master->id ?? uniqid()),
          'src'            => $__masterUrl,
          'mime'           => $master->mime_type,
          'name'           => $master->name ?? '',
          'masterUrl'      => $__masterUrl,
          'masterMime'     => $master->mime_type,
          'byteSize'       => $master->byte_size ?? null,
          'needsStreaming' => false,
          'showDownload'   => $__media['show_download'] ?? true,
          'poster'         => $thumbnailUrl ?? null,
      ])
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
