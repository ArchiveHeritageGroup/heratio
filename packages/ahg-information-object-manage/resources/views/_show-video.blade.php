@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
$masterUsage = config('atom.term.MASTER_ID');
$chaptersUsage = config('atom.term.CHAPTERS_ID');
$subtitlesUsage = config('atom.term.SUBTITLES_ID');
$referenceUsage = config('atom.term.REFERENCE_ID');
$thumbnailUsage = config('atom.term.THUMBNAIL_ID');
@endphp

@if($masterUsage == $usageType)

  @if(isset($link))
    <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}">
  @else
    <a href="{{ $link }}">
      <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}">
    </a>
  @endif

@elseif($chaptersUsage == $usageType)

  {{-- Chapters handled internally by player --}}

@elseif($subtitlesUsage == $usageType)

  {{-- Subtitles handled internally by player --}}

@elseif($referenceUsage == $usageType)

  @if($showMediaPlayer ?? false)

    @if(function_exists('render_media_player'))
      {{-- ahgIiifPlugin enabled - use AhgMediaPlayer JS player --}}
      @php echo render_media_player([
          'id' => $resource->id,
          'name' => $resource->name,
          'path' => $resource->path,
          'mimeType' => $resource->mimeType,
          'mediaTypeId' => $resource->mediaTypeId ?? null,
          'object_id' => $resource->object->id ?? $resource->objectId ?? 0,
      ]); @endphp
    @else
      {{-- #106 Phase 2+4: shared Heratio video player component on the
           reference-derivative path. media_show_controls / autoplay /
           loop are honoured inside the component via
           App\Support\MediaSettings::payload(). --}}
      @php
        $__videoSrc = asset($representation->getFullPath());
        $__byteSize = $resource->byte_size ?? null;
      @endphp
      @include('theme::components.media-player', [
          'type'           => 'video',
          'playerId'       => 'ahg-video-ref-' . ($resource->id ?? uniqid()),
          'src'            => $__videoSrc,
          'mime'           => $resource->mimeType,
          'name'           => $resource->name ?? '',
          'masterUrl'      => $link ?? $__videoSrc,
          'masterMime'     => $resource->mimeType,
          'byteSize'       => $__byteSize,
          'needsStreaming' => false,
          'showDownload'   => false,
          'poster'         => null,
      ])
    @endif

  @else
    <div style="text-align: center">
      <img src="{{ $representation->getFullPath() }}" style="border: #999 1px solid" alt="">
    </div>
  @endif

  {{-- Issue #85: media_show_download gates the download button alongside
       the existing ACL check. The component-internal download is disabled
       (showDownload=false above) so guests with readMaster permission
       still see this anchor. --}}
  @if(isset($link) && \AhgCore\Services\AclService::check($resource->object ?? null, 'readMaster')
      && \App\Support\MediaSettings::showDownload())
    <div class="mt-2">
      <a href="{{ $link }}" class="btn btn-sm btn-outline-secondary">{{ __('Download video') }}</a>
    </div>
  @endif

@elseif($thumbnailUsage == $usageType)

  @if($iconOnly ?? false)
    @if(isset($link))
      <a href="{{ $link }}">
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}">
      </a>
    @else
      <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}">
    @endif
  @else
    <div class="digitalObject">
      <div class="digitalObjectRep">
        @if(isset($link))
          <a href="{{ $link }}">
            <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}">
          </a>
        @else
          <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}">
        @endif
      </div>
      <div class="digitalObjectDesc">
        {{ Illuminate\Support\Str::limit($resource->name, 18) }}
      </div>
    </div>
  @endif

@endif
