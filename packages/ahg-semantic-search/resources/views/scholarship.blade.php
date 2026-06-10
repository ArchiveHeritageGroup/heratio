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

</div>
@endsection
