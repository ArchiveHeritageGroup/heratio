{{-- Block: Image Carousel (migrated from ahgLandingPagePlugin) --}}
@php
$collectionSlug = $config['collection_id'] ?? null;
$maxItems = (int)($config['limit'] ?? 12) ?: 12;
$height = $config['height'] ?? '450px';
$autoplay = $config['auto_play'] ?? true;
$interval = (int)($config['interval'] ?? 5000) ?: 5000;
$showTitle = !empty($config['title']);
$showCaptions = $config['show_captions'] ?? true;
$showViewAll = $config['show_view_all'] ?? false;
$customTitle = $config['title'] ?? null;
$customSubtitle = $config['subtitle'] ?? null;
$carouselId = 'carousel-' . uniqid();
@endphp

@if (!$collectionSlug)
  <p class="text-muted">No collection configured. Edit this block to select an IIIF collection.</p>
@else
  @if ($showTitle && $customTitle)
    <h2 class="h4 mb-3">{{ e($customTitle) }}</h2>
    @if ($customSubtitle)
      <p class="text-muted mb-3">{{ e($customSubtitle) }}</p>
    @endif
  @endif

  <div id="{{ $carouselId }}" class="carousel slide" @if($autoplay) data-bs-ride="carousel" data-bs-interval="{{ $interval }}" @endif>
    <div class="carousel-inner" style="height: {{ e($height) }};">
      {{-- Carousel items loaded from IIIF collection data --}}
      @if (!empty($data))
        @foreach ($data as $index => $item)
          <div class="carousel-item {{ $index === 0 ? 'active' : '' }}">
            <img src="{{ e($item['image_url'] ?? '') }}" class="d-block w-100 h-100" style="object-fit: cover;" alt="{{ e($item['title'] ?? '') }}">
            @if ($showCaptions && !empty($item['title']))
              <div class="carousel-caption d-none d-md-block">
                <h5>{{ e($item['title']) }}</h5>
              </div>
            @endif
          </div>
        @endforeach
      @else
        <div class="carousel-item active">
          <div class="d-flex align-items-center justify-content-center h-100 bg-light">
            <div class="text-center text-muted">
              <i class="bi bi-images display-1"></i>
              <p class="mt-2">Collection: {{ e($collectionSlug) }}</p>
            </div>
          </div>
        </div>
      @endif
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">{{ __('Previous') }}</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#{{ $carouselId }}" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">{{ __('Next') }}</span>
    </button>
  </div>

  @if ($showViewAll && $collectionSlug)
    <div class="text-center mt-3">
      @php
        // #1478: this was route('iiif-collection.show'), which does not exist -
        // there is no such route name in ahg-iiif-collection. It never threw
        // because computed_data was always null, so the carousel loop never ran
        // and this line was never reached. Populating the data exposed it: the
        // empty data was hiding a broken route the same way a ?? fallback hides
        // a wrong column.
        //
        // iiif-collection.view takes an {id}; config['collection_id'] holds a
        // SLUG here ("mobrey-family-archive-3"), so the slug-taking route is
        // iiif.viewer. Guarded with Route::has so a landing page cannot 500 on
        // an instance where ahg-iiif-collection is not installed - it falls
        // back to the record page, which always exists.
        $viewAllUrl = \Illuminate\Support\Facades\Route::has('iiif.viewer')
            ? route('iiif.viewer', ['slug' => $collectionSlug])
            : url('/'.$collectionSlug);
      @endphp
      <a href="{{ $viewAllUrl }}" class="btn btn-outline-primary">
        View All <i class="bi bi-arrow-right"></i>
      </a>
    </div>
  @endif
@endif
