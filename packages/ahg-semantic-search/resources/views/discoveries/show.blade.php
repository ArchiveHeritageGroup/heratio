{{--
  Discovery detail - single stored generative-scholarship discovery (heratio#1210)

  Renders one persisted discovery: the record it centres on, the AI's lead/summary,
  the verified linked records/entities the lead rests on, an evidence-strength
  meter, and the standing verify-before-citing disclaimer. Read-only; reads a row
  from ahg_scholarship_discovery via DiscoveriesController::show(). International,
  jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $d = $discovery ?? [];
    $rec = $d['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
    $recTitle = $rec['title'] ?: (__('Record').' #'.($rec['id'] ?? ''));
    $insights = $d['insights'] ?? [];
    $summary = $d['summary'] ?? '';
    $links = $d['links'] ?? [];
    $conf = $d['confidence'] ?? ['label' => __('Tentative'), 'level' => 'secondary', 'score' => 5];
    $genAt = $generatedAt ?? ($d['generated_at'] ?? null);
@endphp

@section('title', __('Discovery').': '.$recTitle)

@section('content')
<div class="container py-4">

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb small mb-0">
            <li class="breadcrumb-item">
                <a href="{{ route('scholarship.discoveries') }}" class="text-decoration-none">{{ __('Discoveries') }}</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">{{ $recTitle }}</li>
        </ol>
    </nav>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-start gap-2">
            <div class="me-2">
                <div class="text-uppercase text-muted small fw-semibold">
                    <i class="fas fa-link me-1"></i>{{ __('Connection') }}
                </div>
                <h1 class="h4 mb-0">
                    @if(!empty($rec['slug']))
                        <a href="{{ url('/'.$rec['slug']) }}" class="text-decoration-none">{{ $recTitle }}</a>
                    @else
                        {{ $recTitle }}
                    @endif
                </h1>
            </div>
            <span class="badge rounded-pill bg-{{ $conf['level'] }} flex-shrink-0">{{ __($conf['label']) }}</span>
        </div>

        <div class="card-body">

            {{-- Grounding disclaimer --}}
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <i class="fas fa-triangle-exclamation fa-lg me-3 mt-1"></i>
                <div>
                    <strong>{{ __('AI-generated, grounded in catalogue links - verify before citing.') }}</strong>
                    <p class="mb-0 small">
                        {{ __('This discovery was produced by an AI model that was given ONLY the catalogue connections listed below. The model cites entities by their exact catalogue names and is told never to invent facts. Treat it as a hypothesis to check against the underlying records before relying on it.') }}
                    </p>
                </div>
            </div>

            {{-- Evidence-strength meter --}}
            <div class="mb-4">
                <div class="d-flex justify-content-between small text-muted mb-1">
                    <span>{{ __('Evidence strength') }}</span>
                    <span>{{ (int) ($conf['score'] ?? 0) }}%</span>
                </div>
                <div class="progress" style="height: 6px;" role="progressbar"
                     aria-valuenow="{{ (int) ($conf['score'] ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar bg-{{ $conf['level'] }}" style="width: {{ (int) ($conf['score'] ?? 0) }}%;"></div>
                </div>
            </div>

            {{-- The AI's lead --}}
            <div class="mb-4">
                <div class="text-uppercase text-muted small fw-semibold mb-2">
                    <i class="fas fa-lightbulb me-1 text-warning"></i>{{ __('What the AI noticed') }}
                </div>
                @if(count($insights) > 0)
                    <ul class="mb-0 ps-3">
                        @foreach($insights as $insight)
                            <li class="mb-2">{{ $insight }}</li>
                        @endforeach
                    </ul>
                @elseif(trim($summary) !== '')
                    <p class="mb-0">{{ $summary }}</p>
                @else
                    <p class="text-muted small mb-0">
                        <i class="fas fa-circle-info me-1"></i>
                        {{ __('The AI did not surface a non-obvious lead beyond the direct links shown below. The verified connections still stand.') }}
                    </p>
                @endif
            </div>

            {{-- The verified linked records/entities --}}
            <div class="mb-2">
                <div class="text-uppercase text-muted small fw-semibold mb-2">
                    <i class="fas fa-sitemap me-1"></i>{{ __('Linked records and entities') }}
                    <span class="badge bg-light text-dark border ms-1">{{ __('verified') }}</span>
                </div>
                @if(count($links) > 0)
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($links as $link)
                            @if(!empty($link['slug']))
                                <a href="{{ url('/'.$link['slug']) }}"
                                   class="badge text-bg-light border text-decoration-none"
                                   title="{{ $link['domain'] ?? '' }}">{{ $link['name'] }}</a>
                            @else
                                <span class="badge text-bg-light border"
                                      title="{{ $link['domain'] ?? '' }}">{{ $link['name'] }}</span>
                            @endif
                        @endforeach
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
                @if($genAt)
                    &middot; <i class="fas fa-clock-rotate-left me-1"></i>{{ __('Generated') }} {{ \Illuminate\Support\Carbon::parse($genAt)->toDayDateTimeString() }}
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
@endsection
