{{--
  Discoveries - public generative-scholarship surface (heratio#1210)

  "AI finds connections no human spotted." Lists AI-surfaced, graph-grounded
  cross-collection discoveries: for each, the connection's record, the linked
  records/entities (as links), the AI's rationale, and a confidence indicator
  derived from how much real catalogue evidence underpins the link. Empty-state
  when the collection has no connected records yet. International, jurisdiction-
  neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Discoveries'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-diagram-project fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Discoveries') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Connections across the collection that no one had noticed.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('Our AI reads the catalogue\'s own web of relationships - the people, places, subjects and records that link to one another - and surfaces non-obvious connections a cataloguer might never have spotted. Every lead below is grounded strictly in real catalogue links; the model is instructed never to invent people, places, dates or records.') }}
        </p>
    </div>

    {{-- Grounding disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-triangle-exclamation fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('AI-generated, grounded in catalogue links - verify before citing.') }}</strong>
            <p class="mb-0 small">
                {{ __('Each discovery is produced by an AI model that was given ONLY the catalogue connections shown on its card. The model cites entities by their exact catalogue names and is told never to invent facts. Even so, AI can misread or overstate a link. Treat every discovery as a hypothesis to check against the underlying records before relying on it.') }}
            </p>
        </div>
    </div>

    @if(!empty($aiUnavailable))
        <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
            <i class="fas fa-plug-circle-xmark fa-lg me-3 mt-1"></i>
            <div>
                {{ __('The AI service is currently unreachable, so fresh research leads could not be generated. The verified catalogue connections below are unaffected - check back shortly for the AI commentary.') }}
            </div>
        </div>
    @endif

    @if(empty($discoveries))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-compass fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No discoveries yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 40rem;">
                    {{ __('As records in this collection become linked to one another - through shared people, places, subjects or provenance - our AI will begin surfacing the non-obvious connections between them here. Once the catalogue\'s relationships are in place, the discoveries will appear automatically.') }}
                </p>
            </div>
        </div>
    @else
        <p class="text-muted small mb-3">
            <i class="fas fa-circle-info me-1"></i>
            {{ trans_choice('{1}:count discovery surfaced from the current collection graph.|[2,*]:count discoveries surfaced from the current collection graph.', count($discoveries), ['count' => count($discoveries)]) }}
        </p>

        <div class="row g-4">
            @foreach($discoveries as $d)
                @php
                    $rec = $d['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
                    $recTitle = $rec['title'] ?: (__('Record').' #'.($rec['id'] ?? ''));
                    $insights = $d['insights'] ?? [];
                    $links = $d['links'] ?? [];
                    $conf = $d['confidence'] ?? ['label' => __('Tentative'), 'level' => 'secondary', 'score' => 5];
                    $visibleLinks = array_slice($links, 0, 8);
                    $moreLinks = max(0, count($links) - count($visibleLinks));
                @endphp

                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">

                        {{-- Card header: the record the connection centres on + confidence --}}
                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas fa-link me-1"></i>{{ __('Connection') }}
                                </div>
                                <h2 class="h5 mb-0">
                                    @if(!empty($rec['slug']))
                                        <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none">{{ $recTitle }}</a>
                                    @else
                                        {{ $recTitle }}
                                    @endif
                                </h2>
                            </div>
                            <span class="badge rounded-pill bg-{{ $conf['level'] }} flex-shrink-0">
                                {{ __($conf['label']) }}
                            </span>
                        </div>

                        <div class="card-body">

                            {{-- Confidence meter --}}
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small text-muted mb-1">
                                    <span>{{ __('Evidence strength') }}</span>
                                    <span>{{ (int) ($conf['score'] ?? 0) }}%</span>
                                </div>
                                <div class="progress" style="height: 6px;" role="progressbar"
                                     aria-valuenow="{{ (int) ($conf['score'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-{{ $conf['level'] }}"
                                         style="width: {{ (int) ($conf['score'] ?? 0) }}%;"></div>
                                </div>
                            </div>

                            {{-- The AI's rationale --}}
                            <div class="mb-3">
                                <div class="text-uppercase text-muted small fw-semibold mb-2">
                                    <i class="fas fa-lightbulb me-1 text-warning"></i>{{ __('What the AI noticed') }}
                                </div>
                                @if(count($insights) > 0)
                                    <ul class="mb-0 ps-3">
                                        @foreach($insights as $insight)
                                            <li class="mb-2">{{ $insight }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-circle-info me-1"></i>
                                        {{ __('The AI did not surface a non-obvious lead beyond the direct links shown below, or the AI service was unreachable. The verified connections still stand.') }}
                                    </p>
                                @endif
                            </div>

                            {{-- The real linked records/entities the connection rests on --}}
                            <div>
                                <div class="text-uppercase text-muted small fw-semibold mb-2">
                                    <i class="fas fa-sitemap me-1"></i>{{ __('Linked records and entities') }}
                                    <span class="badge bg-light text-dark border ms-1">{{ __('verified') }}</span>
                                </div>
                                @if(count($visibleLinks) > 0)
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($visibleLinks as $link)
                                            @if(!empty($link['slug']))
                                                <a href="{{ url('/'.$link['slug']) }}"
                                                   class="badge text-bg-light border text-decoration-none"
                                                   title="{{ $link['domain'] ?? '' }}">{{ $link['name'] }}</a>
                                            @else
                                                <span class="badge text-bg-light border"
                                                      title="{{ $link['domain'] ?? '' }}">{{ $link['name'] }}</span>
                                            @endif
                                        @endforeach
                                        @if($moreLinks > 0)
                                            <span class="badge text-bg-secondary">+{{ $moreLinks }} {{ __('more') }}</span>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-muted small mb-0">{{ __('No linked entities to show.') }}</p>
                                @endif
                            </div>

                        </div>

                        <div class="card-footer bg-white text-muted small d-flex justify-content-between flex-wrap gap-2">
                            <span>
                                <i class="fas fa-share-nodes me-1"></i>
                                {{ trans_choice('{1}:count direct link|[2,*]:count direct links', (int) ($d['total'] ?? 0), ['count' => (int) ($d['total'] ?? 0)]) }}
                                @if((int) ($d['second_hop'] ?? 0) > 0)
                                    &middot; {{ (int) $d['second_hop'] }} {{ __('indirect') }}
                                @endif
                            </span>
                            @if(!empty($rec['slug']))
                                <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none">
                                    {{ __('Open record') }} <i class="fas fa-arrow-right ms-1"></i>
                                </a>
                            @endif
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-rotate me-1"></i>
            {{ __('Discoveries are refreshed periodically from the live catalogue graph. New connections appear as records become linked.') }}
        </p>
    @endif

</div>
@endsection
