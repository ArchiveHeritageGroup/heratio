{{--
  Discovered connections - generative scholarship report (heratio#1210)

  Surfaces NON-OBVIOUS cross-collection connections and AI-suggested research
  leads for one record. Every AI insight is grounded in the record's actual
  catalogue links - the prompt forbids invented entities. The page carries a
  visible "verify before citing" disclaimer.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $rec = $discovery['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
    $recTitle = $rec['title'] ?? ('Record #'.($rec['id'] ?? ''));
    $connections = $discovery['connections'] ?? [];
    $insights = $discovery['insights'] ?? [];
    $total = (int) ($discovery['total'] ?? 0);
    $secondHop = (int) ($discovery['second_hop_count'] ?? 0);
    $grounded = (int) ($discovery['grounded_entities'] ?? 0);
    $aiAvailable = (bool) ($discovery['ai_available'] ?? false);

    // heratio#1210 federation increment - cross-institutional connections.
    $federated = $federated ?? null;
    $fedAvailable = (bool) ($federated['available'] ?? false);
    $fedConnections = $federated['connections'] ?? [];
    $fedTerms = $federated['terms'] ?? [];
    $fedAiAvailable = (bool) ($federated['ai_available'] ?? false);
    // heratio#1210 persistence/cache freshness: when these results came from the
    // read-through cache, surface when they were computed and a force-refresh link.
    $fedGeneratedAt = $federated['generated_at'] ?? null;
    $fedStale = (bool) ($federated['stale'] ?? false);
@endphp

@section('title', 'Discovered connections - '.$recTitle)

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">
                <i class="fas fa-diagram-project me-2"></i>{{ __('Discovered connections') }}
            </h1>
            <p class="text-muted mb-0">
                {{ __('Record') }}:
                @if(!empty($rec['slug']))
                    <a href="{{ url('/'.$rec['slug']) }}">{{ $recTitle }}</a>
                @else
                    <strong>{{ $recTitle }}</strong>
                @endif
                <span class="text-muted small">(#{{ $rec['id'] }})</span>
            </p>
        </div>
        <div class="text-end small text-muted">
            <div><span class="badge bg-secondary">{{ $total }}</span> {{ __('direct connections') }}</div>
            <div><span class="badge bg-secondary">{{ $secondHop }}</span> {{ __('indirect (2nd hop)') }}</div>
            <div><span class="badge bg-secondary">{{ $grounded }}</span> {{ __('entities given to the AI') }}</div>
        </div>
    </div>

    {{-- Grounding disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-triangle-exclamation fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('AI-generated, grounded in catalogue links - verify before citing.') }}</strong>
            <p class="mb-0 small">
                {{ __('The leads below are produced by an AI model that was given ONLY the catalogue connections shown on this page. The model is instructed never to invent people, places, dates or records, and to cite entities by their exact catalogue names. Even so, AI can misread or overstate a link. Treat every lead as a hypothesis to check against the underlying records before relying on or publishing it.') }}
            </p>
        </div>
    </div>

    @if($total === 0)
        <div class="alert alert-info">
            <i class="fas fa-circle-info me-2"></i>
            {{ __('This record has no cross-collection graph connections yet, so there is nothing to ground a discovery on.') }}
        </div>
    @else
        <div class="row g-4">

            {{-- AI-surfaced research leads --}}
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-lightbulb me-2 text-warning"></i>
                        <strong>{{ __('Research leads') }}</strong>
                        <span class="badge bg-light text-dark border ms-2">{{ __('AI') }}</span>
                    </div>
                    <div class="card-body">
                        @if(count($insights) > 0)
                            <ol class="mb-0 ps-3">
                                @foreach($insights as $insight)
                                    <li class="mb-2">{{ $insight }}</li>
                                @endforeach
                            </ol>
                        @elseif(!$aiAvailable)
                            <div class="text-muted">
                                <i class="fas fa-plug-circle-xmark me-2"></i>
                                {{ __('The AI gateway is currently unreachable, so no leads were generated. The catalogue connections on the right are unaffected. Try again later.') }}
                            </div>
                        @else
                            <div class="text-muted">
                                <i class="fas fa-circle-info me-2"></i>
                                {{ __('The AI found no non-obvious connections beyond the direct catalogue links shown here.') }}
                            </div>
                        @endif
                    </div>
                    <div class="card-footer text-muted small">
                        {{ __('Each lead is derived only from the connections listed in this report. No external knowledge is used.') }}
                    </div>
                </div>
            </div>

            {{-- The real graph connections this is grounded in --}}
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center">
                        <i class="fas fa-sitemap me-2"></i>
                        <strong>{{ __('Catalogue connections') }}</strong>
                        <span class="badge bg-light text-dark border ms-2">{{ __('verified facts') }}</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion accordion-flush" id="connAccordion">
                            @foreach($connections as $i => $group)
                                @php $cid = 'conn'.$i; @endphp
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#{{ $cid }}">
                                            {{ $group['domain'] }}
                                            <span class="badge bg-secondary ms-2">{{ $group['count'] }}</span>
                                        </button>
                                    </h2>
                                    <div id="{{ $cid }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                                         data-bs-parent="#connAccordion">
                                        <div class="accordion-body p-0">
                                            <ul class="list-group list-group-flush">
                                                @foreach($group['items'] as $item)
                                                    <li class="list-group-item py-1 small">
                                                        @if(!empty($item['slug']))
                                                            <a href="{{ url('/'.$item['slug']) }}">{{ $item['name'] }}</a>
                                                        @else
                                                            {{ $item['name'] }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-footer text-muted small">
                        {{ __('These are direct catalogue relationships - the ground truth the AI was given.') }}
                    </div>
                </div>
            </div>

        </div>
    @endif

    {{-- ================================================================= --}}
    {{-- Cross-institutional connections (heratio#1210 federation slice).  --}}
    {{-- Strictly additive: only renders when the federation layer is      --}}
    {{-- available. Fully fail-soft - absent peers / a peer or AI timeout   --}}
    {{-- simply yields an empty list, never an error.                       --}}
    {{-- ================================================================= --}}
    @if($fedAvailable)
        <div class="card mt-4 border-primary-subtle">
            <div class="card-header d-flex align-items-center flex-wrap gap-2">
                <i class="fas fa-globe me-2 text-primary"></i>
                <strong>{{ __('Connections across institutions') }}</strong>
                <span class="badge bg-light text-dark border">{{ __('federated') }}</span>
                @if(count($fedConnections) > 0)
                    <span class="badge bg-primary">{{ count($fedConnections) }}</span>
                @endif
                <span class="ms-auto d-inline-flex align-items-center gap-2 small text-muted">
                    @if($fedGeneratedAt)
                        <span title="{{ __('When these cross-institutional results were last computed') }}">
                            <i class="fas fa-clock me-1"></i>{{ __('as of') }} {{ \Illuminate\Support\Carbon::parse($fedGeneratedAt)->diffForHumans() }}
                        </span>
                        @if($fedStale)
                            <span class="badge bg-warning text-dark" title="{{ __('A live refresh could not reach the peers, so the last-known results are shown.') }}">{{ __('last known') }}</span>
                        @endif
                    @endif
                    <a href="{{ url()->current() }}?refresh=1" class="btn btn-sm btn-outline-secondary py-0" title="{{ __('Recompute from peers now') }}">
                        <i class="fas fa-rotate me-1"></i>{{ __('Refresh') }}
                    </a>
                </span>
            </div>
            <div class="card-body">

                <p class="text-muted small">
                    <i class="fas fa-circle-info me-1"></i>
                    {{ __('These are related records held by OTHER institutions in the federation, found live by searching their catalogues for this record\'s strongest access points (its title, people, subjects and places). Each rationale is AI-generated and grounded only in the shared catalogue terms shown - verify against the source institution before citing.') }}
                </p>

                @if(count($fedTerms) > 0)
                    <div class="mb-3">
                        <span class="text-uppercase text-muted small fw-semibold me-1">{{ __('Searched on') }}:</span>
                        @foreach($fedTerms as $term)
                            <span class="badge text-bg-light border">{{ $term }}</span>
                        @endforeach
                    </div>
                @endif

                @if(count($fedConnections) === 0)
                    <div class="alert alert-light border mb-0">
                        <i class="fas fa-circle-info me-2"></i>
                        {{ __('No related records were found in partner institutions for this record\'s access points. This may mean no peer holds a match, or that no federation peers are currently reachable.') }}
                    </div>
                @else
                    @unless($fedAiAvailable)
                        <div class="alert alert-info d-flex align-items-start" role="alert">
                            <i class="fas fa-plug-circle-xmark me-2 mt-1"></i>
                            <div class="small">
                                {{ __('The AI service was unreachable, so the "why this connects" lines are not shown. The cross-institutional matches and their shared access points below are unaffected.') }}
                            </div>
                        </div>
                    @endunless

                    <div class="list-group">
                        @foreach($fedConnections as $fc)
                            @php
                                $fcTitle = $fc['title'] ?? __('Untitled record');
                                $fcUrl = $fc['url'] ?? null;
                                $fcPeer = $fc['peer_name'] ?? __('Partner institution');
                                $fcPeerUrl = $fc['peer_url'] ?? null;
                                $fcShared = $fc['shared'] ?? [];
                                $fcRationale = $fc['rationale'] ?? null;
                                $fcAlso = $fc['also_present_in'] ?? [];
                            @endphp
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                    <h3 class="h6 mb-1">
                                        @if(!empty($fcUrl))
                                            <a href="{{ $fcUrl }}" target="_blank" rel="noopener noreferrer">
                                                {{ $fcTitle }} <i class="fas fa-up-right-from-square fa-xs ms-1"></i>
                                            </a>
                                        @else
                                            {{ $fcTitle }}
                                        @endif
                                    </h3>
                                    <span class="badge rounded-pill text-bg-primary flex-shrink-0">
                                        <i class="fas fa-building-columns me-1"></i>
                                        @if(!empty($fcPeerUrl))
                                            <a href="{{ $fcPeerUrl }}" target="_blank" rel="noopener noreferrer" class="text-white text-decoration-none">{{ $fcPeer }}</a>
                                        @else
                                            {{ $fcPeer }}
                                        @endif
                                    </span>
                                </div>

                                @if($fcRationale)
                                    <p class="mb-2 small">
                                        <i class="fas fa-lightbulb me-1 text-warning"></i>{{ $fcRationale }}
                                    </p>
                                @endif

                                @if(count($fcShared) > 0)
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <span class="text-uppercase text-muted small fw-semibold me-1">{{ __('Shared access points') }}:</span>
                                        @foreach($fcShared as $sp)
                                            <span class="badge text-bg-light border">{{ $sp }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-circle-info me-1"></i>{{ __('Matched on a shared catalogue keyword only.') }}
                                    </p>
                                @endif

                                @if(count($fcAlso) > 0)
                                    <div class="mt-1">
                                        @foreach($fcAlso as $also)
                                            <span class="badge text-bg-secondary">{{ $also }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
            <div class="card-footer text-muted small">
                {{ __('Cross-institutional matches are retrieved live from federation peers and are not stored. Results may vary between page loads as peers come and go.') }}
            </div>
        </div>
    @endif

</div>
@endsection
