{{--
  Question Builder - version history (heratio#1226, ROS Stage 2)
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.

  Lists every saved version of a project's Research Design Brief newest-first,
  with the reason recorded for each change. Versions are immutable.
--}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-history me-2"></i>{{ __('Brief History') }} - {{ e($project->title) }}</h1>
    <p class="text-muted mb-0">{{ __('Every save creates a new version. Earlier versions are kept for the audit trail.') }}</p>
@endsection
@section('content')

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.projects') }}">{{ __('Projects') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id) }}">{{ e($project->title) }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.question.builder', $project->id) }}">{{ __('Question Builder') }}</a></li>
        <li class="breadcrumb-item active">{{ __('History') }}</li>
    </ol>
</nav>

<div class="mb-3">
    <a href="{{ route('research.question.builder', $project->id) }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to builder') }}
    </a>
</div>

@php
    $labels = [
        'broad_topic'         => __('Broad topic'),
        'problem_statement'   => __('Problem statement'),
        'research_gap'        => __('Research gap'),
        'primary_question'    => __('Primary research question'),
        'secondary_questions' => __('Secondary questions'),
        'hypothesis'          => __('Hypothesis'),
        'scope_boundaries'    => __('Scope and boundaries'),
        'key_definitions'     => __('Key definitions'),
        'assumptions'         => __('Assumptions'),
        'bias_risks'          => __('Bias and risks'),
    ];
@endphp

@if(empty($versions))
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
            <p class="mb-2">{{ __('No versions saved yet.') }}</p>
            <a href="{{ route('research.question.builder', $project->id) }}" class="btn btn-primary btn-sm">
                <i class="fas fa-pen-nib me-1"></i>{{ __('Start the brief') }}
            </a>
        </div>
    </div>
@else
    <div class="accordion" id="qbHistory">
        @foreach($versions as $i => $v)
            <div class="accordion-item">
                <h2 class="accordion-header" id="qbh-h-{{ $v->id }}">
                    <button class="accordion-button {{ $i === 0 ? '' : 'collapsed' }}" type="button"
                        data-bs-toggle="collapse" data-bs-target="#qbh-c-{{ $v->id }}"
                        aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="qbh-c-{{ $v->id }}">
                        <span class="badge bg-secondary me-2">v{{ $v->version_no }}</span>
                        @if($i === 0)<span class="badge bg-success me-2">{{ __('Current') }}</span>@endif
                        <span class="me-3">{{ $v->created_at ?? '-' }}</span>
                        <span class="text-muted small">{{ $v->change_reason ? e($v->change_reason) : __('No reason recorded') }}</span>
                    </button>
                </h2>
                <div id="qbh-c-{{ $v->id }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}"
                    aria-labelledby="qbh-h-{{ $v->id }}" data-bs-parent="#qbHistory">
                    <div class="accordion-body">
                        @if($v->change_reason)
                            <p class="mb-3"><strong>{{ __('Reason for change') }}:</strong> {{ e($v->change_reason) }}</p>
                        @endif
                        <dl class="row mb-0">
                            @foreach($labels as $f => $label)
                                @php $val = trim((string) ($v->$f ?? '')); @endphp
                                <dt class="col-sm-3 text-muted">{{ $label }}</dt>
                                <dd class="col-sm-9">{!! $val === '' ? '<span class="text-muted fst-italic">' . e(__('Not set')) . '</span>' : nl2br(e($val)) !!}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
