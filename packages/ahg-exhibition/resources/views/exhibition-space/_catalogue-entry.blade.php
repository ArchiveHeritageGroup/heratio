{{--
  A single catalogue entry: image (or a graceful no-image placeholder) + entry
  number, title, creator / date attribution line, wall text / caption, and a link
  to the full record. Every field degrades to a sensible fallback when absent.

  Param:
    $entry - one entry array from ExhibitionSpaceService::getCatalogueEntries()

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3.0 or later.
--}}
@php
  // Attribution line: "Creator, date" with whichever parts are present.
  $attribParts = array_filter([
      trim((string) ($entry['creator'] ?? '')),
      trim((string) ($entry['date'] ?? '')),
  ]);
  $attrib = implode(', ', $attribParts);
@endphp
<article class="cat-entry">
  @if(!empty($entry['image_url']))
    <figure class="cat-entry-figure">
      <img src="{{ $entry['image_url'] }}" alt="{{ $entry['title'] }}" loading="lazy">
    </figure>
  @else
    <div class="cat-entry-noimg" aria-hidden="true">{{ __('No image available') }}</div>
  @endif
  <div class="cat-entry-body">
    <p class="cat-entry-no">{{ __('No.') }} {{ $entry['position'] }}</p>
    <h3 class="cat-entry-title">{{ $entry['title'] }}</h3>
    @if(!empty($attribParts))
      <p class="cat-entry-attrib">{{ $attrib }}</p>
    @endif
    <p class="cat-entry-caption">{{ $entry['caption'] ?? __('No description is recorded for this object.') }}</p>
    @if(!empty($entry['record_url']))
      <p class="cat-entry-record">
        <a href="{{ $entry['record_url'] }}">{{ __('View full record') }}</a>
      </p>
    @endif
  </div>
</article>
