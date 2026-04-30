{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Research Permit')

@section('content')
@php
    $statusColors = ['pending' => 'warning', 'approved' => 'info', 'rejected' => 'danger', 'active' => 'success', 'expired' => 'secondary', 'revoked' => 'dark'];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.permits') }}">Permits</a></li>
                    <li class="breadcrumb-item active">{{ $permit->permit_number ?? '' }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-id-card me-2"></i>Research Permit {{ $permit->permit_number ?? '' }}</h1>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.permits') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Researcher') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name</dt>
                        <dd class="col-sm-8">
                            <a href="{{ route('ahgnaz.researcher-view', ['id' => $permit->researcher_id ?? 0]) }}">
                                {{ ($researcher->first_name ?? '') }} {{ ($researcher->last_name ?? '') }}
                            </a>
                        </dd>
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8">{{ ucfirst($researcher->researcher_type ?? '') }}</dd>
                        <dt class="col-sm-4">Institution</dt>
                        <dd class="col-sm-8">{{ $researcher->institution ?? '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Research Details') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Topic</dt>
                        <dd class="col-sm-8">{{ $permit->research_topic ?? '' }}</dd>
                        <dt class="col-sm-4">Purpose</dt>
                        <dd class="col-sm-8">{!! nl2br(e($permit->research_purpose ?? '-')) !!}</dd>
                        <dt class="col-sm-4">Permit Type</dt>
                        <dd class="col-sm-8">{{ ucfirst($permit->permit_type ?? '') }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Validity & Fees') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Valid From</dt>
                        <dd class="col-sm-8">@if(!empty($permit->start_date)){{ \Carbon\Carbon::parse($permit->start_date)->format('j F Y') }}@endif</dd>
                        <dt class="col-sm-4">Valid Until</dt>
                        <dd class="col-sm-8">@if(!empty($permit->end_date)){{ \Carbon\Carbon::parse($permit->end_date)->format('j F Y') }}@endif</dd>
                        <dt class="col-sm-4">Fee</dt>
                        <dd class="col-sm-8">{{ $permit->fee_currency ?? '' }} {{ number_format($permit->fee_amount ?? 0, 2) }}</dd>
                        <dt class="col-sm-4">Payment Status</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-{{ !empty($permit->fee_paid) ? 'success' : 'warning' }}">
                                {{ !empty($permit->fee_paid) ? 'Paid' : 'Pending' }}
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            @if(($permit->status ?? '') === 'pending')
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Review Actions') }}</h5></div>
                <div class="card-body">
                    <form method="post">
                        @csrf
                        <button type="submit" name="form_action" value="approve" class="btn btn-success me-2">
                            <i class="fas fa-check me-1"></i> {{ __('Approve') }}
                        </button>
                        <button type="submit" name="form_action" value="reject" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i> {{ __('Reject') }}
                        </button>
                    </form>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">{{ __('Status') }}</h5></div>
                <div class="card-body text-center">
                    <span class="badge bg-{{ $statusColors[$permit->status ?? ''] ?? 'secondary' }} fs-5 px-4 py-2">
                        {{ ucfirst($permit->status ?? '') }}
                    </span>
                    @php
                        $daysLeft = !empty($permit->end_date) ? floor((strtotime($permit->end_date) - time()) / 86400) : 0;
                    @endphp
                    @if(($permit->status ?? '') === 'active' && $daysLeft > 0)
                    <p class="mt-2 mb-0">{{ $daysLeft }} days remaining</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Record') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('Created:') }}</strong> @if(!empty($permit->created_at)){{ \Carbon\Carbon::parse($permit->created_at)->format('j M Y') }}@endif</p>
                    @if(!empty($permit->approved_date))
                    <p class="mb-0"><strong>{{ __('Approved:') }}</strong> {{ \Carbon\Carbon::parse($permit->approved_date)->format('j M Y') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
