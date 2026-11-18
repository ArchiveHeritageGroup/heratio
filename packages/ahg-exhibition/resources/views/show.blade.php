{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Exhibition page (heratio#1613): status and workflow, dates, about,
  objects, sections, storylines, statistics, details, financial, upcoming
  events, checklists and reports.
--}}
@extends('theme::layouts.1col')

@section('title', $exhibition->title ?? __('Exhibition'))

@section('content')
@php
  $exId = $exhibition->id;
  $objects = collect($exhibition->objects ?? []);
  $sections = collect($exhibition->sections ?? []);
  $storylines = collect($exhibition->storylines ?? []);
  $events = collect($exhibition->events ?? []);
  $checklists = collect($exhibition->checklists ?? []);
  $stats = $exhibition->statistics ?? [];
  $timing = $exhibition->timing ?? [];
  $statusInfo = $exhibition->status_info ?? null;
  $statusColor = $statusInfo['color'] ?? '#6c757d';
  $statuses = $statuses ?? [];
  $validTransitions = $validTransitions ?? [];
  $canEdit = \AhgCore\Services\AclService::hasPermission(auth()->id(), 'update');
  $currency = $exhibition->budget_currency ?: '';
  $today = now()->toDateString();
  $upcomingEvents = $events->filter(fn ($e) => ($e->event_date ?? '') >= $today && ($e->status ?? '') !== 'canceled')->values();
  $venueLine = collect([$exhibition->venue_address ?? null, $exhibition->venue_city ?? null, $exhibition->venue_country ?? null])->filter()->implode(', ');
@endphp

