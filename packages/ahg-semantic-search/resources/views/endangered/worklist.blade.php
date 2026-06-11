{{--
  Endangered-heritage capture-priority worklist - admin (heratio#1205)

  The admin worklist for the North Star "race against loss": items flagged as
  at-risk, ordered most-urgent first by a simple, legible priority score, with
  capture-status filter chips and a quick capture-status advance control on each
  row. Factual, non-alarmist copy: a flag is a prioritisation judgement and the
  reason for it, never a prediction of certain loss. Empty-state when nothing is
  flagged (or none for the chosen status). International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Capture-priority worklist'))

@section('content')
<div class="container-fluid py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-triangle-exclamation me-2"></i>{{ __('Capture-priority worklist') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('Items judged to be at risk, ordered most-urgent first, so the most vulnerable heritage is captured before it can be lost.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('endangered.register') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                <i class="fas fa-globe me-1"></i>{{ __('Public at-risk register') }}
            </a>
            <a href="{{ route('endangered.flag.form') }}" class="btn btn-primary">
                <i class="fas fa-flag me-1"></i>{{ __('Flag a record') }}
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Standing disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('A prioritisation aid, not a prediction of loss.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    {{-- Capture-status filter chips --}}
    <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
        <span class="text-muted small me-1">{{ __('Filter by capture status:') }}</span>
        <a href="{{ route('endangered.priority') }}"
           class="badge rounded-pill text-decoration-none {{ $statusFilter === '' ? 'text-bg-dark' : 'text-bg-light border' }}">
            {{ __('Awaiting capture') }} <span class="opacity-75">{{ (int) (($statusCounts['flagged'] ?? 0) + ($statusCounts['in_progress'] ?? 0)) }}</span>
        </a>
        @foreach($statuses as $key => $meta)
            @php $c = (int) ($statusCounts[$key] ?? 0); @endphp
            <a href="{{ route('endangered.priority', ['status' => $key]) }}"
               class="badge rounded-pill text-decoration-none {{ strcasecmp($statusFilter, $key) === 0 ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
            </a>
        @endforeach
    </div>

    @if(empty($flags))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-shield-heart fa-3x text-muted mb-3"></i>
                <h2 class="h5">
                    @if($statusFilter !== '')
                        {{ __('No items with this capture status') }}
                    @else
                        {{ __('Nothing is flagged for priority capture') }}
                    @endif
                </h2>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    @if($statusFilter !== '')
                        {{ __('No flags currently hold this status. Other statuses may still appear in the worklist.') }}
                    @else
                        {{ __('When a record is flagged as at-risk, it appears here ordered by urgency, so the most vulnerable heritage is captured first.') }}
                    @endif
                </p>
                <a href="{{ route('endangered.flag.form') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-flag me-1"></i>{{ __('Flag a record') }}
                </a>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 3rem;">{{ __('#') }}</th>
                            <th>{{ __('Item') }}</th>
                            <th>{{ __('Risk') }}</th>
                            <th>{{ __('Urgency') }}</th>
                            <th>{{ __('Capture') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($flags as $i => $f)
                            @php
                                $itemTitle = $f['item_title'] ?: (__('Record').' #'.$f['item_ref']);
                                $rm = $f['risk_meta'] ?? ['label' => $f['risk_category'], 'icon' => 'fa-triangle-exclamation'];
                                $um = $f['urgency_meta'] ?? ['label' => $f['urgency'], 'level' => 'info'];
                                $cm = $f['capture_meta'] ?? ['label' => $f['capture_status'], 'level' => 'secondary'];
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $i + 1 }}</td>
                                <td>
                                    @if(!empty($f['item_slug']))
                                        <a href="{{ url('/'.$f['item_slug']) }}" class="text-decoration-none" target="_blank" rel="noopener">{{ $itemTitle }}</a>
                                    @else
                                        {{ $itemTitle }}
                                    @endif
                                    <div class="small text-muted">
                                        {{ __('Item ref') }} #{{ $f['item_ref'] }}
                                        <span class="ms-2" title="{{ __('Priority score') }}">
                                            <i class="fas fa-arrow-up-wide-short"></i> {{ (int) ($f['priority_score'] ?? 0) }}
                                        </span>
                                    </div>
                                    @if(!empty($f['reason']))
                                        <div class="small text-muted text-truncate" style="max-width: 28rem;" title="{{ $f['reason'] }}">
                                            {{ $f['reason'] }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="small"><i class="fas {{ $rm['icon'] }} me-1"></i>{{ __($rm['label']) }}</span>
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $um['level'] }}">{{ __($um['label']) }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('endangered.capture-status', ['id' => $f['id']]) }}" class="d-inline">
                                        @csrf
                                        <select name="capture_status" class="form-select form-select-sm d-inline-block" style="width: auto;"
                                                onchange="this.form.submit()" aria-label="{{ __('Capture status') }}">
                                            @foreach($statuses as $key => $meta)
                                                <option value="{{ $key }}" {{ strcasecmp($f['capture_status'], $key) === 0 ? 'selected' : '' }}>
                                                    {{ __($meta['label']) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Set') }}</button></noscript>
                                    </form>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('endangered.flag.form', ['item' => $f['item_ref']]) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-pen"></i>
                                        <span class="d-none d-lg-inline ms-1">{{ __('Edit flag') }}</span>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-muted small mt-3 mb-0">
            <i class="fas fa-circle-info me-1"></i>
            {{ __('Priority score combines the urgency band with a small bonus when no durable digital surrogate exists yet. Captured items leave the worklist; the public at-risk register shows published items only.') }}
        </p>
    @endif

</div>
@endsection
