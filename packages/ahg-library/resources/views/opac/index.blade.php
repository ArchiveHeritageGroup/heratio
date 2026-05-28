@extends('theme::layouts.1col')
@section('title', 'OPAC — ' . __('Online Public Access Catalogue'))

@php
    // Helpers
    $q           = request()->query('q', '');
    $sort        = request()->query('sort', 'relevance');
    $perPage     = request()->query('per_page', 30);
    $activeFacets = collect([
        'material_type' => request()->query('material_type'),
        'language'      => request()->query('language'),
        'creator'       => request()->query('creator'),
        'publisher'     => request()->query('publisher'),
        'year_from'     => request()->query('year_from'),
        'year_to'       => request()->query('year_to'),
    ])->filter()->mapWithKeys(fn($v, $k) => [$k => $v]);

    // Preserve all existing query params for facet links, removing the one being toggled
    function facetUrl($key, $value, $currentFacets, $q, $sort) {
        $params = array_merge(request()->query(), ['q' => $q, 'sort' => $sort, 'page' => 1]);
        unset($params['material_type'], $params['language'], $params['creator'],
              $params['publisher'], $params['year_from'], $params['year_to']);

        if ($key === 'year_from' || $key === 'year_to') {
            unset($params['year_from'], $params['year_to']);
        }

        if (request()->query($key) == $value) {
            // Toggle off
        } else {
            $params[$key] = $value;
        }

        return route('library.opac', array_filter($params, fn($v) => $v !== null && $v !== ''));
    }

    function clearFacetsUrl($q, $sort) {
        $params = ['q' => $q, 'sort' => $sort, 'page' => 1];
        return route('library.opac', array_filter($params, fn($v) => $v !== null && $v !== ''));
    }

    $sortOptions = [
        'relevance'   => __('Relevance'),
        'title_asc'   => __('Title A–Z'),
        'title_desc'  => __('Title Z–A'),
        'year_desc'   => __('Newest first'),
        'year_asc'    => __('Oldest first'),
        'popular'     => __('Most borrowed'),
    ];

    $materialTypeLabels = [
        'monograph'   => __('Monograph'),
        'periodical'  => __('Periodical / Serial'),
        'electronic'  => __('Electronic'),
        'map'         => __('Map'),
        'audiovisual' => __('Audio-visual'),
        'manuscript'  => __('Manuscript'),
        'kit'         => __('Kit / Package'),
        'other'       => __('Other'),
    ];
@endphp

