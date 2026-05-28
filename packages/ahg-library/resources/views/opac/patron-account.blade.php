@extends('theme::layouts.1col')
@section('title', 'My Library Account')
@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0">
            <i class="fas fa-user me-2"></i>
            {{ __('My Library Account') }}
        </h1>
        <a href="{{ route('opac.patron.logout') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-sign-out-alt me-1"></i>{{ __('Sign Out') }}
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Welcome message --}}
    <p class="lead">
        {{ __('Welcome back,') }}
        <strong>{{ $patron->first_name ?? '' }}</strong>.
    </p>

    {{-- Summary cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h2>{{ $loans->count() }}</h2>
                    <small>{{ __('Items Checked Out') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h2>{{ $holds->count() }}</h2>
                    <small>{{ __('Holds Waiting') }}</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h2 class="{{ $finesTotal > 0 ? 'text-danger' : '' }}">
                        R {{ number_format($finesTotal, 2) }}
                    </h2>
                    <small>{{ __('Outstanding Fines') }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    @if($loans->isNotEmpty())
        <form method="POST" action="{{ route('opac.patron.renew-all') }}" class="d-inline mb-3">
            @csrf
            <button type="submit" class="btn btn-sm atom-btn-white">
                <i class="fas fa-redo me-1"></i>{{ __('Renew All Items') }}
            </button>
        </form>
    @endif
    <a href="{{ route('opac.patron.loans') }}" class="btn btn-sm atom-btn-white mb-3">
        <i class="fas fa-list me-1"></i>{{ __('View All Loans') }}
    </a>
    <a href="{{ route('opac.patron.holds') }}" class="btn btn-sm atom-btn-white mb-3">
        <i class="fas fa-bookmark me-1"></i>{{ __('View Holds') }}
    </a>
    @if($finesTotal > 0)
        <a href="{{ route('opac.patron.fines') }}" class="btn btn-sm atom-btn-white mb-3">
            <i class="fas fa-exclamation-circle me-1"></i>{{ __('View Fines') }}
        </a>
    @endif

    {{-- Current loans --}}
    @if($loans->isNotEmpty())
        <h5 class="mt-4 mb-2"><i class="fas fa-book me-2"></i>{{ __('Current Checkouts') }}</h5>
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Due Date') }}</th>
                                <th class="text-center">{{ __('Days Left') }}</th>
                                <th class="text-end">{{ __('Renew') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $l)
                                @php
                                    $dueTs = strToTime($l->due_date ?? '');
                                    $daysLeft = $dueTs !== false ? floor(($dueTs - time()) / 86400) : null;
                                    $isOverdue = $dueTs !== false && $dueTs < time();
                                @endphp
                                <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                    <td>
                                        {{ $l->title ?? __('(unknown item)') }}
                                        @if($l->call_number ?? null)
                                            <br><small class="text-muted">{{ $l->call_number }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $l->due_date ?? '—' }}
                                        @if($isOverdue)
                                            <span class="badge bg-danger ms-1">{{ __('OVERDUE') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($daysLeft !== null)
                                            <span class="{{ $daysLeft < 0 ? 'text-danger fw-bold' : ($daysLeft <= 3 ? 'text-warning fw-bold' : '') }}">
                                                {{ $daysLeft }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('opac.patron.renew-one', $l->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>{{ __('You have no items checked out.') }}
        </div>
    @endif

    {{-- Current holds --}}
    @if($holds->isNotEmpty())
        <h5 class="mt-4 mb-2"><i class="fas fa-bookmark me-2"></i>{{ __('Current Holds') }}</h5>
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th class="text-center">{{ __('Queue') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th>{{ __('Expires') }}</th>
                                <th class="text-end">{{ __('Cancel') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($holds as $h)
                                <tr class="{{ ($h->status ?? '') === 'ready' ? 'table-success' : '' }}">
                                    <td>
                                        {{ $h->title ?? __('(unknown item)') }}
                                        @if($h->call_number ?? null)
                                            <br><small class="text-muted">{{ $h->call_number }}</small>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $h->queue_position ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-{{ ($h->status ?? '') === 'ready' ? 'success' : 'info' }}">
                                            @if(($h->status ?? '') === 'ready')
                                                {{ __('Ready for Pickup') }}
                                            @else
                                                {{ __('Waiting') }}
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ $h->expiry_date ?? '—' }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('opac.patron.holds.cancel') }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="hold_id" value="{{ $h->id }}">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    title="{{ __('Cancel hold') }}">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </td>
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