<div class="row">
  <div class="col-lg-8">
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $exhibition->title }}</li>
      </ol>
    </nav>

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
      <div>
        <h1 class="mb-1">{{ $exhibition->title }}</h1>
        @if(!empty($exhibition->subtitle))
          <p class="lead text-muted mb-0">{{ $exhibition->subtitle }}</p>
        @endif
      </div>
      <div class="btn-group">
        @if($canEdit)
          <a href="{{ route('exhibition.edit', ['id' => $exId]) }}" class="btn btn-outline-primary">
            <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
          </a>
        @endif
        <button type="button" class="btn btn-outline-primary dropdown-toggle {{ $canEdit ? 'dropdown-toggle-split' : '' }}" data-bs-toggle="dropdown" aria-expanded="false">
          @if($canEdit)<span class="visually-hidden">{{ __('More') }}</span>@else {{ __('More') }} @endif
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="{{ route('exhibition.objects', ['id' => $exId]) }}"><i class="fas fa-cube me-2"></i>{{ __('Manage Objects') }}</a></li>
          <li><a class="dropdown-item" href="{{ route('exhibition.sections', ['id' => $exId]) }}"><i class="fas fa-th-large me-2"></i>{{ __('Manage Sections') }}</a></li>
          <li><a class="dropdown-item" href="{{ route('exhibition.storylines', ['id' => $exId]) }}"><i class="fas fa-book me-2"></i>{{ __('Storylines') }}</a></li>
          <li><a class="dropdown-item" href="{{ route('exhibition.events', ['id' => $exId]) }}"><i class="fas fa-calendar me-2"></i>{{ __('Events') }}</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('exhibition.checklists', ['id' => $exId]) }}"><i class="fas fa-check-square me-2"></i>{{ __('Checklists') }}</a></li>
          <li><a class="dropdown-item" href="{{ route('exhibition.objectList', ['id' => $exId]) }}"><i class="fas fa-list me-2"></i>{{ __('Object List Report') }}</a></li>
        </ul>
      </div>
    </div>

    {{-- Status bar --}}
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <span class="badge fs-6" style="background-color: {{ $statusColor }}">{{ $statusInfo['label'] ?? $exhibition->status }}</span>
            @if(!empty($exhibition->type_label))
              <span class="badge bg-secondary ms-2">{{ $exhibition->type_label }}</span>
            @endif
          </div>

          @if($canEdit && !empty($validTransitions))
            <div class="dropdown">
              <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Change Status') }}
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                @foreach($validTransitions as $transition)
                  @php $tInfo = $statuses[$transition] ?? []; @endphp
                  <li>
                    <a class="dropdown-item exhibition-transition" href="#"
                       data-status="{{ $transition }}" data-label="{{ $tInfo['label'] ?? $transition }}">
                      <span class="badge me-2" style="background-color: {{ $tInfo['color'] ?? '#6c757d' }}">&nbsp;</span>
                      {{ $tInfo['label'] ?? $transition }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </div>
          @endif
        </div>

        <div class="progress mt-3" style="height: 8px;" role="progressbar"
             aria-label="{{ __('Workflow progress') }}" aria-valuenow="{{ $exhibition->progress ?? 0 }}" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar" style="width: {{ $exhibition->progress ?? 0 }}%; background-color: {{ $statusColor }}"></div>
        </div>
      </div>
    </div>

    {{-- Dates --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>{{ __('Dates') }}</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-2 mb-md-0">
            <div class="text-muted small">{{ __('Opens') }}</div>
            <p class="mb-0 fw-bold">{{ $exhibition->opening_date ?? __('Not set') }}</p>
            @if(($timing['days_until_opening'] ?? null) !== null)
              <small class="text-info">{{ __(':n days away', ['n' => $timing['days_until_opening']]) }}</small>
            @elseif(($timing['days_since_opening'] ?? null) !== null)
              <small class="text-muted">{{ __('Opened :n days ago', ['n' => $timing['days_since_opening']]) }}</small>
            @endif
          </div>
          <div class="col-md-4 mb-2 mb-md-0">
            <div class="text-muted small">{{ __('Closes') }}</div>
            <p class="mb-0 fw-bold">{{ $exhibition->closing_date ?? __('Not set') }}</p>
            @if(($timing['days_until_closing'] ?? null) !== null)
              <small class="text-warning">{{ __(':n days remaining', ['n' => $timing['days_until_closing']]) }}</small>
            @elseif(($timing['days_since_closing'] ?? null) !== null)
              <small class="text-muted">{{ __('Closed :n days ago', ['n' => $timing['days_since_closing']]) }}</small>
            @endif
          </div>
          <div class="col-md-4">
            <div class="text-muted small">{{ __('Duration') }}</div>
            <p class="mb-0 fw-bold">
              @if(($timing['duration_days'] ?? null) !== null)
                {{ __(':n days', ['n' => $timing['duration_days']]) }}
              @else
                -
              @endif
            </p>
          </div>
        </div>
      </div>
    </div>

    {{-- About --}}
    @if(!empty($exhibition->description) || !empty($exhibition->theme))
      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About') }}</h5>
        </div>
        <div class="card-body">
          @if(!empty($exhibition->theme))
            <p><strong>{{ __('Theme') }}:</strong> {{ $exhibition->theme }}</p>
          @endif
          @if(!empty($exhibition->description))
            <p class="mb-0">{!! nl2br(e($exhibition->description)) !!}</p>
          @endif
        </div>
      </div>
    @endif

    {{-- Objects --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-boxes-stacked me-2"></i>{{ __('Objects') }} ({{ $objects->count() }})</h5>
        <a href="{{ route('exhibition.objects', ['id' => $exId]) }}" class="btn btn-sm btn-outline-primary">{{ __('Manage Objects') }}</a>
      </div>
      @if($objects->isEmpty())
        <div class="card-body text-muted">{{ __('No objects have been added to this exhibition yet.') }}</div>
      @else
        <div class="list-group list-group-flush">
          @foreach($objects->take(5) as $obj)
            <div class="list-group-item d-flex align-items-center">
              @if(!empty($obj->thumbnail_url))
                <img src="{{ $obj->thumbnail_url }}" alt="" class="me-3 rounded" style="width: 50px; height: 50px; object-fit: cover;" loading="lazy">
              @else
                <div class="me-3 bg-secondary text-white d-flex align-items-center justify-content-center rounded" style="width: 50px; height: 50px;" aria-hidden="true">
                  <i class="fas fa-cube"></i>
                </div>
              @endif
              <div class="flex-grow-1">
                @if(!empty($obj->slug))
                  <a href="{{ url('/'.$obj->slug) }}"><strong>{{ $obj->object_title ?: __('Untitled') }}</strong></a>
                @else
                  <strong>{{ $obj->object_title ?: __('Untitled') }}</strong>
                @endif
                <br><small class="text-muted">{{ $obj->identifier ?: '-' }}@if(!empty($obj->section_title)) &middot; {{ $obj->section_title }}@endif</small>
              </div>
              <span class="badge bg-secondary">{{ $obj->status_label }}</span>
            </div>
          @endforeach
          @if($objects->count() > 5)
            <div class="list-group-item text-center">
              <a href="{{ route('exhibition.objects', ['id' => $exId]) }}">{{ __('View all :n objects', ['n' => $objects->count()]) }}</a>
            </div>
          @endif
        </div>
      @endif
    </div>

    {{-- Sections --}}
    @if($sections->isNotEmpty())
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-th-large me-2"></i>{{ __('Sections') }} ({{ $sections->count() }})</h5>
          <a href="{{ route('exhibition.sections', ['id' => $exId]) }}" class="btn btn-sm btn-outline-primary">{{ __('Manage') }}</a>
        </div>
        <div class="list-group list-group-flush">
          @foreach($sections as $section)
            <div class="list-group-item">
              <div class="d-flex justify-content-between">
                <strong>{{ $section->title }}</strong>
                <span class="badge bg-info">{{ trans_choice(':n object|:n objects', (int) $section->object_count, ['n' => (int) $section->object_count]) }}</span>
              </div>
              @if(!empty($section->gallery_name))
                <small class="text-muted d-block">{{ $section->gallery_name }}</small>
              @endif
              @if(!empty($section->description))
                <small class="text-muted">{{ \Illuminate\Support\Str::limit($section->description, 100) }}</small>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @endif

    {{-- Storylines --}}
    @if($storylines->isNotEmpty())
      <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="fas fa-book me-2"></i>{{ __('Storylines') }}</h5>
          <a href="{{ route('exhibition.storylines', ['id' => $exId]) }}" class="btn btn-sm btn-outline-primary">{{ __('Manage') }}</a>
        </div>
        <div class="list-group list-group-flush">
          @foreach($storylines as $storyline)
            <a href="{{ route('exhibition.storyline', ['exhibitionId' => $exId, 'storylineId' => $storyline->id]) }}" class="list-group-item list-group-item-action">
              <div class="d-flex justify-content-between">
                <strong>
                  @if(!empty($storyline->is_primary))
                    <i class="fas fa-star text-warning me-1" title="{{ __('Primary storyline') }}"></i>
                  @endif
                  {{ $storyline->title }}
                </strong>
                <span class="badge bg-secondary">{{ trans_choice(':n stop|:n stops', (int) $storyline->stop_count, ['n' => (int) $storyline->stop_count]) }}</span>
              </div>
              <small class="text-muted">
                {{ $storyline->narrative_type_label }}
                @if(!empty($storyline->estimated_duration_minutes))
                  &middot; ~{{ __(':n min', ['n' => $storyline->estimated_duration_minutes]) }}
                @endif
              </small>
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </div>

  <div class="col-lg-4">
    {{-- Statistics --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Statistics') }}</h5></div>
      <div class="card-body">
        <div class="row text-center">
          <div class="col-6 mb-3">
            <div class="fs-4 fw-bold text-primary">{{ $stats['object_count'] ?? 0 }}</div>
            <small class="text-muted">{{ __('Objects') }}</small>
          </div>
          <div class="col-6 mb-3">
            <div class="fs-4 fw-bold text-info">{{ $stats['section_count'] ?? 0 }}</div>
            <small class="text-muted">{{ __('Sections') }}</small>
          </div>
          <div class="col-6">
            <div class="fs-4 fw-bold text-success">{{ $stats['storyline_count'] ?? 0 }}</div>
            <small class="text-muted">{{ __('Storylines') }}</small>
          </div>
          <div class="col-6">
            <div class="fs-4 fw-bold text-warning">{{ $stats['event_count'] ?? 0 }}</div>
            <small class="text-muted">{{ __('Events') }}</small>
          </div>
        </div>
      </div>
    </div>

    {{-- Details --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Details') }}</h5></div>
      <ul class="list-group list-group-flush">
        <li class="list-group-item d-flex justify-content-between gap-3">
          <span class="text-muted">{{ __('Venue') }}</span>
          <span class="text-end">
            {{ $exhibition->venue_name ?: '-' }}
            @if($venueLine !== '')<br><small class="text-muted">{{ $venueLine }}</small>@endif
          </span>
        </li>
        <li class="list-group-item d-flex justify-content-between gap-3">
          <span class="text-muted">{{ __('Curator') }}</span>
          <span class="text-end">{{ $exhibition->curator_name ?: '-' }}</span>
        </li>
        @if(!empty($exhibition->designer_name))
          <li class="list-group-item d-flex justify-content-between gap-3">
            <span class="text-muted">{{ __('Designer') }}</span>
            <span class="text-end">{{ $exhibition->designer_name }}</span>
          </li>
        @endif
        <li class="list-group-item d-flex justify-content-between gap-3">
          <span class="text-muted">{{ __('Organized by') }}</span>
          <span class="text-end">{{ $exhibition->organized_by ?: '-' }}</span>
        </li>
        @if(!empty($exhibition->project_code))
          <li class="list-group-item d-flex justify-content-between gap-3">
            <span class="text-muted">{{ __('Project Code') }}</span>
            <span class="text-end">{{ $exhibition->project_code }}</span>
          </li>
        @endif
        @if(!empty($exhibition->expected_visitors))
          <li class="list-group-item d-flex justify-content-between gap-3">
            <span class="text-muted">{{ __('Expected Visitors') }}</span>
            <span class="text-end">{{ number_format((int) $exhibition->expected_visitors) }}</span>
          </li>
        @endif
        @php
          $access = collect([
            'wheelchair_accessible' => __('Wheelchair accessible'),
            'audio_guide_available' => __('Audio guide'),
            'braille_available' => __('Braille'),
            'sign_language_tours' => __('Sign language tours'),
          ])->filter(fn ($label, $col) => !empty($exhibition->{$col}));
        @endphp
        @if($access->isNotEmpty())
          <li class="list-group-item d-flex justify-content-between gap-3">
            <span class="text-muted">{{ __('Accessibility') }}</span>
            <span class="text-end">{{ $access->implode(', ') }}</span>
          </li>
        @endif
      </ul>
    </div>

    {{-- Financial --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Financial') }}</h5></div>
      <ul class="list-group list-group-flush">
        @if(!empty($exhibition->budget_amount))
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Budget') }}</span>
            <span>{{ number_format((float) $exhibition->budget_amount, 2) }} {{ $currency }}</span>
          </li>
        @endif
        @if(!empty($stats['total_insurance_value']))
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Total Insurance') }}</span>
            <span>{{ number_format((float) $stats['total_insurance_value'], 2) }} {{ $currency }}</span>
          </li>
        @endif
        @if((float) ($exhibition->admission_fee ?? 0) > 0 && empty($exhibition->is_free_admission))
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Admission') }}</span>
            <span>{{ number_format((float) $exhibition->admission_fee, 2) }} {{ $exhibition->admission_currency ?: $currency }}</span>
          </li>
        @elseif(!empty($exhibition->is_free_admission))
          <li class="list-group-item d-flex justify-content-between">
            <span class="text-muted">{{ __('Admission') }}</span>
            <span class="text-success">{{ __('Free') }}</span>
          </li>
        @endif
        @if(empty($exhibition->budget_amount) && empty($stats['total_insurance_value']) && (float) ($exhibition->admission_fee ?? 0) <= 0 && empty($exhibition->is_free_admission))
          <li class="list-group-item text-muted">{{ __('No financial details recorded.') }}</li>
        @endif
      </ul>
    </div>

    {{-- Upcoming events --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Upcoming Events') }}</h5>
        <a href="{{ route('exhibition.events', ['id' => $exId]) }}" class="btn btn-sm btn-link">{{ __('All (:n)', ['n' => $events->count()]) }}</a>
      </div>
      @if($upcomingEvents->isEmpty())
        <div class="card-body text-muted small">{{ __('No upcoming events.') }}</div>
      @else
        <div class="list-group list-group-flush">
          @foreach($upcomingEvents->take(3) as $event)
            <div class="list-group-item">
              <div class="d-flex justify-content-between gap-2">
                <strong>{{ $event->title }}</strong>
                <span class="badge bg-info">{{ $event->event_type_label }}</span>
              </div>
              <small class="text-muted">
                {{ $event->event_date }}
                @if(!empty($event->start_time))
                  {{ __('at :time', ['time' => substr($event->start_time, 0, 5)]) }}
                @endif
              </small>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    {{-- Checklists --}}
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Checklists') }}</h5>
        <a href="{{ route('exhibition.checklists', ['id' => $exId]) }}" class="btn btn-sm btn-link">{{ __('Open') }}</a>
      </div>
      @if($checklists->isEmpty())
        <div class="card-body text-muted small">{{ __('No checklists yet.') }}</div>
      @else
        <ul class="list-group list-group-flush">
          @foreach($checklists as $cl)
            <li class="list-group-item">
              <div class="d-flex justify-content-between small">
                <span>{{ $cl->name }}</span>
                <span class="text-muted">{{ $cl->completed_items }}/{{ $cl->total_items }}</span>
              </div>
              <div class="progress mt-1" style="height: 6px;" role="progressbar" aria-label="{{ $cl->name }}"
                   aria-valuenow="{{ $cl->progress }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar {{ $cl->progress >= 100 ? 'bg-success' : '' }}" style="width: {{ $cl->progress }}%"></div>
              </div>
            </li>
          @endforeach
        </ul>
      @endif
    </div>

    {{-- Reports --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">{{ __('Reports') }}</h5></div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.objectList', ['id' => $exId]) }}" class="list-group-item list-group-item-action"><i class="fas fa-list me-2"></i>{{ __('Object List') }}</a>
        <a href="{{ route('exhibition.objectListCsv', ['id' => $exId]) }}" class="list-group-item list-group-item-action"><i class="fas fa-download me-2"></i>{{ __('Export Objects (CSV)') }}</a>
        <a href="{{ route('exhibition.checklists', ['id' => $exId]) }}" class="list-group-item list-group-item-action"><i class="fas fa-check-square me-2"></i>{{ __('Checklists') }}</a>
      </div>
    </div>
  </div>
</div>

@if($canEdit && !empty($validTransitions))
  {{-- Status change --}}
  <div class="modal fade" id="exhibitionStatusModal" tabindex="-1" aria-labelledby="exhibitionStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exhibitionStatusModalLabel">{{ __('Change Status') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <p>{{ __('Change exhibition status to:') }} <strong id="exhibitionNewStatusLabel"></strong></p>
          <div class="mb-3">
            <label class="form-label" for="exhibitionStatusReason">{{ __('Reason (optional)') }}</label>
            <textarea id="exhibitionStatusReason" class="form-control" rows="2"></textarea>
          </div>
          <div class="alert alert-danger d-none" id="exhibitionStatusError" role="alert"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="button" class="btn btn-primary" id="exhibitionConfirmStatus">{{ __('Confirm') }}</button>
        </div>
      </div>
    </div>
  </div>

  @push('js')
  <script{!! \Illuminate\Support\Facades\Vite::useCspNonce() ? ' nonce="'.\Illuminate\Support\Facades\Vite::cspNonce().'"' : '' !!}>
  document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('exhibitionStatusModal');
    if (!modalEl || typeof bootstrap === 'undefined') { return; }
    var modal = new bootstrap.Modal(modalEl);
    var errorEl = document.getElementById('exhibitionStatusError');
    var selected = '';

    document.querySelectorAll('.exhibition-transition').forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        selected = this.dataset.status;
        document.getElementById('exhibitionNewStatusLabel').textContent = this.dataset.label;
        errorEl.classList.add('d-none');
        modal.show();
      });
    });

    document.getElementById('exhibitionConfirmStatus').addEventListener('click', function () {
      var body = new URLSearchParams();
      body.append('status', selected);
      body.append('reason', document.getElementById('exhibitionStatusReason').value);
      fetch(@json(route('exhibition.transition', ['id' => $exId])), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: body.toString()
      })
      .then(function (r) { return r.json().catch(function () { return {success: false, error: r.statusText}; }); })
      .then(function (data) {
        if (data.success) { location.reload(); return; }
        errorEl.textContent = data.error || data.message || @json(__('The status could not be changed.'));
        errorEl.classList.remove('d-none');
      });
    });
  });
  </script>
  @endpush
@endif
@endsection
