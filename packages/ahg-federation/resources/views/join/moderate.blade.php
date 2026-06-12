{{--
  Federated GLAM network - admin moderation of "Join the network" requests
  (#1203 join-request slice).

  The moderation queue: pending / reviewing / approved / declined, with
  per-request status transitions and an optional review note. Mirrors the
  moderation pattern used elsewhere (glossary / transcription review).
  Approving marks the request approved only - it does NOT create a member;
  member creation stays a deliberate admin step in the member registry.
  Admin-gated. Empty-state safe; never 500s.

  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio (AGPL-3.0-or-later).
--}}
@extends('theme::layout')

@section('title', __('Join requests'))

@php
    $badgeClass = [
        'pending' => 'bg-warning text-dark',
        'reviewing' => 'bg-info text-dark',
        'approved' => 'bg-success',
        'declined' => 'bg-secondary',
    ];
@endphp

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-inbox me-2"></i>{{ __('Network join requests') }}
            </h4>
            <p class="text-muted mb-0">
                {{ __('Institutions that have asked to join the federated GLAM network. Review each request and approve or decline it. Approving does not add a member - create the member deliberately in the member registry once approved.') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('union.members.index') }}" class="atom-btn-white">
                <i class="bi bi-people me-1"></i>{{ __('Member registry') }}
            </a>
            <a href="{{ url('/federation/join') }}" class="atom-btn-white" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Public join page') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Status filter chips with counts. --}}
    <div class="mb-4 d-flex flex-wrap gap-2">
        <a href="{{ route('federation.joinRequests.index') }}"
           class="btn btn-sm {{ $filter === null ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ number_format($total) }}</span>
        </a>
        @foreach ($statuses as $st)
            <a href="{{ route('federation.joinRequests.index', ['status' => $st]) }}"
               class="btn btn-sm {{ $filter === $st ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ __(ucfirst($st)) }}
                <span class="badge bg-light text-dark ms-1">{{ number_format($counts[$st] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    @if (empty($requests))
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            @if ($filter !== null)
                {{ __('No requests with this status.') }}
            @else
                {{ __('No join requests yet. When institutions submit the public join form, their requests appear here for review.') }}
            @endif
        </div>
    @else
        <div class="row g-3">
            @foreach ($requests as $r)
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center flex-wrap gap-2">
                            <i class="bi bi-building me-1"></i>
                            <strong>{{ $r->institution_name }}</strong>
                            <span class="badge {{ $badgeClass[$r->status] ?? 'bg-secondary' }}">
                                {{ __(ucfirst($r->status)) }}
                            </span>
                            <span class="text-muted small ms-auto">
                                {{ __('Submitted') }}
                                {{ $r->created_at ? \Illuminate\Support\Carbon::parse($r->created_at)->diffForHumans() : __('unknown') }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-7">
                                    <dl class="row mb-0 small">
                                        @if (! empty($r->contact_name))
                                            <dt class="col-sm-4">{{ __('Contact') }}</dt>
                                            <dd class="col-sm-8">{{ $r->contact_name }}</dd>
                                        @endif
                                        @if (! empty($r->contact_email))
                                            <dt class="col-sm-4">{{ __('Email') }}</dt>
                                            <dd class="col-sm-8">
                                                <a href="mailto:{{ $r->contact_email }}">{{ $r->contact_email }}</a>
                                            </dd>
                                        @endif
                                        @if (! empty($r->base_url))
                                            <dt class="col-sm-4">{{ __('URL') }}</dt>
                                            <dd class="col-sm-8">
                                                <a href="{{ $r->base_url }}" target="_blank" rel="noopener">{{ $r->base_url }}</a>
                                            </dd>
                                        @endif
                                        @if (! empty($r->reviewed_by))
                                            <dt class="col-sm-4">{{ __('Last reviewed by') }}</dt>
                                            <dd class="col-sm-8">
                                                {{ $r->reviewed_by }}
                                                @if (! empty($r->reviewed_at))
                                                    <span class="text-muted">({{ \Illuminate\Support\Carbon::parse($r->reviewed_at)->diffForHumans() }})</span>
                                                @endif
                                            </dd>
                                        @endif
                                    </dl>

                                    @if (! empty($r->what_they_share))
                                        <p class="mt-3 mb-1 small fw-bold">{{ __('What they would share') }}</p>
                                        <p class="small mb-2">{{ $r->what_they_share }}</p>
                                    @endif
                                    @if (! empty($r->notes))
                                        <p class="mt-2 mb-1 small fw-bold">{{ __('Notes') }}</p>
                                        <pre class="small bg-light border rounded p-2 mb-0" style="white-space: pre-wrap;">{{ $r->notes }}</pre>
                                    @endif
                                </div>

                                <div class="col-md-5">
                                    <form method="POST" action="{{ route('federation.joinRequests.update', $r->id) }}">
                                        @csrf
                                        <label class="form-label small fw-bold">{{ __('Set status') }}</label>
                                        <select name="status" class="form-select form-select-sm mb-2">
                                            @foreach ($statuses as $st)
                                                <option value="{{ $st }}" {{ $r->status === $st ? 'selected' : '' }}>
                                                    {{ __(ucfirst($st)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <label class="form-label small fw-bold">{{ __('Review note (optional)') }}</label>
                                        <textarea name="review_note" rows="2" class="form-control form-control-sm mb-2"
                                                  placeholder="{{ __('Recorded against this request.') }}"></textarea>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-check2 me-1"></i>{{ __('Apply') }}
                                        </button>
                                    </form>
                                    @if ($r->status === 'approved')
                                        <p class="small text-muted mt-2 mb-0">
                                            <i class="bi bi-info-circle me-1"></i>
                                            {{ __('Approved. To add this institution to the network, create it in the') }}
                                            <a href="{{ route('union.members.add') }}">{{ __('member registry') }}</a>.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
