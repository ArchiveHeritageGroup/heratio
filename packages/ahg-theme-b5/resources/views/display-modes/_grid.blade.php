{{--
  Grid View Partial

  @param \Illuminate\Support\Collection|array $items   Items to display
  @param string $module Module context (route name prefix)
--}}
@php
    $items = $items ?? [];
    $module = $module ?? 'informationobject';
@endphp

<div class="display-grid-view row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3" data-display-container>
    @if(empty($items))
        <div class="col-12">
            <p class="text-muted text-center py-4">{{ __('No items to display') }}</p>
        </div>
    @else
        @foreach($items as $item)
            <div class="col">
                <div class="result-item browse-item card h-100" data-display-mode="grid">
                    @if(!empty($item['thumbnail']))
                        <img src="{{ $item['thumbnail'] }}"
                             class="card-img-top"
                             alt="{{ $item['title'] ?? '' }}"
                             loading="lazy">
                    @else
                        <div class="card-img-top d-flex align-items-center justify-content-center bg-light">
                            <i class="bi bi-file-earmark display-4 text-muted"></i>
                        </div>
                    @endif

                    <div class="card-body">
                        <h5 class="card-title">
                            <a href="{{ route($module . '.show', $item['slug']) }}"
                               class="stretched-link text-decoration-none">
                                {{ $item['title'] ?? $item['slug'] }}
                            </a>
                        </h5>

                        <p class="card-text">
                            @if(!empty($item['reference_code']))
                                <small>{{ $item['reference_code'] }}</small><br>
                            @endif
                            @if(!empty($item['dates']))
                                <small>{{ $item['dates'] }}</small>
                            @endif
                        </p>
                    </div>

                    @if(!empty($item['level_of_description']))
                        <div class="card-footer bg-transparent border-top-0">
                            <small class="text-muted">
                                <i class="bi bi-layers me-1"></i>
                                {{ $item['level_of_description'] }}
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
