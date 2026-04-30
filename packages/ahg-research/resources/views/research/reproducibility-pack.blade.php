{{-- Reproducibility Pack — cloned from AtoM + Heratio extras --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item active">Reproducibility Pack</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">{{ __('Reproducibility Pack') }}</h1>
    <button id="downloadPack" class="btn btn-primary"><i class="fas fa-download me-1"></i> {{ __('Download Pack (JSON)') }}</button>
</div>

{{-- Summary cards --}}
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($milestones ?? []) }}</h4>
            <small class="text-muted">{{ __('Milestones') }}</small>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($snapshots ?? []) }}</h4>
            <small class="text-muted">{{ __('Snapshots') }}</small>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($resources ?? []) }}</h4>
            <small class="text-muted">{{ __('Resources') }}</small>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($assertions ?? []) }}</h4>
            <small class="text-muted">{{ __('Assertions') }}</small>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($hypotheses ?? []) }}</h4>
            <small class="text-muted">{{ __('Hypotheses') }}</small>
        </div></div>
    </div>
    <div class="col-md-2">
        <div class="card text-center"><div class="card-body py-2">
            <h4 class="mb-0">{{ count($extractionJobs ?? []) }}</h4>
            <small class="text-muted">{{ __('Extraction Jobs') }}</small>
        </div></div>
    </div>
</div>

{{-- Project Metadata --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ __('Project Metadata') }}</h5></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ e($project->title) }}</dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-{{ match($project->status ?? '') { 'active' => 'success', 'completed' => 'primary', 'on_hold' => 'warning', default => 'secondary' } }}">{{ ucfirst($project->status ?? 'unknown') }}</span></dd>
            @if($project->description ?? null)
            <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ e($project->description) }}</dd>
            @endif
            @if($project->institution ?? null)
            <dt class="col-sm-3">Institution</dt><dd class="col-sm-9">{{ e($project->institution) }}</dd>
            @endif
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $project->created_at ?? '' }}</dd>
            <dt class="col-sm-3">Integrity Hash</dt><dd class="col-sm-9"><code>{{ hash('sha256', json_encode([$project->id, count($assertions ?? []), count($snapshots ?? []), count($milestones ?? [])])) }}</code></dd>
        </dl>
    </div>
</div>

{{-- Snapshots (AtoM) --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Snapshots ({{ count($snapshots ?? []) }})</h5>
        <a href="{{ route('research.snapshots', $project->id) }}" class="btn btn-sm btn-outline-primary">Manage</a>
    </div>
    <div class="card-body">
        @if(!empty($snapshots))
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>{{ __('Label') }}</th><th>{{ __('Created') }}</th><th>{{ __('Items') }}</th></tr></thead>
                <tbody>
                @foreach($snapshots as $snap)
                    <tr>
                        <td>{{ e($snap->title ?? 'Snapshot') }}</td>
                        <td><small>{{ $snap->created_at ?? '' }}</small></td>
                        <td>{{ (int)($snap->item_count ?? 0) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-muted mb-0">No snapshots.</p>
        @endif
    </div>
</div>

{{-- Search Queries (AtoM) --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Search Queries ({{ count($searchQueries ?? []) }})</h5></div>
    <div class="card-body">
        @if(!empty($searchQueries))
        <ul class="list-group list-group-flush">
            @foreach($searchQueries as $sq)
            <li class="list-group-item px-0">
                <code>{{ e($sq->search_query ?? $sq->query ?? '') }}</code>
                <small class="text-muted d-block">{{ $sq->created_at ?? '' }}</small>
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-muted mb-0">No search queries recorded.</p>
        @endif
    </div>
</div>

{{-- Milestones (Heratio extra) --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Milestones ({{ count($milestones ?? []) }})</h5>
        <a href="{{ route('research.ethicsMilestones', $project->id) }}" class="btn btn-sm btn-outline-primary">Manage</a>
    </div>
    <ul class="list-group list-group-flush">
        @forelse($milestones ?? [] as $m)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ e($m->title ?? '') }}</span>
            <span class="badge bg-{{ match($m->status ?? '') { 'completed' => 'success', 'approved' => 'success', 'in_progress' => 'primary', default => 'secondary' } }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'pending')) }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No milestones.</li>
        @endforelse
    </ul>
</div>

<div class="row">
    {{-- Assertions (AtoM) --}}
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Assertions ({{ count($assertions ?? []) }})</h5></div>
            <div class="card-body" style="max-height:300px; overflow:auto;">
                @if(!empty($assertions))
                <ul class="list-group list-group-flush">
                    @foreach($assertions as $a)
                    <li class="list-group-item px-0 py-1">
                        <small>
                            <span class="badge bg-light text-dark">{{ e($a->assertion_type ?? '') }}</span>
                            <strong>{{ e($a->subject_label ?? '') }}</strong>
                            {{ e($a->predicate ?? '') }}
                            <strong>{{ e($a->object_label ?? $a->object_value ?? '') }}</strong>
                        </small>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted mb-0">No assertions.</p>
                @endif
            </div>
        </div>
    </div>
    {{-- Extraction Jobs (AtoM) --}}
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Extraction Jobs ({{ count($extractionJobs ?? []) }})</h5></div>
            <div class="card-body" style="max-height:300px; overflow:auto;">
                @if(!empty($extractionJobs))
                <ul class="list-group list-group-flush">
                    @foreach($extractionJobs as $ej)
                    <li class="list-group-item px-0 py-1">
                        <small>
                            <span class="badge bg-light text-dark">{{ e($ej->extraction_type ?? '') }}</span>
                            {{ ucfirst($ej->status ?? '') }} - {{ (int)($ej->processed_items ?? 0) }}/{{ (int)($ej->total_items ?? 0) }}
                        </small>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted mb-0">No extraction jobs.</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Resources (Heratio extra) --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Resources ({{ count($resources ?? []) }})</h5></div>
    <ul class="list-group list-group-flush">
        @forelse($resources ?? [] as $r)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>
                @if($r->external_url ?? null)
                <a href="{{ e($r->external_url) }}" target="_blank">{{ e($r->title ?? $r->external_url) }} <i class="fas fa-external-link-alt fa-xs"></i></a>
                @else
                {{ e($r->title ?? '') }}
                @endif
            </span>
            <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $r->resource_type ?? '')) }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No resources.</li>
        @endforelse
    </ul>
</div>

{{-- Hypotheses (Heratio extra) --}}
<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Hypotheses ({{ count($hypotheses ?? []) }})</h5></div>
    <ul class="list-group list-group-flush">
        @forelse($hypotheses ?? [] as $h)
        <li class="list-group-item d-flex justify-content-between align-items-center">
            <span>{{ e(Str::limit($h->statement ?? '', 80)) }}</span>
            <span class="badge bg-{{ match($h->status ?? '') { 'supported' => 'success', 'refuted' => 'danger', 'testing' => 'info', default => 'warning' } }}">{{ ucfirst($h->status ?? 'proposed') }}</span>
        </li>
        @empty
        <li class="list-group-item text-muted small">No hypotheses.</li>
        @endforelse
    </ul>
</div>

<script>
document.getElementById('downloadPack').addEventListener('click', function() {
    var projectId = {{ (int) $project->id }};
    fetch('{{ route("research.reproducibilityPack", $project->id) }}?format=json')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'reproducibility-pack-project-' + projectId + '.json';
            a.click();
        });
});
</script>
@endsection
