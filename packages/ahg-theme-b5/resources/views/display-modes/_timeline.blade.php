{{--
  Timeline View Partial

  @param \Illuminate\Support\Collection|array $items   Items to display (should have date fields)
  @param string $module Module context (route name prefix)
--}}
@php
    $items = $items ?? [];
    $module = $module ?? 'informationobject';

    // Group items by year if they have dates
    $groupedItems = [];
    foreach ($items as $item) {
        $year = 'Unknown';
        if (!empty($item['start_date'])) {
            $year = date('Y', strtotime($item['start_date']));
        } elseif (!empty($item['dates'])) {
            // Try to extract year from dates string
            if (preg_match('/\d{4}/', $item['dates'], $matches)) {
                $year = $matches[0];
            }
        }
        $groupedItems[$year][] = $item;
    }

    // Sort by year descending
    krsort($groupedItems);
@endphp

<div class="display-timeline-view" data-display-container>
    @if(empty($items))
        <p class="text-muted text-center py-4">{{ __('No items to display') }}</p>
    @else
        @foreach($groupedItems as $year => $yearItems)
            <div class="timeline-year mb-4">
                <h4 class="timeline-year-label text-primary mb-3">
                    <i class="bi bi-calendar3 me-2"></i>{{ $year }}
                </h4>

                @foreach($yearItems as $item)
                    <div class="result-item browse-item" data-display-mode="timeline">
                        <div class="timeline-marker"></div>

                        @if(!empty($item['dates']))
                            <div class="timeline-date">{{ $item['dates'] }}</div>
                        @endif

                        <div class="timeline-content">
                            <h5 class="timeline-title">
                                <a href="{{ route($module . '.show', $item['slug']) }}">
                                    {{ $item['title'] ?? $item['slug'] }}
                                </a>
                            </h5>

                            @if(!empty($item['scope_and_content']))
                                <p class="timeline-description">
                                    {{ Str::limit(strip_tags($item['scope_and_content']), 150) }}
                                </p>
                            @endif

                            <div class="timeline-meta text-muted small">
                                @if(!empty($item['reference_code']))
                                    <span class="me-3">
                                        <i class="bi bi-hash"></i> {{ $item['reference_code'] }}
                                    </span>
                                @endif
                                @if(!empty($item['level_of_description']))
                                    <span>
                                        <i class="bi bi-layers"></i> {{ $item['level_of_description'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    @endif
</div>
