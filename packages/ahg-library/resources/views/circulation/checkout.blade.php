@extends('theme::layouts.1col')
@section('title', 'Check Out Item')
@section('content')
<div class="container py-4">

    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>{{ __('Check Out Item') }}</h1>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.circulation.do-checkout') }}">
        @csrf

        {{-- Copy details --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('Item Details') }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('Barcode') }}</dt>
                    <dd class="col-sm-9"><code>{{ $copy->barcode ?? '' }}</code></dd>
                    <dt class="col-sm-3">{{ __('Title') }}</dt>
                    <dd class="col-sm-9">{{ $copy->title ?? __('(untitled)') }}</dd>
                    <dt class="col-sm-3">{{ __('Call Number') }}</dt>
                    <dd class="col-sm-9">{{ $copy->call_number ?? '—' }}</dd>
                    <dt class="col-sm-3">{{ __('Shelf Location') }}</dt>
                    <dd class="col-sm-9">{{ $copy->shelf_location ?? '—' }}</dd>
                    <dt class="col-sm-3">{{ __('Status') }}</dt>
                    <dd class="col-sm-9">
                        @if(($copy->copy_status ?? '') === 'available')
                            <span class="badge bg-success">{{ __('Available') }}</span>
                        @else
                            <span class="badge bg-warning text-dark">{{ ucfirst($copy->copy_status ?? '') }}</span>
                        @endif
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Patron search / selection --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Patron') }}</h5>
            </div>
            <div class="card-body">
                @if($patron ?? null)
                    <input type="hidden" name="patron_id" value="{{ $patron->id }}">
                    <div class="alert alert-success">
                        <strong>{{ trim(($patron->first_name ?? '') . ' ' . ($patron->last_name ?? '')) }}</strong>
                        &nbsp;<code>{{ $patron->card_number ?? '' }}</code>
                        &nbsp;<span class="badge bg-{{ ($patron->borrowing_status ?? '') === 'active' ? 'success' : 'danger' }}">
                            {{ ucfirst($patron->borrowing_status ?? '') }}
                        </span>
                        &nbsp; {{ __('Items out') }}: {{ $patron->max_checkouts ?? 0 }}
                        &nbsp; {{ __('Fines') }}: {{ number_format((float) ($patron->total_fines_owed ?? 0), 2) }}
                    </div>
                @else
                    <div class="mb-3">
                        <label for="patron_search" class="form-label">{{ __('Search by name, email or card number') }}</label>
                        <input type="text" id="patron_search" class="form-control"
                               placeholder="{{ __('Search…') }}" autocomplete="off"
                               hx-get="{{ route('library.patrons') }}"
                               hx-trigger="keyup changed delay:300ms"
                               hx-target="#patronResults"
                               hx-include="[name='_token']">
                    </div>
                    <div id="patronResults">
                        <p class="text-muted small">{{ __('Type to search for a patron, or go to') }}
                            <a href="{{ route('library.patrons') }}">{{ __('Manage Patrons') }}</a>
                            {{ __('to select one.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Loan info --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>{{ __('Loan Terms') }}</h5>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">{{ __('Loan Period') }}</dt>
                    <dd class="col-sm-9">{{ $loanDays }} {{ __('days') }}</dd>
                </dl>
            </div>
        </div>

        <input type="hidden" name="copy_id" value="{{ $copy->id ?? 0 }}">

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-success" {{ !$patron ? 'disabled' : '' }}>
                <i class="fas fa-check me-1"></i>{{ __('Confirm Check Out') }}
            </button>
            <a href="{{ route('library.circulation.index') }}" class="btn btn-outline-secondary">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</div>
@endsection
