@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'savedSearches'])@endsection
@section('title', 'Saved Searches')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.workspace') }}">Workspace</a></li>
        <li class="breadcrumb-item active">Saved Searches</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1 class="h2 mb-4"><i class="fas fa-search text-primary me-2"></i>{{ __('Saved Searches') }}</h1>

<div class="card mb-4">
    @if(count($savedSearches ?? []) > 0)
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Citation ID') }}</th>
                    <th>{{ __('Query') }}</th>
                    <th>{{ __('Results') }}</th>
                    <th>{{ __('Alerts') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @foreach($savedSearches as $s)
                <tr>
                    <td>
                        <strong>{{ e($s->name) }}</strong>
                        @if($s->description ?? null)<br><small class="text-muted">{{ e($s->description) }}</small>@endif
                    </td>
                    <td>
                        @if($s->citation_id ?? null)
                            <code class="small">{{ e($s->citation_id) }}</code>
                        @else
                            <small class="text-muted">-</small>
                        @endif
                    </td>
                    <td><code class="small">{{ e(\Illuminate\Support\Str::limit($s->search_query, 40)) }}</code></td>
                    <td>
                        @if(isset($s->last_result_count) && $s->last_result_count !== null)
                            <span class="badge bg-secondary">{{ (int) $s->last_result_count }} results</span>
                        @else
                            <small class="text-muted">{{ __('No snapshot') }}</small>
                        @endif
                    </td>
                    <td>{!! ($s->alert_enabled ?? 0) ? '<span class="badge bg-success">' . e(__('On')) . '</span>' : '<span class="badge bg-secondary">' . e(__('Off')) . '</span>' !!}</td>
                    <td><small>{{ date('Y-m-d', strtotime($s->created_at)) }}</small></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            @php
                                $runUrl = str_contains($s->search_query, '=')
                                    ? url('/glam/browse?' . $s->search_query)
                                    : url('/informationobject/browse?query=' . urlencode($s->search_query));
                            @endphp
                            <a href="{{ $runUrl }}" class="btn btn-outline-primary" title="{{ __('Run search') }}"><i class="fas fa-search"></i></a>
                            <button class="btn btn-outline-info diff-btn" data-id="{{ (int) $s->id }}" title="{{ __('Diff results') }}"><i class="fas fa-exchange-alt"></i></button>
                            <button class="btn btn-outline-success snapshot-btn" data-id="{{ (int) $s->id }}" title="{{ __('Snapshot current results') }}"><i class="fas fa-camera"></i></button>
                            <form method="POST" action="{{ route('research.savedSearches.destroy', $s->id) }}" class="d-inline" onsubmit="return confirm('Delete this saved search?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="card-body text-center text-muted py-5">
        <i class="fas fa-search fa-3x mb-3"></i>
        <p>No saved searches. Use the search feature and save searches for quick access.</p>
    </div>
    @endif
</div>

{{-- Save New Search --}}
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-plus me-2"></i>{{ __('Save a New Search') }}</div>
    <div class="card-body">
        <form action="{{ route('research.savedSearches.store') }}" method="POST">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">{{ __('Name *') }}</label>
                    <input type="text" name="name" class="form-control form-control-sm" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label small">{{ __('Search Query *') }}</label>
                    <input type="text" name="search_query" class="form-control form-control-sm" required placeholder="{{ __('Enter query or paste URL params') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">{{ __('Description') }}</label>
                    <input type="text" name="description" class="form-control form-control-sm" placeholder="{{ __('Optional') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Diff Modal --}}
<div class="modal fade" id="diffModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">{{ __('Search Result Diff') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="diffBody"><div class="text-center"><i class="fas fa-spinner fa-spin"></i> {{ __('Computing diff...') }}</div></div>
        </div>
    </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = '{{ csrf_token() }}';

    // Diff button
    document.querySelectorAll('.diff-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var searchId = this.dataset.id;
            document.getElementById('diffBody').innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Computing diff against last snapshot...</p></div>';
            new bootstrap.Modal(document.getElementById('diffModal')).show();
            fetch('/research/search-diff/' + searchId, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify({})
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.error) {
                    document.getElementById('diffBody').innerHTML = '<div class="alert alert-warning">' + d.error + '</div>';
                    return;
                }
                var html = '<div class="row mb-3">';
                html += '<div class="col-md-4"><div class="card text-center"><div class="card-body py-2"><div class="fs-5 fw-bold">' + (d.previous_count || 0) + '</div><small class="text-muted">Previous</small></div></div></div>';
                html += '<div class="col-md-4"><div class="card text-center"><div class="card-body py-2"><div class="fs-5 fw-bold">' + (d.current_count || 0) + '</div><small class="text-muted">Current</small></div></div></div>';
                html += '<div class="col-md-4"><div class="card text-center"><div class="card-body py-2"><div class="fs-5 fw-bold">' + (d.unchanged_count || 0) + '</div><small class="text-muted">Unchanged</small></div></div></div>';
                html += '</div>';
                if (d.added && d.added.length > 0) {
                    html += '<h6 class="text-success"><i class="fas fa-plus-circle me-1"></i>Added (' + d.added.length + ')</h6><ul class="list-group mb-3">';
                    d.added.forEach(function(id) { html += '<li class="list-group-item list-group-item-success py-1"><small>ID: ' + id + '</small></li>'; });
                    html += '</ul>';
                }
                if (d.removed && d.removed.length > 0) {
                    html += '<h6 class="text-danger"><i class="fas fa-minus-circle me-1"></i>Removed (' + d.removed.length + ')</h6><ul class="list-group mb-3">';
                    d.removed.forEach(function(id) { html += '<li class="list-group-item list-group-item-danger py-1"><small>ID: ' + id + '</small></li>'; });
                    html += '</ul>';
                }
                if ((!d.added || d.added.length === 0) && (!d.removed || d.removed.length === 0)) {
                    html += '<div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>No changes since last snapshot.</div>';
                }
                document.getElementById('diffBody').innerHTML = html;
            }).catch(function() {
                document.getElementById('diffBody').innerHTML = '<div class="alert alert-warning">No previous snapshot found. Take a snapshot first.</div>';
            });
        });
    });

    // Snapshot button
    document.querySelectorAll('.snapshot-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (!confirm('Snapshot the current results? This will be used as baseline for future diffs.')) return;
            var searchId = this.dataset.id;
            fetch('/research/search-snapshot/' + searchId, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify({})
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.success) {
                    alert('Snapshot saved (' + (d.count || 0) + ' results).');
                    location.reload();
                } else {
                    alert(d.error || 'Error saving snapshot');
                }
            }).catch(function() { alert('Error saving snapshot'); });
        });
    });
});
</script>
@endpush
@endsection
