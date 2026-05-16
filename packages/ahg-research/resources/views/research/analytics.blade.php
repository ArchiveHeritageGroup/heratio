@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'analytics'])@endsection
@section('title-block')
    <h1><i class="fas fa-chart-line me-2"></i>{{ __('Research Analytics') }}</h1>
    <p class="text-muted mb-0">{{ __('Usage, search patterns, researcher activity, popular collections.') }}</p>
@endsection
@section('content')

<form method="get" action="{{ route('research.analytics') }}" class="row g-2 mb-3">
    <div class="col-auto">
        <label class="form-label small mb-0">{{ __('From') }}</label>
        <input type="date" name="from" value="{{ $data['period']['from'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto">
        <label class="form-label small mb-0">{{ __('To') }}</label>
        <input type="date" name="to" value="{{ $data['period']['to'] }}" class="form-control form-control-sm">
    </div>
    <div class="col-auto align-self-end">
        <button class="btn btn-sm btn-primary">{{ __('Update') }}</button>
    </div>
</form>

<div class="row g-2 mb-3">
    @foreach([
        'total_events'       => ['label' => 'Total events',      'icon' => 'bolt',       'color' => 'primary'],
        'unique_researchers' => ['label' => 'Researchers',       'icon' => 'users',      'color' => 'success'],
        'unique_objects'     => ['label' => 'Distinct objects',  'icon' => 'archive',    'color' => 'info'],
        'view_events'        => ['label' => 'Views',             'icon' => 'eye',        'color' => 'secondary'],
        'search_events'      => ['label' => 'Searches',          'icon' => 'search',     'color' => 'warning'],
        'cite_events'        => ['label' => 'Citations',         'icon' => 'quote-left', 'color' => 'dark'],
        'download_events'    => ['label' => 'Downloads',         'icon' => 'download',   'color' => 'success'],
        'annotation_events'  => ['label' => 'Annotations',       'icon' => 'highlighter','color' => 'danger'],
    ] as $key => $meta)
        <div class="col-md-3 col-sm-6">
            <div class="card text-center">
                <div class="card-body py-3">
                    <i class="fas fa-{{ $meta['icon'] }} text-{{ $meta['color'] }} fa-lg mb-1"></i>
                    <div class="h4 mb-0">{{ number_format($data['usage_totals'][$key] ?? 0) }}</div>
                    <small class="text-muted">{{ __($meta['label']) }}</small>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-2"></i>{{ __('Top researchers') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($data['top_researchers']))
                    <div class="text-muted text-center py-3">{{ __('No researcher activity in this period.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($data['top_researchers'] as $r)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ e(trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''))) ?: ('#' . $r->researcher_id) }} <small class="text-muted">{{ e($r->email) }}</small></span>
                                <span class="badge bg-primary">{{ $r->n }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-archive me-2"></i>{{ __('Popular descriptions') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($data['popular_descriptions']))
                    <div class="text-muted text-center py-3">{{ __('No description views in this period.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($data['popular_descriptions'] as $d)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ e($d->entity_title ?? ('IO #' . $d->entity_id)) }}</span>
                                <span class="badge bg-info">{{ $d->n }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-folder me-2"></i>{{ __('Popular collections') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($data['popular_collections']))
                    <div class="text-muted text-center py-3">{{ __('No collection activity in this period.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($data['popular_collections'] as $c)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ e($c->name ?? ('Collection #' . $c->collection_id)) }}</span>
                                <span class="badge bg-warning text-dark">{{ $c->n }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-search me-2"></i>{{ __('Top search terms') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($data['search_terms']))
                    <div class="text-muted text-center py-3">{{ __('No searches in this period.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($data['search_terms'] as $t)
                            <li class="list-group-item d-flex justify-content-between">
                                <code>{{ e($t->term) }}</code>
                                <span class="badge bg-secondary">{{ $t->n }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-calendar-week me-2"></i>{{ __('Weekly volume') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($data['date_range_distribution']))
                    <div class="text-muted text-center py-3">{{ __('No activity to chart.') }}</div>
                @else
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Week starting') }}</th><th class="text-end">{{ __('Events') }}</th></tr></thead>
                        <tbody>
                            @php $max = max(array_map(fn($w) => (int) $w->n, $data['date_range_distribution'])); @endphp
                            @foreach($data['date_range_distribution'] as $w)
                                <tr>
                                    <td>{{ $w->week_start }}</td>
                                    <td class="text-end">
                                        <span class="d-inline-block bg-primary" style="height:10px;width:{{ $max > 0 ? round((int)$w->n / $max * 200) : 0 }}px"></span>
                                        <span class="ms-2">{{ $w->n }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
