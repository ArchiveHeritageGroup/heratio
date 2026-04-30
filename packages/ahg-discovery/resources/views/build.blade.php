@extends('theme::layouts.1col')

@section('title', 'PageIndex Build — ' . $title)

@section('content')
<div class="container-fluid py-4">
    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-tree text-primary me-2"></i>
                PageIndex Build
            </h1>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('ahgdiscovery.pageindex') }}">PageIndex</a></li>
                    <li class="breadcrumb-item active">Build</li>
                </ol>
            </nav>
        </div>
        <a href="{{ route('ahgdiscovery.pageindex') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Back to PageIndex
        </a>
    </div>

    {{-- Record Info --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="card-title mb-1">
                        <a href="{{ url('/informationobject/' . $objectId) }}" class="text-decoration-none">
                            {{ $title }}
                        </a>
                    </h5>
                    @if (!empty($identifier))
                        <p class="text-muted mb-1">Identifier: {{ $identifier }}</p>
                    @endif
                    <p class="mb-0">
                        @if ($objectType === 'ead')
                            <span class="badge bg-success">EAD</span>
                        @elseif ($objectType === 'pdf')
                            <span class="badge bg-info">PDF</span>
                        @elseif ($objectType === 'rico')
                            <span class="badge bg-warning text-dark">RiC-O</span>
                        @endif
                        Object ID: {{ $objectId }}
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    {{-- Status Badge --}}
                    @if ($status)
                        @if ($status['status'] === 'ready')
                            <span class="badge bg-success fs-6">
                                <i class="fas fa-check-circle me-1"></i> Ready
                            </span>
                        @elseif ($status['status'] === 'building')
                            <span class="badge bg-warning text-dark fs-6">
                                <i class="fas fa-spinner fa-spin me-1"></i> Building
                            </span>
                        @elseif ($status['status'] === 'error')
                            <span class="badge bg-danger fs-6">
                                <i class="fas fa-exclamation-triangle me-1"></i> Error
                            </span>
                        @else
                            <span class="badge bg-secondary fs-6">{{ $status['status'] }}</span>
                        @endif
                    @else
                        <span class="badge bg-secondary fs-6">
                            <i class="fas fa-clock me-1"></i> Not indexed
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Status Details --}}
    @if ($status && $status['status'] === 'ready')
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-info-circle me-1"></i> Index Details
        </div>
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <td class="text-muted" style="width: 200px;">Indexed at</td>
                        <td>{{ $status['indexed_at'] }}</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Model used</td>
                        <td><code>{{ $status['model_used'] }}</code></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Node count</td>
                        <td>{{ $status['node_count'] }}</td>
                    </tr>
                    @if (!empty($status['source_hash']))
                    <tr>
                        <td class="text-muted">Source hash</td>
                        <td><code class="small">{{ substr($status['source_hash'], 0, 16) }}...</code></td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if ($status && $status['status'] === 'error' && !empty($status['error_message']))
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-triangle me-1"></i>
        <strong>Error:</strong> {{ $status['error_message'] }}
    </div>
    @endif

    {{-- Build / Rebuild Button --}}
    <div class="mb-4">
        <button type="button" class="btn btn-primary" id="build-btn"
                data-object-id="{{ $objectId }}" data-object-type="{{ $objectType }}">
            <i class="fas fa-hammer me-1"></i>
            {{ $status && $status['status'] === 'ready' ? 'Rebuild Index' : 'Build Index' }}
        </button>
        <span id="build-status" class="ms-3"></span>
    </div>

    {{-- Tree View (if ready) --}}
    @if ($status && $status['status'] === 'ready' && $tree)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-sitemap me-1"></i> Tree Structure</span>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#tree-collapse" aria-expanded="false" aria-controls="tree-collapse">
                <i class="fas fa-chevron-down me-1"></i> Toggle
            </button>
        </div>
        <div class="collapse" id="tree-collapse">
            <div class="card-body">
                <div id="tree-view">
                    @include('ahg-discovery::partials.tree-node', ['node' => $tree, 'depth' => 0])
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var buildBtn = document.getElementById('build-btn');
    var buildStatus = document.getElementById('build-status');

    buildBtn.addEventListener('click', function() {
        var objectId = this.dataset.objectId;
        var objectType = this.dataset.objectType;

        buildBtn.disabled = true;
        buildBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Building...';
        buildStatus.innerHTML = '<span class="text-muted">This may take a minute...</span>';

        fetch('{{ route("ahgdiscovery.build.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                object_id: objectId,
                object_type: objectType
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            buildBtn.disabled = false;

            if (data.success) {
                buildBtn.innerHTML = '<i class="fas fa-hammer me-1"></i> Rebuild Index';
                buildStatus.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i> ' +
                    'Index built successfully (' + (data.node_count || 0) + ' nodes, model: ' + (data.model || 'unknown') + ')</span>';
                // Reload to show tree
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                buildBtn.innerHTML = '<i class="fas fa-hammer me-1"></i> Retry Build';
                buildStatus.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i> ' +
                    'Build failed: ' + (data.error || 'Unknown error') + '</span>';
            }
        })
        .catch(function(err) {
            buildBtn.disabled = false;
            buildBtn.innerHTML = '<i class="fas fa-hammer me-1"></i> Retry Build';
            buildStatus.innerHTML = '<span class="text-danger">Request failed: ' + err.message + '</span>';
        });
    });
});
</script>
@endpush
@endsection
