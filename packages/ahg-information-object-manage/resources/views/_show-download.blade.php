@php
$digitalObjectLabel = config('app.ui_label_digitalobject', 'digital object');
$altTextOpen = __($resource->getDigitalObjectAltText() ?: 'Open original %1%', ['%1%' => $digitalObjectLabel]);
$altTextClosed = __($resource->getDigitalObjectAltText() ?: 'Original %1% not accessible', ['%1%' => $digitalObjectLabel]);
@endphp

@if(isset($link))
  <a href="{{ $link }}" target="_blank">
    <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextOpen }}" class="img-thumbnail">
  </a>
@else
  <img src="{{ $representation->getFullPath() }}" alt="{{ $altTextClosed }}" class="img-thumbnail">
@endif
