{{--
  Research Leads - public generative-scholarship feed (heratio#1210)

  "AI finds connections no human spotted." The public, read-only feed of the most
  compelling AI-found cross-collection connections, curated by staff into
  browsable scholarly leads. Each lead shows the connection's centre record, the
  verified records/entities it links, a plain-language "why this might matter"
  prompt, and links straight to the records. Only published leads (with published
  records) appear here. Empty-state when nothing is published yet. International,
  jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Research Leads'))

@section('content')
<div class="container-fluid py-4">

    {{-- Hero --}}
    <div class="p-4 p-md-5 mb-4 rounded-3 bg-dark text-white">
        <div class="d-flex align-items-center mb-2">
            <i class="fas fa-magnifying-glass-chart fa-lg me-3"></i>
            <h1 class="h2 mb-0">{{ __('Research Leads') }}</h1>
        </div>
        <p class="lead mb-1">
            {{ __('Connections worth following - drawn by AI from the collection own web of links.') }}
        </p>
        <p class="mb-0 text-white-50">
            {{ __('A research lead promotes one of the strongest non-obvious connections our AI has found across the catalogue into a starting point for enquiry. Each lead pairs the connection with a plain-language prompt - why it might matter - and links straight to the records it rests on. Every lead is grounded in real catalogue links and reviewed by staff before it appears here.') }}
        </p>
    </div>

    {{-- Standing disclaimer - always visible --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-robot fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('AI-generated leads, grounded in catalogue links - verify before citing.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    @if(empty($leads))
        {{-- Empty-state --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-compass-drafting fa-3x text-muted mb-3"></i>
                <h2 class="h4">{{ __('No research leads published yet') }}</h2>
                <p class="text-muted mb-0 mx-auto" style="max-width: 42rem;">
                    {{ __('As our AI surfaces non-obvious connections across the collection and curators review them, the most compelling will be published here as research leads. Once leads are published, they will appear automatically, each linking to the records it rests on.') }}
                </p>
            </div>
        </div>
    @else
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <p class="text-muted small mb-0">
                <i class="fas fa-circle-info me-1"></i>
                {{ trans_choice('{1}:count research lead published from the current collection graph.|[2,*]:count research leads published from the current collection graph.', $count, ['count' => $count]) }}
            </p>
        </div>

        <div class="row g-4">
            @foreach($leads as $lead)
                @php
                    $rec = $lead['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
                    $recTitle = $lead['headline'] ?: ($rec['title'] ?: (__('Record').' #'.($rec['id'] ?? '')));
                    $conf = $lead['confidence'] ?? ['label' => __('Tentative'), 'level' => 'secondary', 'score' => 5];
                    $links = $lead['links'] ?? [];
                    $visibleLinks = array_slice($links, 0, 8);
                    $moreLinks = max(0, count($links) - count($visibleLinks));
                @endphp

                <div class="col-12 col-lg-6">
                    <div class="card h-100 shadow-sm">

                        {{-- Header: the connection's centre record + confidence --}}
                        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
                            <div class="me-2">
                                <div class="text-uppercase text-muted small fw-semibold">
                                    <i class="fas fa-lightbulb me-1 text-warning"></i>{{ __('Research lead') }}
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

                            {{-- Why this might matter --}}
                            @if(!empty($lead['why_it_matters']))
                                <div class="border-start border-3 border-warning ps-3 mb-3">
                                    <div class="text-uppercase text-muted small fw-semibold mb-1">
                                        <i class="fas fa-circle-question me-1"></i>{{ __('Why this might matter') }}
                                    </div>
                                    <p class="mb-0">{{ $lead['why_it_matters'] }}</p>
                                </div>
                            @endif

                            {{-- The AI lead text --}}
                            @if(!empty($lead['lead_text']))
                                <div class="mb-3">
                                    <div class="text-uppercase text-muted small fw-semibold mb-2">
                                        <i class="fas fa-link me-1"></i>{{ __('The connection') }}
                                    </div>
                                    <p class="small mb-0">{{ \Illuminate\Support\Str::limit($lead['lead_text'], 280) }}</p>
                                </div>
                            @endif

                            {{-- The verified linked records/entities --}}
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
                                {{ trans_choice('{1}:count direct link|[2,*]:count direct links', (int) ($lead['connection_count'] ?? 0), ['count' => (int) ($lead['connection_count'] ?? 0)]) }}
                                @if((int) ($lead['second_hop'] ?? 0) > 0)
                                    &middot; {{ (int) $lead['second_hop'] }} {{ __('indirect') }}
                                @endif
                            </span>
                            <span class="d-flex gap-3">
                                <a href="{{ route('research-leads.show', ['id' => $lead['id']]) }}" class="text-decoration-none">
                                    {{ __('View lead') }} <i class="fas fa-up-right-from-square ms-1"></i>
                                </a>
                                @if(!empty($rec['slug']))
                                    <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none">
                                        {{ __('Open record') }} <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                @endif
                            </span>
                        </div>

                    </div>
                </div>
            @endforeach
        </div>

        <p class="text-muted small mt-4 mb-0">
            <i class="fas fa-hand-holding-heart me-1"></i>
            {{ __('Research leads are curated by staff from the AI-found connections in the live catalogue graph. They are starting points for enquiry, not findings.') }}
        </p>
    @endif

</div>
@endsection
