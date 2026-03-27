{{--
  List View Partial

  @param \Illuminate\Support\Collection|array $items   Items to display
  @param string $module Module context (route name prefix)
--}}
@php
    $items = $items ?? [];
    $module = $module ?? 'informationobject';
@endphp

<div class="display-list-view" data-display-container>
    @if(empty($items))
        <p class="text-muted text-center py-4">{{ __('No items to display') }}</p>
    @else
        @foreach($items as $item)
            <div class="result-item browse-item" data-display-mode="list">
                @if(!empty($item['thumbnail']))
                    <img src="{{ $item['thumbnail'] }}"
                         alt=""
                         class="item-thumbnail"
                         loading="lazy">
                @else
                    <div class="item-thumbnail d-flex align-items-center justify-content-center bg-light">
                        <i class="bi bi-file-earmark text-muted"></i>
                    </div>
                @endif

                <div class="item-content">
                    <h3 class="item-title">
                        <a href="{{ route($module . '.show', $item['slug']) }}">
                            {{ $item['title'] ?? $item['slug'] }}
                        </a>
                    </h3>

                    <div class="item-meta">
                        @if(!empty($item['reference_code']))
                            <span class="me-3">
                                <i class="bi bi-hash me-1"></i>{{ $item['reference_code'] }}
                            </span>
                        @endif

                        @if(!empty($item['dates']))
                            <span class="me-3">
                                <i class="bi bi-calendar me-1"></i>{{ $item['dates'] }}
                            </span>
                        @endif

                        @if(!empty($item['level_of_description']))
                            <span>
                                <i class="bi bi-layers me-1"></i>{{ $item['level_of_description'] }}
                            </span>
                        @endif
                    </div>

                    @if(!empty($item['scope_and_content']))
                        <p class="item-description">
                            {{ Str::limit(strip_tags($item['scope_and_content']), 200) }}
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>
