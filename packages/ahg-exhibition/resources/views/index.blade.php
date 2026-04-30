{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Exhibition Dashboard — PSIS parity clone of
  ahgExhibitionPlugin/modules/exhibition/templates/dashboardSuccess.php
  rendered in the clean Heratio Bootstrap 5 theme.
--}}
@extends('theme::layouts.1col')

@section('title', 'Exhibition Dashboard')

@section('content')
@php
  $exhibitions = $exhibitions ?? collect();
  $statuses = $statuses ?? [];
  $types = $types ?? [];
  $stats = $stats ?? [];
  $filters = $filters ?? [];
  $page = $page ?? 1;
  $pages = $pages ?? 1;
  $currentExhibitions = $currentExhibitions ?? [];
  $upcomingExhibitions = $upcomingExhibitions ?? [];
  $pendingChecklists = $pendingChecklists ?? [];
  $recentActivity = $recentActivity ?? [];
  $calendarEvents = $calendarEvents ?? [];

  $statusLabel = function ($key) use ($statuses) {
    if (isset($statuses[$key])) {
      $s = $statuses[$key];
      return is_array($s) ? ($s['label'] ?? $key) : $s;
    }
    return ucwords(str_replace('_', ' ', (string) $key));
  };
@endphp

{{-- Page header --}}
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">{{ __('Exhibition Dashboard') }}</h1>
  <div class="btn-group">
    <a href="{{ route('exhibition.add') }}" class="btn btn-success btn-sm">
      <i class="fas fa-plus me-1"></i> {{ __('New Exhibition') }}
    </a>
    <a href="{{ route('exhibition.index') }}#all-exhibitions" class="btn btn-outline-secondary btn-sm">
      <i class="fas fa-list me-1"></i> {{ __('All Exhibitions') }}
    </a>
  </div>
</div>

