{{--
  Federation loans - admin worklist: inter-institution loan requests grouped
  into Incoming and Outgoing, filterable by status + direction (#1203).

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Inter-institution loans'))

@php
    $statusBadge = function ($s) {
        return [
            'requested'  => 'bg-info',
            'approved'   => 'bg-primary',
            'declined'   => 'bg-danger',
            'in_transit' => 'bg-warning text-dark',
            'returned'   => 'bg-success',
            'cancelled'  => 'bg-secondary',
        ][$s] ?? 'bg-secondary';
    };
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-arrow-left-right me-2"></i>{{ __('Inter-institution loans') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('Loan requests between federation members. Incoming = this institution holds the item; Outgoing = this institution is the borrower.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('union.members.index') }}" class="atom-btn-white">
                <i class="bi bi-people me-1"></i>{{ __('Members') }}
            </a>
            <a href="{{ route('federation.loans.analytics') }}" class="atom-btn-white">
                <i class="bi bi-bar-chart-line me-1"></i>{{ __('Analytics') }}
            </a>
            <a href="{{ route('federation.loans.create') }}" class="atom-btn-white">
                <i class="bi bi-plus-lg me-1"></i>{{ __('New loan request') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if (! $self)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            {{ __('No self-member is registered, so requests cannot be split into incoming and outgoing. Add a member marked "This institution" on the') }}
            <a href="{{ route('union.members.index') }}">{{ __('members page') }}</a>.
        </div>
    @endif

    {{-- Filters --}}
    <form method="GET" action="{{ route('federation.loans.index') }}" class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-end gap-3">
            <div>
                <label for="direction" class="form-label small mb-1">{{ __('Direction') }}</label>
                <select name="direction" id="direction" class="form-select form-select-sm" style="min-width: 160px;">
                    <option value="" {{ $direction === '' ? 'selected' : '' }}>{{ __('All directions') }}</option>
                    <option value="incoming" {{ $direction === 'incoming' ? 'selected' : '' }}>{{ __('Incoming (we hold)') }}</option>
                    <option value="outgoing" {{ $direction === 'outgoing' ? 'selected' : '' }}>{{ __('Outgoing (we borrow)') }}</option>
                </select>
            </div>
            <div>
                <label for="status" class="form-label small mb-1">{{ __('Status') }}</label>
                <select name="status" id="status" class="form-select form-select-sm" style="min-width: 160px;">
                    <option value="" {{ $status === '' ? 'selected' : '' }}>
                        {{ __('All statuses') }} ({{ $counts['all'] ?? 0 }})
                    </option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>
                            {{ __(ucwords(str_replace('_', ' ', $s))) }} ({{ $counts[$s] ?? 0 }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>{{ __('Filter') }}
                </button>
                <a href="{{ route('federation.loans.index') }}" class="btn btn-sm btn-outline-secondary">
                    {{ __('Reset') }}
                </a>
            </div>
        </div>
    </form>

    {{-- Incoming --}}
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-box-arrow-in-down-left me-1"></i>{{ __('Incoming requests') }}
            <span class="text-muted small">{{ __('(other members asking to borrow from us)') }}</span>
        </div>
        <div class="card-body p-0">
            @if (empty($incoming))
                <p class="text-muted m-3 mb-3">{{ __('No incoming loan requests.') }}</p>
            @else
                @include('ahg-federation::loans._table', ['rows' => $incoming, 'statusBadge' => $statusBadge])
            @endif
        </div>
    </div>

    {{-- Outgoing --}}
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Outgoing requests') }}
            <span class="text-muted small">{{ __('(this institution asking to borrow)') }}</span>
        </div>
        <div class="card-body p-0">
            @if (empty($outgoing))
                <p class="text-muted m-3 mb-3">{{ __('No outgoing loan requests.') }}</p>
            @else
                @include('ahg-federation::loans._table', ['rows' => $outgoing, 'statusBadge' => $statusBadge])
            @endif
        </div>
    </div>

    {{-- Other (neither party is the self-member; only shown when present) --}}
    @if (! empty($other))
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-diagram-2 me-1"></i>{{ __('Other requests') }}
                <span class="text-muted small">{{ __('(between members, this institution is neither party)') }}</span>
            </div>
            <div class="card-body p-0">
                @include('ahg-federation::loans._table', ['rows' => $other, 'statusBadge' => $statusBadge])
            </div>
        </div>
    @endif
</div>
@endsection
