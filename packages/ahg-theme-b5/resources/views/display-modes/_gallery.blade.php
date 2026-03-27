{{--
  Gallery View Partial

  @param \Illuminate\Support\Collection|array $items   Items to display
  @param string $module Module context (route name prefix)
--}}
@php
    $items = $items ?? [];
    $module = $module ?? 'informationobject';
@endphp

<div class="display-gallery-view row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" data-display-container>
    @if(empty($items))
        <div class="col-12">
            <p class="text-muted text-center py-4">{{ __('No items to display') }}</p>
        </div>
    @else
        @foreach($items as $item)
            <div class="col">
                <div class="result-item browse-item card h-100" data-display-mode="gallery">
                    @if(!empty($item['thumbnail']))
                        <a href="{{ route($module . '.show', $item['slug']) }}"
                           data-lightbox="gallery"
                           data-title="{{ $item['title'] ?? '' }}">
                            <img src="{{ $item['thumbnail_large'] ?? $item['thumbnail'] }}"
                                 class="card-img-top"
                                 alt="{{ $item['title'] ?? '' }}"
                                 loading="lazy">
                        </a>
                    @else
                        <a href="{{ route($module . '.show', $item['slug']) }}">
                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                                <i class="bi bi-image display-1 text-muted"></i>
                            </div>
                        </a>
                    @endif

                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="{{ route($module . '.show', $item['slug']) }}"
                               class="text-decoration-none">
                                {{ $item['title'] ?? $item['slug'] }}
                            </a>
                        </h5>

                        @if(!empty($item['dates']))
                            <p class="card-text">
                                <small class="text-muted">{{ $item['dates'] }}</small>
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
