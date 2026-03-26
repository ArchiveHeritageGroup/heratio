@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
$masterUsage = config('atom.term.MASTER_ID');
$referenceUsage = config('atom.term.REFERENCE_ID');
$thumbnailUsage = config('atom.term.THUMBNAIL_ID');
@endphp

@if($masterUsage == $usageType || $referenceUsage == $usageType)

  @if(isset($link))
    <a href="{{ $link }}" target="_blank">
      <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
    </a>
  @else
    <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
  @endif

@elseif($thumbnailUsage == $usageType)

  @if($iconOnly ?? false)
    @if(isset($link))
      <a href="{{ $link }}">
        <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
      </a>
    @else
      <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
    @endif

  @else

    <div class="digitalObject">

      <div class="digitalObjectRep">
        @if(isset($link))
          <a href="{{ $link }}">
            <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
          </a>
        @else
          <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
        @endif
      </div>

      <div class="digitalObjectDesc">
        {{ Illuminate\Support\Str::limit($resource->name, 18) }}
      </div>

    </div>

  @endif

@endif
