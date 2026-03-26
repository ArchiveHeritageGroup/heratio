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
      {{-- Native HTML5 player --}}
      <video controls class="w-100" style="max-height:500px;" preload="metadata">
        <source src="{{ asset($representation->getFullPath()) }}" type="{{ $resource->mimeType }}">
        Your browser does not support video playback.
      </video>
    @endif

  @else
    <div style="text-align: center">
      <img src="{{ $representation->getFullPath() }}" style="border: #999 1px solid" alt="">
    </div>
  @endif

  @if(isset($link) && \AhgCore\Services\AclService::check($resource->object ?? null, 'readMaster'))
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