{{-- KPI stat cards --}}
<div class="row g-3 mb-4">
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted text-uppercase small mb-1">Total Exhibitions</div>
        <div class="display-6 fw-semibold text-primary mb-0">{{ $stats['total_exhibitions'] ?? 0 }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted text-uppercase small mb-1">Currently Open</div>
        <div class="display-6 fw-semibold text-success mb-0">{{ $stats['current_exhibitions'] ?? 0 }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted text-uppercase small mb-1">Upcoming</div>
        <div class="display-6 fw-semibold text-info mb-0">{{ $stats['upcoming_exhibitions'] ?? 0 }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3 col-sm-6">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-body text-center">
        <div class="text-muted text-uppercase small mb-1">Objects on Display</div>
        <div class="display-6 fw-semibold text-secondary mb-0">{{ $stats['total_objects_on_display'] ?? 0 }}</div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  {{-- Main column --}}
  <div class="col-lg-8">
    {{-- Currently Open --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Currently Open') }}</h5>
        <a href="{{ route('exhibition.index', ['status' => 'open']) }}" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        @if(empty($currentExhibitions))
          <div class="p-4 text-center text-muted">
            <i class="fas fa-calendar-check fa-2x mb-2 d-block"></i>
            <p class="mb-0">No exhibitions currently open</p>
          </div>
        @else
          <div class="list-group list-group-flush">
            @foreach($currentExhibitions as $exhibition)
              @php $ex = (array) $exhibition; @endphp
              <a href="{{ route('exhibition.show', ['id' => $ex['id']]) }}" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                  <h6 class="mb-1">{{ $ex['title'] ?? '' }}</h6>
                  <small class="text-muted">{{ $ex['object_count'] ?? 0 }} objects</small>
                </div>
                <p class="mb-1 small text-muted">
                  @if(!empty($ex['venue_name']))
                    <i class="fas fa-map-marker-alt me-1"></i> {{ $ex['venue_name'] }}
                  @endif
                  @if(!empty($ex['closing_date']))
                    <span class="ms-2"><i class="fas fa-calendar me-1"></i> Closes: {{ $ex['closing_date'] }}</span>
                  @endif
                </p>
                @if(!empty($ex['closing_date']))
                  @php
                    try {
                      $closing = \Carbon\Carbon::parse($ex['closing_date']);
                      $daysLeft = max(0, now()->startOfDay()->diffInDays($closing, false));
                    } catch (\Throwable $e) { $daysLeft = null; }
                  @endphp
                  @if($daysLeft !== null && $daysLeft <= 30)
                    <span class="badge bg-warning text-dark">{{ $daysLeft }} days remaining</span>
                  @endif
                @endif
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- Upcoming Exhibitions --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('Upcoming Exhibitions') }}</h5>
        <a href="{{ route('exhibition.index', ['status' => 'preparation']) }}" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        @if(empty($upcomingExhibitions))
          <div class="p-4 text-center text-muted">
            <i class="fas fa-calendar fa-2x mb-2 d-block"></i>
            <p class="mb-0">No upcoming exhibitions scheduled</p>
          </div>
        @else
          <div class="list-group list-group-flush">
            @foreach($upcomingExhibitions as $exhibition)
              @php $ex = (array) $exhibition; @endphp
              <a href="{{ route('exhibition.show', ['id' => $ex['id']]) }}" class="list-group-item list-group-item-action">
                <div class="d-flex w-100 justify-content-between">
                  <h6 class="mb-1">{{ $ex['title'] ?? '' }}</h6>
                  <span class="badge bg-secondary">{{ $statusLabel($ex['status'] ?? '') }}</span>
                </div>
                <p class="mb-1 small text-muted">
                  @if(!empty($ex['opening_date']))
                    <i class="fas fa-calendar me-1"></i> Opens: {{ $ex['opening_date'] }}
                  @endif
                  @if(!empty($ex['venue_name']))
                    <span class="ms-2"><i class="fas fa-map-marker-alt me-1"></i> {{ $ex['venue_name'] }}</span>
                  @endif
                </p>
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- Workflow Overview --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">{{ __('Workflow Overview') }}</h5>
      </div>
      <div class="card-body">
        @if(empty($stats['by_status']))
          <p class="text-muted mb-0 text-center">No workflow data available.</p>
        @else
          <div class="row text-center g-3">
            @foreach($stats['by_status'] as $statusKey => $count)
              <div class="col">
                <div class="p-2">
                  <div class="rounded-circle bg-light border d-inline-flex align-items-center justify-content-center mb-2"
                       style="width: 56px; height: 56px;">
                    <strong class="text-dark">{{ $count }}</strong>
                  </div>
                  <p class="mb-0 small text-muted">{{ $statusLabel($statusKey) }}</p>
                </div>
              </div>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    {{-- All Exhibitions (preserved filter + table) --}}
    <div class="card shadow-sm mb-4" id="all-exhibitions">
      <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ __('All Exhibitions') }}</h5>
          <small class="text-muted">{{ $total ?? 0 }} total</small>
        </div>
        <form method="get" action="{{ route('exhibition.index') }}#all-exhibitions" class="row g-2 align-items-center">
          <div class="col-md-4">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('Search...') }}" value="{{ $filters['search'] ?? '' }}">
          </div>
          <div class="col-md-3">
            <select name="status" class="form-select form-select-sm">
              <option value="">{{ __('All Statuses') }}</option>
              @foreach($statuses as $key => $status)
                <option value="{{ $key }}" {{ ($filters['status'] ?? '') == $key ? 'selected' : '' }}>
                  {{ is_array($status) ? ($status['label'] ?? $key) : $status }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <select name="type" class="form-select form-select-sm">
              <option value="">{{ __('All Types') }}</option>
              @foreach($types as $key => $label)
                <option value="{{ $key }}" {{ ($filters['exhibition_type'] ?? '') == $key ? 'selected' : '' }}>
                  {{ is_array($label) ? ($label['label'] ?? $key) : $label }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 text-end">
            <button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button>
            <a href="{{ route('exhibition.index') }}#all-exhibitions" class="btn btn-outline-secondary btn-sm">Clear</a>
          </div>
        </form>
      </div>
      <div class="card-body p-0">
        @if(empty($exhibitions) || (is_object($exhibitions) && $exhibitions->isEmpty()))
          <div class="p-4 text-center text-muted">
            <i class="fas fa-image fa-2x mb-2 d-block"></i>
            <p class="mb-0">No exhibitions found</p>
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>{{ __('Title') }}</th>
                  <th>{{ __('Type') }}</th>
                  <th>{{ __('Status') }}</th>
                  <th>{{ __('Opens') }}</th>
                  <th>{{ __('Closes') }}</th>
                  <th>{{ __('Venue') }}</th>
                  <th class="text-end"></th>
                </tr>
              </thead>
              <tbody>
                @foreach($exhibitions as $exhibition)
                  @php $ex = (object) $exhibition; @endphp
                  <tr>
                    <td>
                      <a href="{{ route('exhibition.show', ['id' => $ex->id]) }}">
                        <strong>{{ $ex->title ?? '' }}</strong>
                      </a>
                      @if(!empty($ex->subtitle))
                        <br><small class="text-muted">{{ $ex->subtitle }}</small>
                      @endif
                    </td>
                    <td>{{ $ex->exhibition_type ?? '' }}</td>
                    <td><span class="badge bg-secondary">{{ $statusLabel($ex->status ?? '') }}</span></td>
                    <td>{{ $ex->opening_date ?? '-' }}</td>
                    <td>{{ $ex->closing_date ?? '-' }}</td>
                    <td>{{ $ex->venue_name ?? '-' }}</td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="{{ route('exhibition.show', ['id' => $ex->id]) }}" class="btn btn-outline-primary" title="{{ __('View') }}">
                          <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('exhibition.edit', ['id' => $ex->id]) }}" class="btn btn-outline-secondary" title="{{ __('Edit') }}">
                          <i class="fas fa-edit"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @if($pages > 1)
        <div class="card-footer bg-white">
          <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
              @if($page > 1)
                <li class="page-item"><a class="page-link" href="?page={{ $page - 1 }}#all-exhibitions">&laquo;</a></li>
              @endif
              @for($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++)
                <li class="page-item {{ $i == $page ? 'active' : '' }}">
                  <a class="page-link" href="?page={{ $i }}#all-exhibitions">{{ $i }}</a>
                </li>
              @endfor
              @if($page < $pages)
                <li class="page-item"><a class="page-link" href="?page={{ $page + 1 }}#all-exhibitions">&raquo;</a></li>
              @endif
            </ul>
          </nav>
        </div>
      @endif
    </div>
  </div>

  {{-- Sidebar --}}
  <div class="col-lg-4">
    {{-- Quick Actions --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">{{ __('Quick Actions') }}</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.add') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-plus me-2 text-success"></i> {{ __('Create New Exhibition') }}
        </a>
        <a href="{{ route('exhibition.index') }}#all-exhibitions" class="list-group-item list-group-item-action">
          <i class="fas fa-list me-2 text-primary"></i> {{ __('View All Exhibitions') }}
        </a>
        <a href="{{ route('museum.browse') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-archive me-2 text-secondary"></i> {{ __('Object Registry') }}
        </a>
        <a href="{{ route('loan.index') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-exchange-alt me-2 text-warning"></i> {{ __('Manage Loans') }}
        </a>
        <a href="{{ route('loan.create') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-arrow-right-from-bracket me-2 text-info"></i> {{ __('New Loan Out') }}
        </a>
      </div>
    </div>

    {{-- Pending Checklist Items --}}
    @if(!empty($pendingChecklists))
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0">{{ __('Pending Checklist Items') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(array_slice($pendingChecklists, 0, 5) as $item)
            @php $it = (array) $item; @endphp
            <li class="list-group-item">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <strong class="small">{{ $it['task_name'] ?? '' }}</strong>
                  <br>
                  <small class="text-muted">{{ $it['exhibition_title'] ?? '' }}</small>
                </div>
                @if(!empty($it['due_date']))
                  @php
                    try {
                      $due = \Carbon\Carbon::parse($it['due_date']);
                      $overdue = $due->isPast();
                    } catch (\Throwable $e) { $overdue = false; }
                  @endphp
                  <span class="badge {{ $overdue ? 'bg-danger' : 'bg-warning text-dark' }}">
                    {{ $it['due_date'] }}
                  </span>
                @endif
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Recent Activity --}}
    @if(!empty($recentActivity))
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h5 class="mb-0">{{ __('Recent Activity') }}</h5>
        </div>
        <ul class="list-group list-group-flush">
          @foreach(array_slice($recentActivity, 0, 8) as $activity)
            @php $a = (array) $activity; @endphp
            <li class="list-group-item py-2">
              <small>
                <strong>{{ $a['exhibition_title'] ?? '' }}</strong><br>
                <span class="text-muted">
                  {{ $a['transition'] ?? '' }}
                  @if(!empty($a['created_at']))
                    &mdash; {{ \Carbon\Carbon::parse($a['created_at'])->format('M j') }}
                  @endif
                </span>
              </small>
            </li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Calendar --}}
    <div class="card shadow-sm mb-4">
      <div class="card-header bg-white">
        <h5 class="mb-0">{{ __('Calendar') }}</h5>
      </div>
      <div class="card-body">
        <p class="small text-muted mb-2">Next 30 Days</p>
        @if(!empty($calendarEvents))
          <ul class="list-unstyled mb-0">
            @foreach(array_slice($calendarEvents, 0, 5) as $event)
              @php $e = (array) $event; @endphp
              <li class="mb-2">
                <small>
                  <strong>
                    @if(!empty($e['event_date']))
                      {{ \Carbon\Carbon::parse($e['event_date'])->format('M j') }}
                    @endif
                  </strong>
                  &mdash; {{ $e['title'] ?? '' }}
                </small>
              </li>
            @endforeach
          </ul>
        @else
          <p class="small text-muted mb-0">No events scheduled</p>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
