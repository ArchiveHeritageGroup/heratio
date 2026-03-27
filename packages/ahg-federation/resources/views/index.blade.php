@extends('theme::layout')

@section('title', 'Federation Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Federation Dashboard</h4>
            <p class="text-muted mb-0">Manage federated peer repositories and OAI-PMH harvesting</p>
        </div>
        <div>
            <a href="{{ route('federation.peers') }}" class="atom-btn-white me-2">
                <i class="bi bi-hdd-network me-1"></i>Manage Peers
            </a>
            <a href="{{ route('federation.harvest') }}" class="atom-btn-white">
                <i class="bi bi-cloud-download me-1"></i>Harvest
            </a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="mb-0" style="color: var(--ahg-primary);">{{ $stats['peerCount'] }}</h2>
                    <p class="text-muted mb-0">Configured Peers</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h2 class="mb-0 text-success">{{ $stats['harvestCount'] }}</h2>
                    <p class="text-muted mb-0">Total Harvests</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="mb-0">{{ $stats['lastHarvest'] ?? 'Never' }}</h5>
                    <p class="text-muted mb-0">Last Harvest</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Peers</h6>
        </div>
        <div class="card-body p-0">
            @if($peers->isEmpty())
                <div class="p-4 text-center text-muted">
                    No federation peers configured. <a href="{{ route('federation.addPeer') }}">Add a peer</a>.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Base URL</th>
                                <th>Status</th>
                                <th>Last Harvest</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($peers as $peer)
                                <tr>
                                    <td>{{ $peer->name }}</td>
                                    <td><code>{{ $peer->base_url ?? '' }}</code></td>
                                    <td>
                                        @if(($peer->is_active ?? false))
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $peer->last_harvest_at ?? 'Never' }}</td>
                                    <td>
                                        <a href="{{ route('federation.editPeer', $peer->id) }}" class="atom-btn-white btn-sm">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
