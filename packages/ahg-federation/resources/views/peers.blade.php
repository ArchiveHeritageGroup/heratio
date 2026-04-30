@extends('theme::layout')

@section('title', 'Federation Peers')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item active">Peers</li>
                </ol>
            </nav>
            <h4 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Federation Peers</h4>
        </div>
        <a href="{{ route('federation.addPeer') }}" class="atom-btn-white">
            <i class="bi bi-plus-circle me-1"></i>{{ __('Add Peer') }}
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
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Base URL') }}</th>
                                <th>{{ __('Metadata Prefix') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Records') }}</th>
                                <th>{{ __('Last Harvest') }}</th>
                                <th>{{ __('Actions') }}</th>
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
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $peer->record_count ?? 0 }}</td>
                                    <td>{{ $peer->last_harvest_at ?? 'Never' }}</td>
                                    <td>
                                        <a href="{{ route('federation.editPeer', $peer->id) }}" class="atom-btn-white btn-sm me-1">Edit</a>
                                        <form method="post" action="{{ route('federation.testPeer', $peer->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="atom-btn-white btn-sm">{{ __('Test') }}</button>
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
