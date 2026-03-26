<article class="search-result row g-0 p-3 border-bottom">
  @if(!empty($doc['hasDigitalObject']))
    @php
      $imagePath = $doc['digitalObject']['thumbnailPath']
          ?: ('/images/generic-icon-' . ($doc['digitalObject']['mediaTypeId'] ?? 'default') . '.png');
      $altText = $doc['digitalObject']['digitalObjectAltText']
          ?? strip_tags($doc['i18n'][$culture]['authorizedFormOfName'] ?? '');
    @endphp
    <div class="col-12 col-lg-3 pb-2 pb-lg-0 pe-lg-3">
      <a href="{{ route('actor.show', ['slug' => $doc['slug']]) }}">
        <img src="{{ $imagePath }}" alt="{{ $altText }}" class="img-thumbnail">
      </a>
    </div>
  @endif

  <div class="col-12{{ empty($doc['hasDigitalObject']) ? '' : ' col-lg-9' }} d-flex flex-column gap-1">
    <div class="d-flex align-items-center gap-2 mw-100">
      @php
        $name = $doc['i18n'][$culture]['authorizedFormOfName'] ?? $doc['authorizedFormOfName'] ?? __('Untitled');
      @endphp
      <a href="{{ route('actor.show', ['slug' => $doc['slug']]) }}" class="h5 mb-0 text-truncate">
        {{ $name }}
      </a>

      @include('ahg-search::_clipboard-button', [
          'slug' => $doc['slug'],
          'type' => $clipboardType,
          'wide' => false,
      ])
    </div>

    <div class="d-flex flex-column gap-2">
      <div class="d-flex flex-wrap">
        @php $showDash = false; @endphp
        @if(!empty($doc['descriptionIdentifier']))
          <span class="text-primary">
            {{ $doc['descriptionIdentifier'] }}
          </span>
          @php $showDash = true; @endphp
        @endif

        @if(!empty($doc['entityTypeId']))
          @php $termName = \AhgCore\Services\CacheService::getLabel($doc['entityTypeId'], 'term'); @endphp
          @if($termName)
            @if($showDash)
              <span class="text-muted mx-2"> &middot; </span>
            @endif
            <span class="text-muted">
              {{ $termName }}
            </span>
            @php $showDash = true; @endphp
          @endif
        @endif

        @php $dates = $doc['i18n'][$culture]['datesOfExistence'] ?? ''; @endphp
        @if(strlen($dates) > 0)
          @if($showDash)
            <span class="text-muted mx-2"> &middot; </span>
          @endif
          <span class="text-muted">
            {{ $dates }}
          </span>
        @endif
      </div>

      @php $history = $doc['i18n'][$culture]['history'] ?? ''; @endphp
      @if(strlen($history) > 0)
        <span class="text-block d-none">
          {!! $history !!}
        </span>
      @endif
    </div>
  </div>
</article>
