{{--
  Research Leads - admin curation worklist (heratio#1210)

  Admin-gated curation of the Research Leads feed. Promote the strongest
  AI-found discoveries into pending leads (an explicit POST action, optionally
  AI-enriched via the AHG gateway), then publish or dismiss each. Only published
  leads reach the public feed. Status filter chips, per-lead actions, full
  empty-state. International, jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Research Leads - curation'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-magnifying-glass-chart me-2"></i>{{ __('Research Leads') }}
                <span class="badge text-bg-secondary align-middle">{{ __('Curation') }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ __('Promote the strongest AI-found connections into research leads, then publish the best to the public feed.') }}
            </p>
        </div>
        <a href="{{ route('research-leads.index') }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-up-right-from-square me-1"></i>{{ __('View public feed') }}
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    {{-- Disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-robot fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('AI-generated, grounded in catalogue links.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    {{-- Generate panel --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-2"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Promote discoveries into leads') }}</h2>
            <p class="text-muted small mb-3">
                {{ __('Reads the persisted discoveries (highest confidence first) and promotes them into pending leads. Idempotent: re-running refreshes existing leads in place and never overrides a publish or dismiss you have already made. Generation only ever runs when you click - never on a page load.') }}
            </p>

            @if(!$discoveriesAvailable)
                <div class="alert alert-info d-flex align-items-start mb-0" role="alert">
                    <i class="fas fa-circle-info fa-lg me-3 mt-1"></i>
                    <div>
                        {{ __('No discoveries are available to promote yet. Run') }}
                        <code>php artisan ahg:generate-discoveries</code>
                        {{ __('first, then return here to promote the strongest connections into leads.') }}
                    </div>
                </div>
            @else
                <form method="POST" action="{{ route('research-leads.generate') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <label for="limit" class="form-label small mb-1">{{ __('How many') }}</label>
                        <input type="number" class="form-control form-control-sm" id="limit" name="limit"
                               value="25" min="1" max="200" style="width: 7rem;">
                    </div>
                    <div class="col-auto">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="1" id="enrich" name="enrich">
                            <label class="form-check-label small" for="enrich">
                                {{ __('Enrich the "why it matters" prompt with AI') }}
                                <span class="text-muted">({{ __('via the AHG gateway') }})</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Promote') }}
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- Status filter chips --}}
    <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
        <span class="text-muted small me-1">{{ __('Filter:') }}</span>
        <a href="{{ route('research-leads.admin') }}"
           class="badge rounded-pill text-decoration-none {{ $statusFilter === '' ? 'text-bg-dark' : 'text-bg-light border' }}">
            {{ __('All') }} <span class="opacity-75">{{ (int) $total }}</span>
        </a>
        @foreach($statuses as $key => $meta)
            @php $c = (int) ($statusCounts[$key] ?? 0); @endphp
            <a href="{{ route('research-leads.admin', ['status' => $key]) }}"
               class="badge rounded-pill text-decoration-none {{ strcasecmp($statusFilter, $key) === 0 ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
            </a>
        @endforeach
    </div>

    @if(empty($leads))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h2 class="h4">
                    @if($statusFilter !== '')
                        {{ __('No leads in this state') }}
                    @else
                        {{ __('No research leads yet') }}
                    @endif
                </h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    @if($statusFilter !== '')
                        {{ __('No leads currently carry this status. Try another filter, or promote more discoveries above.') }}
                    @else
                        {{ __('Promote the strongest discoveries into leads using the panel above, then publish the best to the public feed.') }}
                    @endif
                </p>
            </div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Lead') }}</th>
                        <th class="text-center">{{ __('Confidence') }}</th>
                        <th class="text-center">{{ __('Links') }}</th>
                        <th class="text-center">{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leads as $lead)
                        @php
                            $rec = $lead['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
                            $recTitle = $lead['headline'] ?: ($rec['title'] ?: (__('Record').' #'.($rec['id'] ?? '')));
                            $conf = $lead['confidence'] ?? ['label' => __('Tentative'), 'level' => 'secondary', 'score' => 5];
                            $sm = $lead['status_meta'] ?? ['label' => $lead['status'], 'level' => 'secondary'];
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    @if(!empty($rec['slug']))
                                        <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none" target="_blank" rel="noopener">{{ $recTitle }}</a>
                                    @else
                                        {{ $recTitle }}
                                    @endif
                                </div>
                                @if(!empty($lead['why_it_matters']))
                                    <div class="small text-muted">{{ \Illuminate\Support\Str::limit($lead['why_it_matters'], 160) }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $conf['level'] }}">{{ (int) ($conf['score'] ?? 0) }}%</span>
                            </td>
                            <td class="text-center">
                                {{ (int) ($lead['connection_count'] ?? 0) }}
                                @if((int) ($lead['second_hop'] ?? 0) > 0)
                                    <span class="text-muted small">+{{ (int) $lead['second_hop'] }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge text-bg-{{ $sm['level'] }}">{{ __($sm['label']) }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($lead['status'] !== 'published')
                                        <form method="POST" action="{{ route('research-leads.publish', ['id' => $lead['id']]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-success" title="{{ __('Publish to the public feed') }}">
                                                <i class="fas fa-check me-1"></i>{{ __('Publish') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($lead['status'] !== 'dismissed')
                                        <form method="POST" action="{{ route('research-leads.dismiss', ['id' => $lead['id']]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary" title="{{ __('Dismiss (hide from the public feed)') }}">
                                                <i class="fas fa-xmark me-1"></i>{{ __('Dismiss') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($lead['status'] !== 'pending')
                                        <form method="POST" action="{{ route('research-leads.repend', ['id' => $lead['id']]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-secondary" title="{{ __('Return to pending review') }}">
                                                <i class="fas fa-rotate-left"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection
