{{--
 | FederationGovernanceController status + governance view (F2, #1315).
 | Read view of each peer's discovery probe outcome plus per-peer governance
 | controls (federation_enabled / trust_level / rate_limit / allowed surfaces).
 | New, UNLOCKED view - separate from the locked edit-peer.blade.php.
 | Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems / AGPL-3.0-or-later
--}}
@extends('theme::layout')

@section('title', 'Federation Governance')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('federation.index') }}">Federation</a></li>
                    <li class="breadcrumb-item active">Governance &amp; Discovery</li>
                </ol>
            </nav>
            <h4 class="mb-0"><i class="bi bi-shield-check me-2"></i>{{ __('Federation Governance & Discovery') }}</h4>
        </div>
        <form method="POST" action="{{ route('federation.governance.discover') }}" class="m-0">
            @csrf
            <button type="submit" class="atom-btn-white">
                <i class="bi bi-arrow-repeat me-1"></i>{{ __('Run discovery now') }}
            </button>
        </form>
    </div>

    @if(session('success'))
        <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}</div>
    @endif

    @unless($hasGovernance)
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-2"></i>
            {{ __('The federation governance columns are not installed yet. They are added automatically on the next boot, or run') }}
            <code>php artisan ahg:install</code>.
        </div>
    @endunless

    <p class="text-muted">
        {{ __('Each peer is probed against its') }} <code>/open-data/protocol</code> {{ __('and') }}
        <code>/open-data/maturity</code>
        {{ __('to record whether it advertises the Federation Query Protocol. Enable a peer for federation, set its trust level, an optional per-peer rate limit, and which surfaces it may be queried for.') }}
    </p>

    <div class="card">
        <div class="card-body p-0">
            @if($peers->isEmpty())
                <div class="p-4 text-center text-muted">{{ __('No peers configured.') }}</div>
            @else
                <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Peer') }}</th>
                            <th>{{ __('Discovery') }}</th>
                            <th>{{ __('Surfaces') }}</th>
                            <th style="min-width:22rem">{{ __('Governance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($peers as $peer)
                        <tr>
                            <td>
                                <strong>{{ $peer->name }}</strong><br>
                                <small class="text-muted">{{ $peer->base_url }}</small>
                            </td>
                            <td>
                                @php($status = $peer->discovery_status ?? 'unknown')
                                @php($badge = ['ok' => 'success', 'unreachable' => 'danger', 'non_compliant' => 'warning text-dark'][$status] ?? 'secondary')
                                <span class="badge bg-{{ $badge }}">{{ $status }}</span>
                                @if($peer->protocol_version ?? null)
                                    <div><small>v{{ $peer->protocol_version }}</small></div>
                                @endif
                                @if($peer->maturity_grade ?? null)
                                    <div><small class="text-muted">{{ __('maturity') }}: {{ $peer->maturity_grade }}</small></div>
                                @endif
                                @if($peer->last_probed_at ?? null)
                                    <div><small class="text-muted">{{ $peer->last_probed_at }}</small></div>
                                @else
                                    <div><small class="text-muted">{{ __('not yet probed') }}</small></div>
                                @endif
                            </td>
                            <td>
                                @forelse($peer->declared_surfaces_list as $s)
                                    <span class="badge bg-light text-dark border">{{ $s }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                            <td>
                                <form method="POST" action="{{ route('federation.governance.save', $peer->id) }}" class="m-0">
                                    @csrf
                                    <div class="row g-2 align-items-center">
                                        <div class="col-auto">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="federation_enabled" value="0">
                                                <input class="form-check-input" type="checkbox" name="federation_enabled"
                                                       value="1" id="fed-{{ $peer->id }}"
                                                       @checked(($peer->federation_enabled ?? 0) == 1)
                                                       @disabled(! $hasGovernance)>
                                                <label class="form-check-label" for="fed-{{ $peer->id }}">{{ __('Federate') }}</label>
                                            </div>
                                        </div>
                                        <div class="col-auto">
                                            <select name="trust_level" class="form-select form-select-sm" @disabled(! $hasGovernance)>
                                                @if(empty($trustLevels))
                                                    <option value="basic">basic</option>
                                                @else
                                                    @foreach($trustLevels as $tl)
                                                        <option value="{{ $tl->code }}" @selected(($peer->trust_level ?? 'basic') === $tl->code)>{{ $tl->label }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <input type="number" min="0" name="rate_limit_seconds"
                                                   class="form-control form-control-sm" style="width:7rem"
                                                   placeholder="{{ __('rate s') }}"
                                                   value="{{ $peer->rate_limit_seconds ?? '' }}"
                                                   @disabled(! $hasGovernance)>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        @foreach($surfaces as $surface)
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox"
                                                       name="allowed_entity_types[]" value="{{ $surface }}"
                                                       id="srf-{{ $peer->id }}-{{ $surface }}"
                                                       @checked(in_array($surface, $peer->allowed_entity_types_list ?? []))
                                                       @disabled(! $hasGovernance)>
                                                <label class="form-check-label" for="srf-{{ $peer->id }}-{{ $surface }}">{{ $surface }}</label>
                                            </div>
                                        @endforeach
                                        <small class="text-muted d-block">{{ __('No surfaces ticked = all advertised surfaces allowed.') }}</small>
                                    </div>
                                    <div class="mt-2">
                                        <button type="submit" class="atom-btn-white btn-sm" @disabled(! $hasGovernance)>
                                            <i class="bi bi-save me-1"></i>{{ __('Save') }}
                                        </button>
                                    </div>
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

    <p class="text-muted mt-3">
        <small>
            <i class="bi bi-link-45deg me-1"></i>{{ __('Public peer index') }}:
            <a href="{{ url('/open-data/federation') }}">{{ url('/open-data/federation') }}</a>
        </small>
    </p>
</div>
@endsection
