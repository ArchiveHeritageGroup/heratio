<div class="row g-3 mb-3 masonry">

@foreach($pager->items() as $hit)
  @php
    $doc = is_object($hit) && method_exists($hit, 'getData') ? $hit->getData() : (array) $hit;
    $title = $doc['title'] ?? '';
  @endphp

  <div class="col-sm-6 col-lg-4 masonry-item">
    <div class="card">
      @if(!empty($doc['hasDigitalObject']))
        @php
          $imagePath = $doc['digitalObject']['thumbnailPath'] ?? $doc['digitalObject']['genericIconPath'] ?? '';
          $altText = $doc['digitalObject']['digitalObjectAltText'] ?? strip_tags($title);
        @endphp
        <a href="{{ route('informationobject.show', $doc['slug'] ?? '') }}">
          <img src="{{ $imagePath }}" alt="{{ $altText }}" class="card-img-top">
        </a>
      @else
        <a class="p-3" href="{{ route('informationobject.show', $doc['slug'] ?? '') }}">
          {{ $title }}
        </a>
      @endif

      <div class="card-body">
        <div class="card-text d-flex align-items-start gap-2">
          <span>{{ $title }}</span>
          @include('ahg-core::partials._clipboard-button', [
              'slug' => $doc['slug'] ?? '',
              'wide' => false,
              'type' => 'informationObject',
          ])
        </div>
      </div>
    </div>
  </div>
@endforeach
</div>
