@extends('theme::layout')

@section('title', 'Federation Dashboard')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>{{ __('Federation Dashboard') }}</h4>
            <p class="text-muted mb-0">{{ __('Manage federated peer repositories, OAI harvesting, federated search, and vocabulary sync.') }}</p>
        </div>
        <div>
            <a href="{{ route('federation.peers') }}" class="atom-btn-white me-2">
                <i class="bi bi-hdd-network me-1"></i>{{ __('Manage Peers') }}
            </a>
            <a href="{{ route('federation.harvest') }}" class="atom-btn-white me-2">
                <i class="bi bi-cloud-download me-1"></i>{{ __('Harvest') }}
            </a>
            <a href="{{ route('federation.log') }}" class="atom-btn-white">
                <i class="bi bi-list-ul me-1"></i>{{ __('Log') }}
            </a>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0" style="color: var(--ahg-primary);">{{ $stats['activePeerCount'] }} / {{ $stats['peerCount'] }}</h2>
                    <p class="text-muted mb-0">{{ __('Active / total peers') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-success">{{ $stats['harvestCount'] }}</h2>
                    <p class="text-muted mb-0">{{ __('Harvest log rows') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-info">{{ $stats['searchCacheLive'] }} / {{ $stats['searchCacheRows'] }}</h2>
                    <p class="text-muted mb-0">{{ __('Search cache (live / total)') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h2 class="mb-0 text-warning">{{ $stats['vocabSyncCount'] }}</h2>
                    <p class="text-muted mb-0">{{ __('Vocab sync configs') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-hdd-network me-2"></i>{{ __('Peers') }}</h6>
        </div>
        <div class="card-body p-0">
            @if($peers->isEmpty())
                <div class="p-4 text-center text-muted">
                    {{ __('No federation peers configured.') }}
                    <a href="{{ route('federation.addPeer') }}">{{ __('Add a peer') }}</a>.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Base URL') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Last Harvest') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($peers as $peer)
                                <tr>
                                    <td>{{ $peer->name }}</td>
                                    <td><code>{{ $peer->base_url ?? '' }}</code></td>
                                    <td>
                                        @if($peer->is_active ?? false)
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                        @endif
                                        @if(!empty($peer->last_harvest_status))
                                            @php
                                                $cls = match($peer->last_harvest_status) {
                                                    'success' => 'bg-success',
                                                    'partial' => 'bg-warning text-dark',
                                                    'failed' => 'bg-danger',
                                                    default => 'bg-secondary',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }} ms-1">{{ $peer->last_harvest_status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $peer->last_harvest_at ?? __('Never') }}</td>
                                    <td>
                                        <a href="{{ route('federation.editPeer', $peer->id) }}" class="atom-btn-white btn-sm">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-cloud-download me-2"></i>{{ __('Recent harvest sessions') }}</h6>
                </div>
                <div class="card-body p-0">
                    @if($recentSessions->isEmpty())
                        <div class="p-4 text-center text-muted">{{ __('No harvest sessions yet.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Started') }}</th>
                                        <th>{{ __('Peer') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th class="text-end">{{ __('Created') }}</th>
                                        <th class="text-end">{{ __('Updated') }}</th>
                                        <th class="text-end">{{ __('Errors') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentSessions as $s)
                                        <tr>
                                            <td>{{ $s->started_at }}</td>
                                            <td>{{ $s->peer_name ?? ('peer #' . $s->peer_id) }}</td>
                                            <td>
                                                @php
                                                    $cls = match($s->status) {
                                                        'completed' => 'bg-success',
                                                        'partial' => 'bg-warning text-dark',
                                                        'failed' => 'bg-danger',
                                                        'running' => 'bg-info',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $cls }}">{{ $s->status }}</span>
                                            </td>
                                            <td class="text-end">{{ $s->records_created }}</td>
                                            <td class="text-end">{{ $s->records_updated }}</td>
                                            <td class="text-end">{{ $s->records_errors }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-search me-2"></i>{{ __('Recent federated searches') }}</h6>
                </div>
                <div class="card-body p-0">
                    @if($recentSearches->isEmpty())
                        <div class="p-4 text-center text-muted">{{ __('No federated searches yet.') }}</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('When') }}</th>
                                        <th>{{ __('Query') }}</th>
                                        <th class="text-end">{{ __('Peers ok / total') }}</th>
                                        <th class="text-end">{{ __('Results') }}</th>
                                        <th class="text-end">{{ __('Time (ms)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($recentSearches as $r)
                                        <tr>
                                            <td>{{ $r->created_at }}</td>
                                            <td><code>{{ \Illuminate\Support\Str::limit($r->query_text, 32) }}</code></td>
                                            <td class="text-end">{{ $r->peers_responded }} / {{ $r->peers_queried }}</td>
                                            <td class="text-end">{{ $r->total_results }}</td>
                                            <td class="text-end">{{ $r->total_time_ms }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(!$vocabSyncConfigs->isEmpty())
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="bi bi-translate me-2"></i>{{ __('Vocabulary sync') }}</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Peer') }}</th>
                            <th>{{ __('Taxonomy') }}</th>
                            <th>{{ __('Direction') }}</th>
                            <th>{{ __('Conflict policy') }}</th>
                            <th>{{ __('Last sync') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vocabSyncConfigs as $cfg)
                            <tr>
                                <td>{{ $cfg->peer_name ?? ('peer #' . $cfg->peer_id) }}</td>
                                <td>{{ $cfg->taxonomy_name ?? ('taxonomy #' . $cfg->taxonomy_id) }}</td>
                                <td><code>{{ $cfg->sync_direction }}</code></td>
                                <td><code>{{ $cfg->conflict_resolution }}</code></td>
                                <td>{{ $cfg->last_sync_at ?? __('Never') }}</td>
                                <td>{{ $cfg->last_sync_status ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
