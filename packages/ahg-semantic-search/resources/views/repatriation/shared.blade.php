{{--
  Repatriation claim - SHARED RECORD (heratio#1207, pillar 3)

  The permissioned, token-gated view of one claim that BOTH the holding
  institution and the origin community can see. Opened with a capability token
  minted by staff (no staff account needed). Shows the object in its origin
  context, the provenance-trace link (published records only), the current status
  and its history, and the SHARED dialogue thread - internal staff notes are never
  shown here. The claimant may add to the dialogue when the grant permits it.
  Sensitive subject matter: factual, non-partisan; the status is where a dialogue
  stands, never a legal outcome. International, jurisdiction-neutral.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $sm = $claim['status_meta'] ?? ['label' => $claim['claim_status'] ?? 'registered', 'level' => 'secondary', 'help' => ''];
    $itemTitle = ($claim['item_title'] ?? null) ?: (__('Record').' #'.($claim['item_ref'] ?? ''));
@endphp

@section('title', __('Shared record').': '.$itemTitle)

@section('content')
<div class="container-fluid py-4">

    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-share-nodes fa-lg me-3"></i>
            <span class="text-uppercase small fw-semibold text-white-50">{{ __('Shared record') }}</span>
        </div>
        <h1 class="h2 mb-2">{{ $itemTitle }}</h1>
        <p class="mb-0 text-white-50">
            {{ __('A record and dialogue shared between the holding institution and the community of origin.') }}
            @if(!empty($grant['grantee_name'])) {{ __('Opened by') }} <strong>{{ $grant['grantee_name'] }}</strong>.@endif
        </p>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
        <div><strong>{{ __('A documented request and its status, not a determination.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p></div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">

            {{-- Origin context + provenance --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold"><i class="fas fa-seedling text-success me-2"></i>{{ __('The object in its origin context') }}</span>
                    <span class="badge text-bg-{{ $sm['level'] }}">{{ __($sm['label']) }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3 small">
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted fw-semibold">{{ __('Place / region of origin') }}</div>
                            <div class="fw-semibold">{{ $claim['origin_place'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted fw-semibold">{{ __('Claimant community') }}</div>
                            <div class="fw-semibold">{{ $claim['claimant_community'] ?: '-' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-uppercase text-muted fw-semibold">{{ __('Current holder') }}</div>
                            <div>{{ $claim['current_holder'] ?: '-' }}</div>
                        </div>
                    </div>
                    @if(!empty($claim['evidence_summary']))
                        <div class="border-start border-3 ps-3 mb-3">
                            <div class="text-uppercase text-muted small fw-semibold mb-1"><i class="fas fa-file-lines me-1"></i>{{ __('Documented evidence') }}</div>
                            <p class="mb-0 small" style="white-space: pre-line;">{{ $claim['evidence_summary'] }}</p>
                        </div>
                    @endif
                    <div class="d-flex flex-wrap gap-2">
                        @if($provenance['provenance_url'])
                            <a href="{{ $provenance['provenance_url'] }}" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener"><i class="fas fa-clock-rotate-left me-1"></i>{{ __('Provenance / chain of custody') }}</a>
                        @endif
                        @if($provenance['record_url'])
                            <a href="{{ $provenance['record_url'] }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square me-1"></i>{{ __('Open the object record') }}</a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Dialogue (shared messages only) --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-comments me-2 text-muted"></i>{{ __('Dialogue') }}</div>
                <div class="card-body">
                    @if(empty($messages))
                        <p class="text-muted small mb-3">{{ __('No messages have been shared yet.') }}</p>
                    @else
                        <div class="d-flex flex-column gap-3 mb-3">
                            @foreach($messages as $m)
                                <div class="border rounded-3 p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge text-bg-{{ $m['author_role_meta']['level'] }}">{{ __($m['author_role_meta']['label']) }}</span>
                                        <span class="small text-muted">{{ $m['author_name'] ?: __('Unnamed') }} - {{ $m['created_at'] }}</span>
                                    </div>
                                    <div style="white-space: pre-line;">{{ $m['body'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($canMessage)
                        <form method="POST" action="{{ route('repatriation.shared.message', ['token' => $grant['token']]) }}">
                            @csrf
                            <label for="body" class="form-label small fw-semibold">{{ __('Add to the dialogue') }}</label>
                            <textarea name="body" id="body" rows="3" maxlength="60000" class="form-control mb-2" required></textarea>
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane me-1"></i>{{ __('Send message') }}</button>
                        </form>
                    @else
                        <p class="small text-muted mb-0"><i class="fas fa-eye me-1"></i>{{ __('This link is read-only. You can follow the dialogue here.') }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Status history --}}
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold"><i class="fas fa-clock-rotate-left me-2 text-muted"></i>{{ __('Where the dialogue stands') }}</div>
                <div class="card-body">
                    <p class="mb-2"><span class="badge text-bg-{{ $sm['level'] }}">{{ __($sm['label']) }}</span></p>
                    @if(!empty($sm['help']))<p class="small text-muted">{{ __($sm['help']) }}</p>@endif
                    @if(empty($history))
                        <p class="small text-muted mb-0">{{ __('No status changes recorded yet.') }}</p>
                    @else
                        <ul class="list-unstyled small mb-0">
                            @foreach($history as $h)
                                <li class="border-bottom pb-2 mb-2">
                                    <div>
                                        @if($h['from_status'])<span class="text-muted">{{ ucwords(str_replace('_',' ',$h['from_status'])) }}</span> <i class="fas fa-arrow-right mx-1 text-muted"></i>@endif
                                        <strong>{{ ucwords(str_replace('_',' ',$h['to_status'])) }}</strong>
                                    </div>
                                    @if($h['note'])<div class="text-muted">{{ $h['note'] }}</div>@endif
                                    <div class="text-muted">{{ $h['created_at'] }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
