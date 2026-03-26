@php $doc = $hit->getData(); @endphp

<article class="search-result row g-0 p-3 border-bottom">
  @if(!empty($doc['hasDigitalObject']))
    @php // Get thumbnail or generic icon path
        $imagePath = '';
        if (
            isset($doc['digitalObject']['thumbnailPath'])
            && !empty($doc['digitalObject']['thumbnailPath'])
        ) {
            $imagePath = $doc['digitalObject']['thumbnailPath'];
        } else {
            $imagePath = '/images/generic-icon-' . ($doc['digitalObject']['mediaTypeId'] ?? 'default') . '.png';
        }
        $altText = $doc['digitalObject']['digitalObjectAltText']
            ?? strip_tags($doc['i18n'][$culture]['title'] ?? $doc['title'] ?? '');
    @endphp
    <div class="col-12 col-lg-3 pb-2 pb-lg-0 pe-lg-3">
      <a href="{{ route('informationobject.show', ['slug' => $doc['slug']]) }}">
        <img src="{{ $imagePath }}" alt="{{ $altText }}" class="img-thumbnail">
      </a>
    </div>
  @endif

  <div class="col-12{{ empty($doc['hasDigitalObject']) ? '' : ' col-lg-9' }} d-flex flex-column gap-1">
    <div class="d-flex align-items-center gap-2">
      @php
        $title = $doc['i18n'][$culture]['title'] ?? $doc['title'] ?? __('Untitled');
      @endphp
      <a href="{{ route('informationobject.show', ['slug' => $doc['slug']]) }}" class="h5 mb-0 text-truncate">
        {{ $title }}
      </a>

      @include('ahg-search::_clipboard-button', ['slug' => $doc['slug'], 'type' => 'informationObject', 'wide' => false])
    </div>

    <div class="d-flex flex-column gap-2">
      <div class="d-flex flex-column">
        <div class="d-flex flex-wrap">
          @php $showDash = false; @endphp
          @if(
              config('atom.inherit_code_informationobject', true)
              && isset($doc['referenceCode']) && !empty($doc['referenceCode'])
          )
            <span class="text-primary">{{ $doc['referenceCode'] }}</span>
            @php $showDash = true; @endphp
          @elseif(isset($doc['identifier']) && !empty($doc['identifier']))
            <span class="text-primary">{{ $doc['identifier'] }}</span>
            @php $showDash = true; @endphp
          @endif

          @if(
              isset($doc['levelOfDescriptionId'])
              && !empty($doc['levelOfDescriptionId'])
          )
            @if($showDash)
              <span class="text-muted mx-2"> &middot; </span>
            @endif
            <span class="text-muted">
              {{ \AhgCore\Services\CacheService::getLabel($doc['levelOfDescriptionId'], 'term') }}
            </span>
            @php $showDash = true; @endphp
          @endif

          @if(isset($doc['dates']))
            @php $date = $doc['dates'][0]['date'] ?? ''; @endphp
            @if(!empty($date))
              @if($showDash)
                <span class="text-muted mx-2"> &middot; </span>
              @endif
              <span class="text-muted">
                {{ $date }}
              </span>
              @php $showDash = true; @endphp
            @endif
          @endif

          @if(
              isset($doc['publicationStatusId'])
              && $doc['publicationStatusId'] == \AhgCore\Constants\QubitTerm::PUBLICATION_STATUS_DRAFT_ID
          )
            @if($showDash)
              <span class="text-muted mx-2"> &middot; </span>
            @endif
            <span class="text-muted">
              {{ \AhgCore\Services\CacheService::getLabel($doc['publicationStatusId'], 'term') }}
            </span>
          @endif
        </div>

        @if(isset($doc['partOf']))
          <span class="text-muted">
            {{ __('Part of ') }}
            @php
              $partOfTitle = $doc['partOf']['i18n'][$culture]['title'] ?? $doc['partOf']['title'] ?? __('Untitled');
            @endphp
            <a href="{{ route('informationobject.show', ['slug' => $doc['partOf']['slug']]) }}">
              {{ $partOfTitle }}
            </a>
          </span>
        @endif
      </div>

      @php
        $scopeAndContent = $doc['i18n'][$culture]['scopeAndContent'] ?? null;
      @endphp
      @if(null !== $scopeAndContent)
        <span class="text-block d-none">
          {!! $scopeAndContent !!}
        </span>
      @endif

      @if(isset($doc['creators']))
        @php
          $creatorNames = collect($doc['creators'])->pluck('i18n.' . $culture . '.authorizedFormOfName')->filter()->implode(', ');
        @endphp
        @if(!empty($creatorNames))
          <span class="text-muted">
            {{ $creatorNames }}
          </span>
        @endif
      @endif
    </div>
  </div>
</article>
