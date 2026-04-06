@php
$chaptersUsage = config('atom.term.CHAPTERS_ID');
$subtitlesUsage = config('atom.term.SUBTITLES_ID');
$isTrack = ($chaptersUsage == $usageType || $subtitlesUsage == $usageType);

// Determine which show component to include based on media type
$showComponent = $showComponent ?? '_show-generic-icon';
$componentView = 'ahg-information-object-manage::' . $showComponent;
@endphp

@if($isTrack)
  @if(!empty($accessWarning))
    <div class="access-warning">
      {{ $accessWarning }}
    </div>
  @else
    @include($componentView, [
        'iconOnly' => $iconOnly ?? false,
        'link' => $link ?? null,
        'resource' => $digitalObject,
        'usageType' => $usageType,
        'representation' => $representation ?? $digitalObject,
    ])
  @endif
@else
  <div class="digital-object-reference text-center{{ ($editForm ?? false) ? '' : ' p-3 border-bottom' }}">
    @if(!empty($accessWarning))
      <div class="access-warning">
        {{ $accessWarning }}
      </div>
    @else
      @include($componentView, [
          'iconOnly' => $iconOnly ?? false,
          'link' => $link ?? null,
          'resource' => $digitalObject,
          'usageType' => $usageType,
          'representation' => $representation ?? $digitalObject,
      ])
    @endif
  </div>
@endif
