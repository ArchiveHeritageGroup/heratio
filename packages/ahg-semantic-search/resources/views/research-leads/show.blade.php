{{--
  Research Lead - public detail view (heratio#1210)

  One published research lead in full: the connection's centre record, the
  plain-language "why this might matter" prompt, the AI lead text, the confidence
  band, and every verified linked record/entity (each a link). Read-only and
  published-only. International, jurisdiction-neutral copy.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@php
    $rec = $lead['record'] ?? ['id' => 0, 'title' => null, 'slug' => null];
    $recTitle = $lead['headline'] ?: ($rec['title'] ?: (__('Record').' #'.($rec['id'] ?? '')));
    $conf = $lead['confidence'] ?? ['label' => __('Tentative'), 'level' => 'secondary', 'score' => 5];
    $links = $lead['links'] ?? [];
@endphp

@section('title', __('Research Lead').': '.$recTitle)

@section('content')
<div class="container py-4">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('research-leads.index') }}">{{ __('Research Leads') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ \Illuminate\Support\Str::limit($recTitle, 60) }}</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-uppercase text-muted small fw-semibold">
                <i class="fas fa-lightbulb me-1 text-warning"></i>{{ __('Research lead') }}
            </div>
            <h1 class="h3 mb-0">{{ $recTitle }}</h1>
        </div>
        <span class="badge rounded-pill bg-{{ $conf['level'] }} fs-6 flex-shrink-0">
            {{ __($conf['label']) }}
        </span>
    </div>

    {{-- Standing disclaimer --}}
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="fas fa-robot fa-lg me-3 mt-1"></i>
        <div>
            <strong>{{ __('AI-generated lead, grounded in catalogue links - verify before citing.') }}</strong>
            <p class="mb-0 small">{{ $disclaimer }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">

            {{-- Why this might matter --}}
            @if(!empty($lead['why_it_matters']))
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="text-uppercase text-muted small fw-semibold mb-2">
                            <i class="fas fa-circle-question me-1"></i>{{ __('Why this might matter') }}
                        </div>
                        <p class="mb-0 lead fs-6">{{ $lead['why_it_matters'] }}</p>
                    </div>
                </div>
            @endif

            {{-- The connection / AI lead text --}}
            @if(!empty($lead['lead_text']))
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="text-uppercase text-muted small fw-semibold mb-2">
                            <i class="fas fa-link me-1"></i>{{ __('The connection') }}
                        </div>
                        @foreach(preg_split('/\r\n|\r|\n/', (string) $lead['lead_text']) as $line)
                            @php $line = trim($line); @endphp
                            @if($line !== '')
                                <p class="mb-2">{{ $line }}</p>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Verified linked records and entities --}}
            <div class="card shadow-sm mb-4">
                <div class="card-body">
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

        </div>

        <div class="col-12 col-lg-4">
            <div class="card shadow-sm">
                <div class="card-body">

                    {{-- Evidence strength --}}
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

                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span class="text-muted">{{ __('Direct links') }}</span>
                            <span class="fw-semibold">{{ (int) ($lead['connection_count'] ?? 0) }}</span>
                        </li>
                        @if((int) ($lead['second_hop'] ?? 0) > 0)
                            <li class="d-flex justify-content-between py-1 border-bottom">
                                <span class="text-muted">{{ __('Indirect links') }}</span>
                                <span class="fw-semibold">{{ (int) $lead['second_hop'] }}</span>
                            </li>
                        @endif
                        @if(!empty($lead['domains']))
                            <li class="d-flex justify-content-between py-1">
                                <span class="text-muted">{{ __('Domains') }}</span>
                                <span class="fw-semibold text-end">{{ implode(', ', $lead['domains']) }}</span>
                            </li>
                        @endif
                    </ul>

                    @if(!empty($rec['slug']))
                        <a href="{{ url('/'.$rec['slug']) }}" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-arrow-right me-1"></i>{{ __('Open the record') }}
                        </a>
                    @endif
                    <a href="{{ route('research-leads.index') }}" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to all leads') }}
                    </a>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection
