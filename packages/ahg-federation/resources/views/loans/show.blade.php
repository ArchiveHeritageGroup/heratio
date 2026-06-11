{{--
  Federation loans - admin: one loan request with its status workflow (#1203).
  Shows the requesting / holding members, the requested item + window, and the
  allowed forward transitions with a who/when audit line.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Loan request #').$loan->id)

@php
    $statusBadge = [
        'requested'  => 'bg-info',
        'approved'   => 'bg-primary',
        'declined'   => 'bg-danger',
        'in_transit' => 'bg-warning text-dark',
        'returned'   => 'bg-success',
        'cancelled'  => 'bg-secondary',
    ][$loan->status] ?? 'bg-secondary';
    $label = fn ($s) => __(ucwords(str_replace('_', ' ', $s)));
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="bi bi-arrow-left-right me-2"></i>{{ __('Loan request') }} #{{ $loan->id }}
        </h4>
        <a href="{{ route('federation.loans.index') }}" class="atom-btn-white">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back to loans') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-card-list me-1"></i>{{ __('Request detail') }}</span>
                    <span class="badge {{ $statusBadge }}">{{ $label($loan->status) }}</span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Item') }}</dt>
                        <dd class="col-sm-9">{{ $loan->item_title ?: __('(untitled item)') }}</dd>

                        <dt class="col-sm-3">{{ __('Item reference') }}</dt>
                        <dd class="col-sm-9">{{ $loan->item_ref ?: '-' }}</dd>

                        <dt class="col-sm-3">{{ __('Requesting member') }}</dt>
                        <dd class="col-sm-9">
                            {{ $requesting->name ?? ('#'.$loan->requesting_member_id) }}
                            @if ($requesting && $requesting->base_url)
                                <a href="{{ $requesting->base_url }}" target="_blank" rel="noopener"
                                   class="small ms-1"><i class="bi bi-box-arrow-up-right"></i></a>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Holding member') }}</dt>
                        <dd class="col-sm-9">
                            {{ $holding->name ?? ('#'.$loan->holding_member_id) }}
                            @if ($holding && $holding->base_url)
                                <a href="{{ $holding->base_url }}" target="_blank" rel="noopener"
                                   class="small ms-1"><i class="bi bi-box-arrow-up-right"></i></a>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Purpose') }}</dt>
                        <dd class="col-sm-9">{{ $loan->purpose ?: '-' }}</dd>

                        <dt class="col-sm-3">{{ __('Loan window') }}</dt>
                        <dd class="col-sm-9">
                            @if ($loan->needed_from || $loan->needed_to)
                                {{ $loan->needed_from ?: '?' }} &rarr; {{ $loan->needed_to ?: '?' }}
                            @else
                                -
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Notes') }}</dt>
                        <dd class="col-sm-9">
                            @if ($loan->notes)
                                <pre class="bg-light border rounded p-2 small mb-0">{{ $loan->notes }}</pre>
                            @else
                                -
                            @endif
                        </dd>
                    </dl>
                </div>
                <div class="card-footer small text-muted">
                    {{ __('Created') }}: {{ $loan->created_at ?: '-' }}
                    @if ($loan->decided_by || $loan->decided_at)
                        &middot; {{ __('Last decision') }}:
                        {{ $loan->decided_by ?: __('unknown') }}
                        @if ($loan->decided_at) ({{ $loan->decided_at }}) @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Workflow / status transitions --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-signpost-split me-1"></i>{{ __('Workflow') }}
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        {{ __('Current status') }}:
                        <span class="badge {{ $statusBadge }}">{{ $label($loan->status) }}</span>
                    </p>

                    @if (empty($allowed))
                        <p class="text-muted small mb-0">
                            {{ __('This request is in a final state. No further transitions are available.') }}
                        </p>
                    @else
                        <form method="POST" action="{{ route('federation.loans.transition', $loan->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="to" class="form-label small">{{ __('Move to') }}</label>
                                <select class="form-select form-select-sm" id="to" name="to" required>
                                    @foreach ($allowed as $next)
                                        <option value="{{ $next }}">{{ $label($next) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="note" class="form-label small">{{ __('Decision note (optional)') }}</label>
                                <textarea class="form-control form-control-sm" id="note" name="note" rows="2"
                                          placeholder="{{ __('Reason / conditions') }}"></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary w-100">
                                <i class="bi bi-check2-circle me-1"></i>{{ __('Apply transition') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
