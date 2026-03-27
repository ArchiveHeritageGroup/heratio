@extends('theme::layout')

@section('title', 'Federation Peers')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item active">Peers</li>
                </ol>
            </nav>
            <h4 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Federation Peers</h4>
        </div>
        <a href="{{ route('federation.addPeer') }}" class="atom-btn-white">
            <i class="bi bi-plus-circle me-1"></i>Add Peer
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            @if($peers->isEmpty())
                <div class="p-4 text-center text-muted">No peers configured.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Base URL</th>
                                <th>Metadata Prefix</th>
                                <th>Status</th>
                                <th>Records</th>
                                <th>Last Harvest</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($peers as $peer)
                                <tr>
                                    <td><strong>{{ $peer->name }}</strong></td>
                                    <td><code class="small">{{ $peer->base_url ?? '' }}</code></td>
                                    <td>{{ $peer->metadata_prefix ?? 'oai_dc' }}</td>
                                    <td>
                                        @if(($peer->is_active ?? false))
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $peer->record_count ?? 0 }}</td>
                                    <td>{{ $peer->last_harvest_at ?? 'Never' }}</td>
                                    <td>
                                        <a href="{{ route('federation.editPeer', $peer->id) }}" class="atom-btn-white btn-sm me-1">Edit</a>
                                        <form method="post" action="{{ route('federation.testPeer', $peer->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="atom-btn-white btn-sm">Test</button>
                                        </form>
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
