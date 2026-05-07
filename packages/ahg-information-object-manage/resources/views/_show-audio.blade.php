@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
$chaptersUsage = config('atom.term.CHAPTERS_ID');
$referenceUsage = config('atom.term.REFERENCE_ID');
$thumbnailUsage = config('atom.term.THUMBNAIL_ID');
@endphp

@if($chaptersUsage == $usageType)

  {{-- Chapters handled internally by player --}}

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
      {{-- #106 Phase 1+4: shared Heratio audio player component. --}}
      @php
        $__media = \App\Support\MediaSettings::payload();
        $__src = asset($representation->getFullPath());
      @endphp
      @include('theme::components.media-player', [
          'type' => 'audio',
          'playerId' => 'ahg-audio-show-' . ($resource->id ?? uniqid()),
          'src' => $__src,
          'mime' => $resource->mimeType,
          'name' => $representation->name ?? '',
          'masterUrl' => $__src,
          'masterMime' => $resource->mimeType,
          'byteSize' => $representation->byte_size ?? null,
          'needsStreaming' => false,
          'showDownload' => $__media['show_download'] ?? true,
      ])
    @endif

  @else
    <div class="text-center">
      <img src="{{ $representation->getFullPath() }}" class="img-thumbnail" alt="">
    </div>
  @endif

  {{-- Issue #85: media_show_download gates the download button alongside
       the existing ACL check. If either is false, the button is hidden. --}}
  @if(isset($link) && \AhgCore\Services\AclService::check($resource->object ?? null, 'readMaster')
      && \App\Support\MediaSettings::showDownload())
    <div class="mt-2">
      <a href="{{ $link }}" class="btn btn-sm btn-outline-secondary">{{ __('Download audio') }}</a>
    </div>
  @endif

@elseif($thumbnailUsage == $usageType)

  @if($iconOnly ?? false)
    @if(isset($link))
      <a href="{{ $link }}">
        <img src="{{ asset('images/play.png') }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
      </a>
    @else
      <img src="{{ asset('images/play.png') }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
    @endif
  @else
    <div class="digitalObject">
      <div class="digitalObjectRep">
        @if(isset($link))
          <a href="{{ $link }}">
            <img src="{{ asset('images/play.png') }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
          </a>
        @else
          <img src="{{ asset('images/play.png') }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
        @endif
      </div>
      <div class="digitalObjectDesc">
        {{ Illuminate\Support\Str::limit($resource->name, 18) }}
      </div>
    </div>
  @endif

@else

  <div class="resource">
    <img src="{{ asset('images/play.png') }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
  </div>

@endif