@section('content')
<div class="container py-4">

    {{-- Page heading --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="h4 mb-0">
            <i class="fas fa-book-open me-2 text-primary"></i>
            {{ __('Online Public Access Catalogue') }}
        </h1>
        @if($es_mode ?? false)
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="fas fa-bolt me-1"></i>{{ __('Elasticsearch') }}
            </span>
        @endif
    </div>

    {{-- Search form --}}
    <form method="get" action="{{ route('library.opac') }}" class="mb-4" id="opac-search-form">
        <div class="row g-2 align-items-end">
            <div class="col">
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $q }}"
                        class="form-control form-control-lg"
                        placeholder="{{ __('Search title, author, ISBN, call number...') }}"
                        autofocus
                    >
                </div>
            </div>
            <div class="col-auto">
                <select name="sort" class="form-select" onchange="document.getElementById('opac-search-form').submit()">
                    @foreach($sortOptions as $val => $label)
                        <option value="{{ $val }}" {{ $sort === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-search me-1"></i>{{ __('Search') }}
                </button>
            </div>
        </div>

        {{-- Hidden facet fields preserved from current URL --}}
        @foreach(['material_type', 'language', 'creator', 'publisher', 'year_from', 'year_to'] as $fKey)
            @if(request()->query($fKey) && !in_array($fKey, ['material_type', 'language', 'creator', 'publisher', 'year_from', 'year_to']))
                {{-- kept as hidden --}}
            @endif
        @endforeach
    </form>

    <div class="row">

        {{-- ── Facet sidebar ─────────────────────────────────────────────── --}}
        @if(($results && $facets) || $activeFacets->isNotEmpty())
        <aside class="col-lg-3 mb-4">

            {{-- Active filters --}}
            @if($activeFacets->isNotEmpty())
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary text-white py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold"><i class="fas fa-filter me-1"></i>{{ __('Active filters') }}</span>
                            <a href="{{ clearFacetsUrl($q, $sort) }}" class="text-white text-decoration-none small">
                                {{ __('Clear all') }}
                            </a>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach($activeFacets as $key => $val)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1">
                                <span>
                                    <strong>{{ match($key) {
                                        'material_type' => __('Type'),
                                        'language' => __('Language'),
                                        'creator' => __('Author'),
                                        'publisher' => __('Publisher'),
                                        'year_from' => __('From year'),
                                        'year_to' => __('To year'),
                                        default => $key
                                    } }}:</strong>
                                    {{ match($key) {
                                        'material_type' => $materialTypeLabels[$val] ?? $val,
                                        'year_from', 'year_to' => $val,
                                        default => $val
                                    } }}
                                </span>
                                <a href="{{ clearFacetsUrl($q, $sort) }}"
                                   class="text-danger text-decoration-none"
                                   title="{{ __('Remove filter') }}">
                                    <i class="fas fa-times"></i>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Material type facet --}}
            @if(!empty($facets['material_types']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Format / Type') }}</h6>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach($facets['material_types'] as $facet)
                            @php $isActive = request()->query('material_type') == $facet['value']; @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1
                                {{ $isActive ? 'list-group-item-primary' : '' }}">
                                <a href="{{ facetUrl('material_type', $facet['value'], $activeFacets, $q, $sort) }}"
                                   class="{{ $isActive ? 'fw-bold text-primary' : 'text-secondary' }} text-decoration-none flex-grow-1">
                                    {{ $materialTypeLabels[$facet['value']] ?? ucfirst($facet['value']) }}
                                </a>
                                <span class="badge bg-light text-dark border ms-2">{{ $facet['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Language facet --}}
            @if(!empty($facets['languages']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Language') }}</h6>
                    </div>
                    <ul class="list-group list-group-flush small" style="max-height:200px;overflow-y:auto">
                        @foreach($facets['languages'] as $facet)
                            @php $isActive = request()->query('language') == $facet['value']; @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1
                                {{ $isActive ? 'list-group-item-primary' : '' }}">
                                <a href="{{ facetUrl('language', $facet['value'], $activeFacets, $q, $sort) }}"
                                   class="{{ $isActive ? 'fw-bold text-primary' : 'text-secondary' }} text-decoration-none flex-grow-1">
                                    {{ $facet['label'] }}
                                </a>
                                <span class="badge bg-light text-dark border ms-2">{{ $facet['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Publication year facet --}}
            @if(!empty($facets['publication_years']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Publication year') }}</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-1">
                            <div class="col-6">
                                <input type="number" name="year_from" class="form-control form-control-sm"
                                       placeholder="{{ __('From') }}"
                                       value="{{ request()->query('year_from') }}"
                                       min="1000" max="{{ date('Y') }}"
                                       onchange="this.form.submit()">
                            </div>
                            <div class="col-6">
                                <input type="number" name="year_to" class="form-control form-control-sm"
                                       placeholder="{{ __('To') }}"
                                       value="{{ request()->query('year_to') }}"
                                       min="1000" max="{{ date('Y') }}"
                                       onchange="this.form.submit()">
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">
                            @foreach(collect($facets['publication_years'])->take(5) as $facet)
                                <a href="{{ facetUrl('year_from', $facet['value'], $activeFacets, $q, $sort) }}"
                                   class="text-decoration-none me-2 {{ request()->query('year_from') == $facet['value'] ? 'fw-bold text-primary' : 'text-secondary' }}">
                                    {{ $facet['label'] }} ({{ $facet['count'] }})
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Creator / Author facet --}}
            @if(!empty($facets['creators']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Author / Creator') }}</h6>
                    </div>
                    <ul class="list-group list-group-flush small" style="max-height:200px;overflow-y:auto">
                        @foreach($facets['creators'] as $facet)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1">
                                <a href="{{ facetUrl('creator', $facet['value'], $activeFacets, $q, $sort) }}"
                                   class="text-secondary text-decoration-none flex-grow-1 text-truncate"
                                   title="{{ $facet['value'] }}">
                                    {{ Str::limit($facet['value'], 30) }}
                                </a>
                                <span class="badge bg-light text-dark border ms-2">{{ $facet['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Publisher facet --}}
            @if(!empty($facets['publishers']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Publisher') }}</h6>
                    </div>
                    <ul class="list-group list-group-flush small" style="max-height:200px;overflow-y:auto">
                        @foreach($facets['publishers'] as $facet)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1">
                                <a href="{{ facetUrl('publisher', $facet['value'], $activeFacets, $q, $sort) }}"
                                   class="text-secondary text-decoration-none flex-grow-1 text-truncate"
                                   title="{{ $facet['value'] }}">
                                    {{ Str::limit($facet['value'], 30) }}
                                </a>
                                <span class="badge bg-light text-dark border ms-2">{{ $facet['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Availability facet --}}
            @if(!empty($facets['availability']))
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h6 class="mb-0 text-secondary">{{ __('Availability') }}</h6>
                    </div>
                    <ul class="list-group list-group-flush small">
                        @foreach($facets['availability'] as $facet)
                            <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-1">
                                <span class="{{ match($facet['value']) {
                                    'available' => 'text-success',
                                    'checked_out' => 'text-danger',
                                    'on_hold' => 'text-warning',
                                    default => 'text-muted'
                                } }">
                                    <i class="fas fa-{{ match($facet['value']) {
                                        'available' => 'check-circle',
                                        'checked_out' => 'times-circle',
                                        'on_hold' => 'clock',
                                        default => 'question-circle'
                                    } }} me-1"></i>
                                    {{ $facet['label'] }}
                                </span>
                                <span class="badge bg-light text-dark border">{{ $facet['count'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

        </aside>
        @endif

        {{-- ── Results column ─────────────────────────────────────────────── --}}
        <div class="{{ (($results && $facets) || $activeFacets->isNotEmpty()) ? 'col-lg-9' : 'col-12' }}">

            {{-- Results count + pagination info --}}
            @if($results)
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0 text-muted small">
                        @if($results->total() > 0)
                            <strong>{{ number_format($results->total()) }}</strong>
                            {{ __('result(s)') }}
                            @if($q)
                                {{ __('for') }} <em>"{{ e($q) }}"</em>
                            @endif
                        @else
                            {{ __('No results found.') }}
                        @endif
                    </p>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small">{{ __('Per page:') }}</span>
                        <select class="form-select form-select-sm" style="width:auto"
                                onchange="window.location.href='{{ request()->url() }}?q={{ urlencode($q) }}&sort={{ $sort }}&per_page=' + this.value + '{{ $activeFacets->isNotEmpty() ? '&' . http_build_query($activeFacets->toArray()) : '' }}'">
                            @foreach([10, 20, 30, 50, 100] as $n)
                                <option value="{{ $n }}" {{ $perPage == $n ? 'selected' : '' }}>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Result cards --}}
                @forelse($results as $item)
                    <div class="card mb-3 shadow-sm">
                        <div class="card-body">
                            <div class="row">

                                {{-- Cover thumbnail --}}
                                @if(!empty($item->cover_url) && ($settings['show_covers'] ?? true))
                                    <div class="col-auto">
                                        <a href="{{ route('library.opac-view', $item->slug ?? $item->id) }}">
                                            <img src="{{ $item->cover_url }}"
                                                 alt="{{ e($item->title) }}"
                                                 class="img-thumbnail"
                                                 style="max-width:80px;max-height:120px;object-fit:contain">
                                        </a>
                                    </div>
                                @endif

                                {{-- Metadata --}}
                                <div class="col">
                                    <h5 class="mb-1">
                                        <a href="{{ route('library.opac-view', $item->slug ?? $item->id) }}"
                                           class="text-decoration-none">
                                            {!! $item->highlighted_title ?? e($item->title) !!}
                                        </a>
                                    </h5>

                                    @if($item->creator)
                                        <p class="mb-1 text-secondary small">
                                            <i class="fas fa-user me-1"></i>
                                            {{ e($item->creator) }}
                                        </p>
                                    @endif

                                    <p class="mb-1 small">
                                        @if($item->publisher)
                                            <span class="text-muted">{{ $item->publisher }}</span>
                                            @if($item->publication_year)
                                                <span class="text-muted"> — </span>
                                            @endif
                                        @endif
                                        @if($item->publication_year)
                                            <span class="text-muted">{{ $item->publication_year }}</span>
                                        @endif
                                    </p>

                                    @if($item->isbn)
                                        <p class="mb-0 small text-muted">
                                            <i class="fas fa-barcode me-1"></i>ISBN: {{ $item->isbn }}
                                        </p>
                                    @endif

                                    @if($item->summary || $item->highlighted_summary)
                                        <p class="mt-2 mb-0 small text-secondary">
                                            {!! Str::limit(strip_tags($item->highlighted_summary ?? $item->summary), 250) !!}
                                        </p>
                                    @endif
                                </div>

                                {{-- Side metadata --}}
                                <div class="col-auto text-end" style="min-width:120px">
                                    @if($item->material_type)
                                        <span class="badge bg-secondary mb-2">{{ $materialTypeLabels[$item->material_type] ?? ucfirst($item->material_type) }}</span>
                                    @endif

                                    @if($settings['show_availability'] ?? true)
                                        @php
                                            $avail = $item->availability ?? 'unknown';
                                            $availLabel = match($avail) {
                                                'available'   => __('Available'),
                                                'checked_out' => __('Checked out'),
                                                'on_hold'     => __('On hold'),
                                                'lost'        => __('Lost'),
                                                default       => __('Unknown'),
                                            };
                                            $availClass = match($avail) {
                                                'available' => 'bg-success',
                                                'checked_out' => 'bg-danger',
                                                'on_hold'     => 'bg-warning text-dark',
                                                'lost'        => 'bg-secondary',
                                                default       => 'bg-light text-dark border',
                                            };
                                        @endphp
                                        <div class="mb-1">
                                            <span class="badge {{ $availClass }}">
                                                <i class="fas fa-{{ match($avail) {
                                                    'available' => 'check',
                                                    'checked_out' => 'arrow-right',
                                                    'on_hold'     => 'clock',
                                                    'lost'        => 'exclamation-triangle',
                                                    default       => 'question'
                                                } }} me-1"></i>
                                                {{ $availLabel }}
                                            </span>
                                        </div>
                                        @if($item->total_copies > 0)
                                            <small class="text-muted">
                                                {{ $item->available_copies }} / {{ $item->total_copies }}
                                            </small>
                                        @endif
                                    @endif

                                    @if($item->call_number)
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-tag me-1"></i>
                                                {{ e($item->call_number) }}
                                            </small>
                                        </div>
                                    @endif
                                </div>

                            </div>
                        </div>
                    </div>
                @empty
                    @if($q)
                        <div class="alert alert-light text-center py-5">
                            <i class="fas fa-search fa-2x text-muted mb-3"></i>
                            <h5 class="text-muted">{{ __('No results found') }}</h5>
                            <p class="text-muted mb-3">
                                {{ __('No items matched') }} <em>"{{ e($q) }}"</em>.
                            </p>
                            <a href="{{ route('library.opac') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-redo me-1"></i>{{ __('Clear search') }}
                            </a>
                        </div>
                    @endif
                @endforelse

                {{-- Pagination --}}
                @if($results && $results->hasPages())
                    <div class="mt-4">
                        {{ $results->withQueryString()->links() }}
                    </div>
                @endif

            @endif

            {{-- Homepage widgets (shown when no search / no facets active) --}}
            @unless($results)
                @if(!empty($newArrivals) && $newArrivals->isNotEmpty())
                    <h4 class="mt-4 mb-3">
                        <i class="fas fa-star text-warning me-2"></i>{{ __('New arrivals') }}
                    </h4>
                    <div class="row mb-4">
                        @foreach($newArrivals->take(6) as $item)
                            <div class="col-6 col-md-4 col-lg-3 mb-3">
                                <div class="card h-100 shadow-sm">
                                    @if(!empty($item->cover_url))
                                        <img src="{{ $item->cover_url }}"
                                             class="card-img-top"
                                             alt="{{ e($item->title ?? '') }}"
                                             style="height:160px;object-fit:contain;padding:8px">
                                    @else
                                        <div class="bg-light d-flex align-items-center justify-content-center"
                                             style="height:160px">
                                            <i class="fas fa-book fa-3x text-secondary"></i>
                                        </div>
                                    @endif
                                    <div class="card-body p-2">
                                        <p class="card-text small text-truncate fw-semibold mb-1"
                                           title="{{ e($item->title ?? '') }}">
                                            {{ e(Str::limit($item->title ?? 'Untitled', 40)) }}
                                        </p>
                                        <a href="{{ route('library.opac-view', $item->slug ?? $item->id) }}"
                                           class="btn btn-outline-primary btn-sm w-100">
                                            {{ __('View') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!empty($popular) && $popular->isNotEmpty())
                    <h4 class="mb-3">
                        <i class="fas fa-fire text-danger me-2"></i>{{ __('Most borrowed') }}
                    </h4>
                    <div class="row">
                        @foreach($popular->take(6) as $item)
                            <div class="col-6 col-md-4 col-lg-3 mb-3">
                                <div class="card h-100 shadow-sm">
                                    @if(!empty($item->cover_url))
                                        <img src="{{ $item->cover_url }}"
                                             class="card-img-top"
                                             alt="{{ e($item->title ?? '') }}"
                                             style="height:160px;object-fit:contain;padding:8px">
                                    @else
                                        <div class="bg-light d-flex align-items-center justify-content-center"
                                             style="height:160px">
                                            <i class="fas fa-book fa-3x text-secondary"></i>
                                        </div>
                                    @endif
                                    <div class="card-body p-2">
                                        <p class="card-text small text-truncate fw-semibold mb-1"
                                           title="{{ e($item->title ?? '') }}">
                                            {{ e(Str::limit($item->title ?? 'Untitled', 40)) }}
                                        </p>
                                        <a href="{{ route('library.opac-view', $item->slug ?? $item->id) }}"
                                           class="btn btn-outline-primary btn-sm w-100">
                                            {{ __('View') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endunless

        </div>
    </div>
</div>
@endsection
