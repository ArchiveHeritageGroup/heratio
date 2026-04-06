@extends('theme::layouts.1col')

@section('title', 'PageIndex Discovery')

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex align-items-center mb-4">
        <i class="fas fa-tree fa-lg text-primary me-2"></i>
        <h1 class="h3 mb-0">PageIndex Discovery</h1>
    </div>

    {{-- Search Form --}}
    <div class="card mb-4">
        <div class="card-body">
            <form id="pageindex-search-form" method="GET" action="{{ route('ahgdiscovery.pageindex') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-md-7">
                        <label for="pageindex-query" class="form-label">Search query</label>
                        <input type="text" class="form-control" id="pageindex-query" name="q"
                               value="{{ $query }}" placeholder="Enter a natural language search query..."
                               autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label for="pageindex-type" class="form-label">Type filter</label>
                        <select class="form-select" id="pageindex-type" name="type">
                            <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All</option>
                            <option value="ead" {{ $type === 'ead' ? 'selected' : '' }}>EAD Finding Aids</option>
                            <option value="pdf" {{ $type === 'pdf' ? 'selected' : '' }}>PDF Documents</option>
                            <option value="rico" {{ $type === 'rico' ? 'selected' : '' }}>RiC-O Metadata</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Loading spinner --}}
    <div id="pageindex-loading" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Searching...</span>
        </div>
        <p class="mt-2 text-muted">Querying LLM across indexed trees...</p>
    </div>

    <div class="row">
        {{-- Index Stats --}}
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-1"></i> Index Stats
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Total indexed</td>
                                <td class="text-end fw-bold">{{ $stats['total'] }}</td>
                            </tr>
                            @foreach ($stats['by_type'] as $typeName => $count)
                            <tr>
                                <td>
                                    @if ($typeName === 'ead')
                                        <span class="badge bg-success">EAD</span>
                                    @elseif ($typeName === 'pdf')
                                        <span class="badge bg-info">PDF</span>
                                    @elseif ($typeName === 'rico')
                                        <span class="badge bg-warning text-dark">RiC-O</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $typeName }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $count }}</td>
                            </tr>
                            @endforeach
                            @foreach ($stats['by_status'] as $statusName => $count)
                            <tr>
                                <td>
                                    @if ($statusName === 'ready')
                                        <span class="text-success">Ready</span>
                                    @elseif ($statusName === 'building')
                                        <span class="text-warning">Building</span>
                                    @elseif ($statusName === 'error')
                                        <span class="text-danger">Error</span>
                                    @else
                                        <span class="text-muted">{{ $statusName }}</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $count }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Results --}}
        <div class="col-md-9">
            @if (!empty($query) && empty($results))
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-1"></i>
                    No matches found for "<strong>{{ e($query) }}</strong>".
                    Try a different query or check if documents have been indexed.
                </div>
            @endif

            @if (!empty($results))
                <div class="alert alert-success mb-3">
                    Found <strong>{{ $totalMatches }}</strong> matching node(s) across
                    <strong>{{ count($results) }}</strong> indexed record(s) for
                    "<strong>{{ e($query) }}</strong>".
                </div>
            @endif

            <div id="pageindex-results">
                @foreach ($results as $treeResult)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <a href="{{ url('/informationobject/' . $treeResult['object_id']) }}" class="fw-bold text-decoration-none">
                                {{ $treeResult['record_title'] ?? 'Record #' . $treeResult['object_id'] }}
                            </a>
                            @if ($treeResult['object_type'] === 'ead')
                                <span class="badge bg-success ms-2">EAD</span>
                            @elseif ($treeResult['object_type'] === 'pdf')
                                <span class="badge bg-info ms-2">PDF</span>
                            @elseif ($treeResult['object_type'] === 'rico')
                                <span class="badge bg-warning text-dark ms-2">RiC-O</span>
                            @endif
                        </div>
                        <small class="text-muted">{{ count($treeResult['matches']) }} match(es)</small>
                    </div>
                    <div class="card-body">
                        @if (!empty($treeResult['reasoning']))
                        <p class="text-muted fst-italic mb-3">
                            <i class="fas fa-brain me-1"></i> {{ $treeResult['reasoning'] }}
                        </p>
                        @endif

                        @foreach ($treeResult['matches'] as $match)
                        <div class="border rounded p-3 mb-2">
                            {{-- Breadcrumb path --}}
                            @if (!empty($match['breadcrumb']))
                            <nav aria-label="Node path" class="mb-2">
                                <ol class="breadcrumb breadcrumb-sm mb-0" style="font-size: 0.85rem;">
                                    @foreach ($match['breadcrumb'] as $crumb)
                                    <li class="breadcrumb-item">
                                        <span class="text-muted">{{ $crumb['title'] }}</span>
                                    </li>
                                    @endforeach
                                </ol>
                            </nav>
                            @endif

                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>{{ $match['node_title'] ?? $match['node_id'] }}</strong>
                                    @if (!empty($match['node_level']))
                                        <span class="badge bg-secondary ms-1">{{ $match['node_level'] }}</span>
                                    @endif
                                </div>
                                <div>
                                    {{-- Relevance bar --}}
                                    @php $pct = round(($match['relevance'] ?? 0) * 100); @endphp
                                    <div class="d-flex align-items-center">
                                        <div class="progress" style="width: 80px; height: 8px;" title="{{ $pct }}% relevance">
                                            <div class="progress-bar {{ $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger') }}"
                                                 role="progressbar" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <small class="ms-2 text-muted">{{ $pct }}%</small>
                                    </div>
                                </div>
                            </div>

                            @if (!empty($match['node_summary']))
                            <p class="text-muted small mt-1 mb-1">{{ $match['node_summary'] }}</p>
                            @endif

                            @if (!empty($match['reason']))
                            <p class="small mb-0">
                                <i class="fas fa-lightbulb text-warning me-1"></i>
                                {{ $match['reason'] }}
                            </p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('pageindex-search-form');
    const loading = document.getElementById('pageindex-loading');
    const resultsDiv = document.getElementById('pageindex-results');

    // AJAX search (optional — the form also works as standard GET)
    form.addEventListener('submit', function(e) {
        const q = document.getElementById('pageindex-query').value.trim();
        if (!q) return; // Let standard form submission handle empty queries

        e.preventDefault();
        loading.classList.remove('d-none');
        resultsDiv.innerHTML = '';

        const type = document.getElementById('pageindex-type').value;

        fetch('{{ route("ahgdiscovery.pageindex.api") }}?' + new URLSearchParams({
            query: q,
            type: type
        }), {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            loading.classList.add('d-none');

            if (!data.success || !data.results || data.results.length === 0) {
                resultsDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-1"></i> No matches found.</div>';
                return;
            }

            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('q', q);
            url.searchParams.set('type', type);
            window.history.pushState({}, '', url);

            let html = '<div class="alert alert-success mb-3">Found <strong>' + data.total_matches +
                       '</strong> matching node(s) across <strong>' + data.results.length + '</strong> record(s).</div>';

            data.results.forEach(function(treeResult) {
                let typeBadge = '';
                if (treeResult.object_type === 'ead') typeBadge = '<span class="badge bg-success ms-2">EAD</span>';
                else if (treeResult.object_type === 'pdf') typeBadge = '<span class="badge bg-info ms-2">PDF</span>';
                else if (treeResult.object_type === 'rico') typeBadge = '<span class="badge bg-warning text-dark ms-2">RiC-O</span>';

                html += '<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div>';
                html += '<a href="/informationobject/' + treeResult.object_id + '" class="fw-bold text-decoration-none">';
                html += escapeHtml(treeResult.record_title || 'Record #' + treeResult.object_id) + '</a>' + typeBadge;
                html += '</div><small class="text-muted">' + treeResult.matches.length + ' match(es)</small></div>';
                html += '<div class="card-body">';

                if (treeResult.reasoning) {
                    html += '<p class="text-muted fst-italic mb-3"><i class="fas fa-brain me-1"></i> ' + escapeHtml(treeResult.reasoning) + '</p>';
                }

                treeResult.matches.forEach(function(match) {
                    html += '<div class="border rounded p-3 mb-2">';

                    // Breadcrumb
                    if (match.breadcrumb && match.breadcrumb.length) {
                        html += '<nav class="mb-2"><ol class="breadcrumb breadcrumb-sm mb-0" style="font-size:0.85rem;">';
                        match.breadcrumb.forEach(function(c) {
                            html += '<li class="breadcrumb-item"><span class="text-muted">' + escapeHtml(c.title) + '</span></li>';
                        });
                        html += '</ol></nav>';
                    }

                    var pct = Math.round((match.relevance || 0) * 100);
                    var barClass = pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning' : 'bg-danger');

                    html += '<div class="d-flex justify-content-between align-items-start"><div>';
                    html += '<strong>' + escapeHtml(match.node_title || match.node_id) + '</strong>';
                    if (match.node_level) html += ' <span class="badge bg-secondary ms-1">' + escapeHtml(match.node_level) + '</span>';
                    html += '</div><div class="d-flex align-items-center">';
                    html += '<div class="progress" style="width:80px;height:8px;" title="' + pct + '% relevance">';
                    html += '<div class="progress-bar ' + barClass + '" style="width:' + pct + '%"></div></div>';
                    html += '<small class="ms-2 text-muted">' + pct + '%</small></div></div>';

                    if (match.node_summary) {
                        html += '<p class="text-muted small mt-1 mb-1">' + escapeHtml(match.node_summary) + '</p>';
                    }
                    if (match.reason) {
                        html += '<p class="small mb-0"><i class="fas fa-lightbulb text-warning me-1"></i> ' + escapeHtml(match.reason) + '</p>';
                    }

                    html += '</div>';
                });

                html += '</div></div>';
            });

            resultsDiv.innerHTML = html;
        })
        .catch(function(err) {
            loading.classList.add('d-none');
            resultsDiv.innerHTML = '<div class="alert alert-danger">Search failed: ' + escapeHtml(err.message) + '</div>';
        });
    });

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
