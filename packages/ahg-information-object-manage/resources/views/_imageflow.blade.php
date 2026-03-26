<div
  class="accordion"
  id="atom-digital-object-carousel"
  data-carousel-instructions-text-text-link="{{ __('Clicking this description title link will open the description view page for this digital object. Advancing the carousel above will update this title text.') }}"
  data-carousel-instructions-text-image-link="{{ __('Changing the current slide of this carousel will change the description title displayed in the following carousel. Clicking any image in this carousel will open the related description view page.') }}"
  data-carousel-next-arrow-button-text="{{ __('Next') }}"
  data-carousel-prev-arrow-button-text="{{ __('Previous') }}"
  data-carousel-images-region-label="{{ __('Archival description images carousel') }}"
  data-carousel-title-region-label="{{ __('Archival description title link') }}">
  <div class="accordion-item border-0">
    <h2 class="accordion-header rounded-0 rounded-top border border-bottom-0" id="heading-carousel">
      <button class="accordion-button rounded-0 rounded-top text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-carousel" aria-expanded="true" aria-controls="collapse-carousel">
        <span>{{ __('Image carousel') }}</span>
      </button>
    </h2>
    <div id="collapse-carousel" class="accordion-collapse collapse show" aria-labelledby="heading-carousel">
      <div class="accordion-body bg-secondary px-5 pt-4 pb-3">
        <div id="atom-slider-images" class="mb-0">
          @foreach($thumbnails ?? [] as $item)
            @php
              $itemObject = $item->parent->object ?? null;
              $itemTitle = $itemObject->authorized_form_of_name ?? $itemObject->title ?? '';
              $itemSlug = $itemObject->slug ?? $itemObject->id ?? '';
              $itemAlt = method_exists($item, 'getDigitalObjectAltText') ? $item->getDigitalObjectAltText() : '';
              $altText = $itemAlt ?: strip_tags($itemTitle);
            @endphp
            <a title="{{ $itemTitle }}" href="{{ route('informationobject.show', $itemSlug) }}">
              <img src="{{ $item->getFullPath() }}" class="img-thumbnail mx-2" longdesc="{{ route('informationobject.show', $itemSlug) }}" alt="{{ $altText }}">
            </a>
          @endforeach
        </div>

        <div id="atom-slider-title">
          @foreach($thumbnails ?? [] as $item)
            @php
              $itemObject = $item->parent->object ?? null;
              $itemTitle = $itemObject->authorized_form_of_name ?? $itemObject->title ?? '';
              $itemSlug = $itemObject->slug ?? $itemObject->id ?? '';
            @endphp
            <a href="{{ route('informationobject.show', $itemSlug) }}" class="text-white text-center mt-2 mb-1">
              {{ strip_tags($itemTitle) }}
            </a>
          @endforeach
        </div>

        @if(isset($limit) && isset($total) && $limit < $total)
          <div class="text-white text-center mt-2 mb-1">
            {{ __('Results %1% to %2% of %3%', ['%1%' => 1, '%2%' => $limit, '%3%' => $total]) }}
            <a class="btn atom-btn-outline-light btn-sm ms-2" href="{{ route('informationobject.browse', [
                'ancestor' => $resource->id ?? '',
                'topLod' => false,
                'view' => 'card',
                'onlyMedia' => true,
            ]) }}">{{ __('Show all') }}</a>
          </div>
        @endif
      </div>
    </div>
  </div>
</div>
