{{--
  Repatriation engine - PUBLIC community KNOWLEDGE contribution form for one
  claim (north-star heratio#1207: the repatriation engine).

  A community member, descendant, researcher or any knowledgeable person viewing
  a claim's virtual-return page can contribute knowledge about the displaced
  object - oral history, provenance knowledge, a correction, a pointer to the
  source community, or another note. The contribution lands for moderation, not
  straight to public. Already-approved community knowledge on this claim is shown
  above the form. Respectful, non-partisan framing; the contributor is credited
  only on explicit consent. Empty-states throughout; never 500.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Share what you know'))

@section('content')
<div class="container-fluid py-4">

    {{-- Breadcrumb back to the public repatriation dashboard --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item"><a href="{{ route('repatriation.dashboard') }}">{{ __('Repatriation') }}</a></li>
            @if($context)
                <li class="breadcrumb-item">
                    <a href="{{ route('virtual-return.show', ['id' => $claimId]) }}">{{ __('Virtual return') }}</a>
                </li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ __('Share what you know') }}</li>
        </ol>
    </nav>

    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    @if($context === null)
        {{-- Claim not resolvable --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-circle-question fa-3x text-muted mb-3"></i>
                <h1 class="h4">{{ __('This claim is not available for contributions') }}</h1>
                <p class="text-muted mb-3 mx-auto" style="max-width: 42rem;">
                    {{ __('The claim could not be found. Knowledge can only be contributed against a claim that is on record.') }}
                </p>
                <a href="{{ route('repatriation.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to repatriation') }}
                </a>
            </div>
        </div>
    @else
        @php
            $itemTitle = ($context['item_title'] ?? null) ?: (__('Record').' #'.($context['item_ref'] ?? ''));
            $sm = $context['status_meta'] ?? ['label' => $context['claim_status'] ?? 'registered', 'level' => 'secondary'];
        @endphp

        {{-- Hero / claim context --}}
        <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
            <div class="d-flex align-items-center flex-wrap mb-2">
                <i class="fas fa-hand-holding-heart fa-lg me-3"></i>
                <h1 class="h3 mb-0">{{ __('Share what you know about this object') }}</h1>
                <span class="badge text-bg-light text-dark ms-3">{{ __($sm['label']) }}</span>
            </div>
            <p class="mb-1 text-white-50">
                @if(!empty($context['item_slug']))
                    <a href="{{ url('/'.$context['item_slug']) }}" class="link-light">{{ $itemTitle }}</a>
                @else
                    {{ $itemTitle }}
                @endif
            </p>
            @if(!empty($context['origin_place']) || !empty($context['claimant_community']))
                <p class="mb-0 text-white-50 small">
                    {{ __('Origin') }}: <strong>{{ $context['origin_place'] ?: ($context['claimant_community'] ?? '-') }}</strong>@if(!empty($context['origin_place']) && !empty($context['claimant_community'])), {{ $context['claimant_community'] }}@endif
                </p>
            @endif
        </div>

        {{-- Standing, respectful, non-partisan framing --}}
        <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
            <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
            <div>
                <strong>{{ __('Knowledge about this object belongs to its communities.') }}</strong>
                <p class="mb-0 small">{{ $disclaimer }}</p>
            </div>
        </div>

        <div class="row g-4">
            {{-- The claim's documented context, read-only, so the contributor has the frame --}}
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="fas fa-seedling me-1 text-success"></i>{{ __('What is on record') }}
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <div class="text-uppercase text-muted small fw-semibold">{{ __('Place / region of origin') }}</div>
                            <div>{{ $context['origin_place'] ?: '-' }}</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-uppercase text-muted small fw-semibold">{{ __('Claimant community') }}</div>
                            <div>{{ $context['claimant_community'] ?: '-' }}</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-uppercase text-muted small fw-semibold">{{ __('Current holder') }}</div>
                            <div>{{ $context['current_holder'] ?: '-' }}</div>
                        </div>
                        @if(!empty($context['evidence_summary']))
                            <div class="border-start border-3 ps-3 mt-3">
                                <div class="text-uppercase text-muted small fw-semibold">{{ __('Documented evidence') }}</div>
                                <p class="small mb-0">{{ \Illuminate\Support\Str::limit($context['evidence_summary'], 600) }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer bg-white">
                        <a href="{{ route('virtual-return.show', ['id' => $claimId]) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-up-right-from-square me-1"></i>{{ __('Open the virtual-return page') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- The contribution form --}}
            <div class="col-12 col-lg-7">
                @if(!$available)
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h2 class="h5">{{ __('Contributions are not available right now') }}</h2>
                            <p class="text-muted mb-0">{{ __('The contributions store has not been installed yet. It is created automatically on the next application boot.') }}</p>
                        </div>
                    </div>
                @else
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <i class="fas fa-plus me-1"></i>{{ __('Contribute knowledge') }}
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">
                                {{ __('Add oral history, provenance knowledge, a correction or a pointer to the source community. Contributions are reviewed before they appear, so the record stays trustworthy.') }}
                            </p>
                            <form method="POST" action="{{ route('repatriation-knowledge.contribute', ['claim' => $claimId]) }}">
                                @csrf
                                <div class="mb-3">
                                    <label for="rkc-type" class="form-label small">{{ __('Type of knowledge') }} <span class="text-danger">*</span></label>
                                    <select class="form-select @error('contribution_type') is-invalid @enderror" id="rkc-type" name="contribution_type" required>
                                        @foreach($types as $key => $meta)
                                            <option value="{{ $key }}" {{ old('contribution_type', 'oral_history') === $key ? 'selected' : '' }}>
                                                {{ __($meta['label']) }} - {{ __($meta['blurb']) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('contribution_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="mb-3">
                                    <label for="rkc-body" class="form-label small">{{ __('What do you know?') }} <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('body') is-invalid @enderror" id="rkc-body" name="body" rows="8" maxlength="60000" required placeholder="{{ __('Share the oral history, provenance, correction or community knowledge here...') }}">{{ old('body') }}</textarea>
                                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label for="rkc-source" class="form-label small">{{ __('Source or attribution (optional)') }}</label>
                                        <input type="text" class="form-control" id="rkc-source" name="source" maxlength="512" value="{{ old('source') }}">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label for="rkc-name" class="form-label small">{{ __('Your name (optional)') }}</label>
                                        <input type="text" class="form-control" id="rkc-name" name="contributor_name" maxlength="255" value="{{ old('contributor_name') }}">
                                    </div>
                                </div>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="1" id="rkc-consent" name="credit_consent" {{ old('credit_consent') ? 'checked' : '' }}>
                                    <label class="form-check-label small" for="rkc-consent">
                                        {{ __('Credit me by name for this contribution. If unticked, your contribution appears anonymously.') }}
                                    </label>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-paper-plane me-1"></i>{{ __('Submit for review') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Already-approved community knowledge on this claim --}}
        <section class="mt-5">
            <h2 class="h4 border-bottom pb-2 mb-3">
                <i class="fas fa-users me-2 text-muted"></i>{{ __('Community knowledge') }}
            </h2>
            @if(empty($approved))
                <p class="text-muted small mb-0">{{ __('No approved community knowledge on this object yet. Be the first to contribute above.') }}</p>
            @else
                <div class="row g-3">
                    @foreach($approved as $c)
                        <div class="col-12 col-lg-6">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <span class="small fw-semibold">
                                        <i class="fas {{ $c['type_meta']['icon'] ?? 'fa-circle-info' }} me-1 text-muted"></i>{{ __($c['type_meta']['label'] ?? $c['contribution_type']) }}
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="small mb-2" style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($c['body'], 600) }}</p>
                                    @if(!empty($c['source']))
                                        <p class="small text-muted mb-1"><i class="fas fa-book-open me-1"></i>{{ $c['source'] }}</p>
                                    @endif
                                    <p class="small text-muted mb-0">
                                        <i class="fas fa-user me-1"></i>{{ $c['contributor_name'] ?: __('Anonymous contributor') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

</div>
@endsection
