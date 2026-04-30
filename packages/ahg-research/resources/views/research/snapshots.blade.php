{{-- Snapshots — cloned from AtoM ahgResearchPlugin/snapshotsSuccess.php --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Snapshots</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">{{ __('Snapshots') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSnapshotModal"><i class="fas fa-camera me-1"></i> Create Snapshot</button>
</div>

@if(empty($snapshots) || count($snapshots) === 0)
    <div class="alert alert-info">No snapshots yet. Create one to freeze the current state of a collection.</div>
@else
<div class="table-responsive">
    <table class="table table-hover">
        <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Items') }}</th><th>{{ __('Hash') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($snapshots as $s)
            <tr>
                <td><strong>{{ e($s->title ?? 'Untitled') }}</strong></td>
                <td>{{ (int)($s->item_count ?? 0) }}</td>
                <td><code class="small">{{ Str::limit($s->hash_sha256 ?? '', 12, '...') }}</code></td>
                <td><span class="badge bg-{{ ($s->status ?? '') === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($s->status ?? 'created') }}</span></td>
                <td><small>{{ $s->created_at ?? '' }}</small></td>
                <td><a href="{{ route('research.viewSnapshot', $s->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Create Snapshot Modal --}}
<div class="modal fade" id="createSnapshotModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('research.snapshots', $project->id) }}">
            @csrf
            <input type="hidden" name="form_action" value="create">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('Create Snapshot') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Collection (optional — freezes a specific collection)') }}</label>
                        <select id="snapshotCollectionSelect" name="collection_id"></select>
                        <small class="text-muted">Leave empty to snapshot the entire project.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-camera me-1"></i>Create</button>
                </div>
            </div>
        </form>
    </div>
</div>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('snapshotCollectionSelect');
    if (el && typeof TomSelect !== 'undefined') {
        new TomSelect(el, {
            valueField: 'id',
            labelField: 'name',
            searchField: ['name'],
            placeholder: 'Search collections...',
            allowEmptyOption: true,
            load: function(query, callback) {
                if (!query.length || query.length < 2) return callback();
                fetch('{{ url("informationobject/autocomplete") }}?query=' + encodeURIComponent(query) + '&limit=15')
                    .then(function(r) { return r.json(); })
                    .then(function(data) { callback(data); })
                    .catch(function() { callback(); });
            },
            render: {
                option: function(item, escape) {
                    return '<div>' + escape(item.name) + (item.slug ? '<small class="text-muted ms-2">' + escape(item.slug) + '</small>' : '') + '</div>';
                },
                item: function(item, escape) {
                    return '<div><i class="fas fa-folder me-1"></i>' + escape(item.name) + '</div>';
                }
            }
        });
    }
});
</script>
@endsection
