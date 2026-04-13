{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Events')

@section('content')
@php
  $exhibition = $exhibition ?? (object) [];
  $exId = $exhibition->id ?? 0;
  $events = $exhibition->events ?? collect();
  $filter = request('filter', '');

  // Group events by month
  $today = date('Y-m-d');
  $grouped = [];
  $upcomingCount = 0;
  $pastCount = 0;
  foreach ($events as $e) {
    $ev = (object) $e;
    if (!empty($ev->event_date)) {
      $month = \Carbon\Carbon::parse($ev->event_date)->format('F Y');
      $grouped[$month][] = $ev;
      if ($ev->event_date >= $today) { $upcomingCount++; } else { $pastCount++; }
    }
  }
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">Exhibitions</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">Events</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>Exhibition Events</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="fas fa-plus"></i> Add Event
      </button>
    </div>

    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted">Filter:</span>
          <a href="?" class="btn btn-sm {{ empty($filter) ? 'btn-primary' : 'btn-outline-primary' }}">All</a>
          <a href="?filter=upcoming" class="btn btn-sm {{ $filter == 'upcoming' ? 'btn-primary' : 'btn-outline-secondary' }}">Upcoming</a>
          <a href="?filter=past" class="btn btn-sm {{ $filter == 'past' ? 'btn-primary' : 'btn-outline-secondary' }}">Past</a>
        </div>
      </div>
    </div>

    @if(empty($events) || (is_object($events) && method_exists($events, 'isEmpty') && $events->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
          <h5>No events scheduled</h5>
          <p class="text-muted">Schedule events like openings, talks, workshops, and tours.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="fas fa-plus"></i> Schedule First Event
          </button>
        </div>
      </div>
    @else
      @foreach($grouped as $month => $monthEvents)
        <h5 class="text-muted mb-3">{{ $month }}</h5>
        @foreach($monthEvents as $event)
          @php
            $eventDate = $event->event_date;
            $isPast = $eventDate < $today;
            $isToday = $eventDate == $today;
          @endphp
          <div class="card mb-3 {{ $isPast ? 'opacity-75' : '' }}">
            <div class="card-body">
              <div class="row align-items-center">
                <div class="col-auto text-center" style="min-width: 80px;">
                  <div class="{{ $isToday ? 'bg-primary text-white' : ($isPast ? 'bg-secondary text-white' : 'bg-light') }} rounded p-2">
                    <div class="h4 mb-0">{{ \Carbon\Carbon::parse($eventDate)->format('d') }}</div>
                    <small>{{ \Carbon\Carbon::parse($eventDate)->format('M') }}</small>
                  </div>
                </div>
                <div class="col">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <h5 class="mb-1">{{ $event->title ?? '' }}</h5>
                      <p class="small text-muted mb-2">
                        @if(!empty($event->event_time))
                          <i class="fas fa-clock me-1"></i> {{ \Carbon\Carbon::parse($event->event_time)->format('g:i A') }}
                        @endif
                        @if(!empty($event->event_type))
                          <span class="badge bg-info ms-2 text-capitalize">{{ str_replace('_', ' ', $event->event_type) }}</span>
                        @endif
                        @if($isPast)
                          <span class="badge bg-secondary ms-2">Past</span>
                        @elseif($isToday)
                          <span class="badge bg-success ms-2">Today</span>
                        @endif
                      </p>
                    </div>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-secondary"><i class="fas fa-edit"></i></button>
                      <button type="button" class="btn btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </div>
                  </div>
                  @if(!empty($event->description))
                    <p class="small mb-2">{{ mb_substr($event->description, 0, 200) }}{{ strlen($event->description) > 200 ? '...' : '' }}</p>
                  @endif
                  <div class="d-flex gap-3 small text-muted">
                    @if(!empty($event->location))
                      <span><i class="fas fa-map-marker me-1"></i> {{ $event->location }}</span>
                    @endif
                    @if(!empty($event->capacity))
                      <span><i class="fas fa-users me-1"></i> Capacity: {{ $event->capacity }}</span>
                    @endif
                    @if(!empty($event->registration_required))
                      <span class="text-warning"><i class="fas fa-ticket me-1"></i> Registration Required</span>
                    @endif
                    @if(!empty($event->is_free))
                      <span class="text-success"><i class="fas fa-gift me-1"></i> Free</span>
                    @elseif(!empty($event->ticket_price))
                      <span><i class="fas fa-money-bill me-1"></i> {{ number_format($event->ticket_price, 2) }}</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>
          </div>
        @endforeach
      @endforeach
    @endif
  </div>

  <div class="col-md-4">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Exhibition Info</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        @if(!empty($exhibition->start_date))
          <p class="small mb-0">
            <i class="fas fa-calendar me-1"></i>
            {{ $exhibition->start_date }}
            @if(!empty($exhibition->end_date)) - {{ $exhibition->end_date }} @endif
          </p>
        @endif
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Event Types</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><strong>Opening</strong> - Exhibition launch event</li>
          <li class="mb-1"><strong>Closing</strong> - Final day celebration</li>
          <li class="mb-1"><strong>Talk</strong> - Lectures and presentations</li>
          <li class="mb-1"><strong>Tour</strong> - Guided tours</li>
          <li class="mb-1"><strong>Workshop</strong> - Hands-on activities</li>
          <li class="mb-1"><strong>Performance</strong> - Live performances</li>
          <li class="mb-1"><strong>Private View</strong> - VIP or members</li>
          <li><strong>Other</strong> - General events</li>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Summary</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="d-flex justify-content-between mb-2">
            <span>Total Events</span>
            <strong>{{ is_countable($events) ? count($events) : 0 }}</strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span>Upcoming</span>
            <strong>{{ $upcomingCount }}</strong>
          </li>
          <li class="d-flex justify-content-between">
            <span>Past</span>
            <strong>{{ $pastCount }}</strong>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="addEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Event</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.events', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Event Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Event Type</label>
              <select name="event_type" class="form-select">
                <option value="other">Other</option>
                <option value="opening">Opening</option>
                <option value="closing">Closing</option>
                <option value="talk">Talk/Lecture</option>
                <option value="tour">Tour</option>
                <option value="workshop">Workshop</option>
                <option value="performance">Performance</option>
                <option value="private_view">Private View</option>
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Date <span class="text-danger">*</span></label>
              <input type="date" name="event_date" class="form-control" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Start Time</label>
              <input type="time" name="event_time" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">End Time</label>
              <input type="time" name="end_time" class="form-control">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Capacity</label>
              <input type="number" name="capacity" class="form-control" min="1">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="registration_required" class="form-check-input" value="1" id="addRegRequired">
                <label class="form-check-label" for="addRegRequired">Registration Required</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="is_free" class="form-check-input" value="1" id="addIsFree" checked>
                <label class="form-check-label" for="addIsFree">Free Event</label>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Ticket Price</label>
            <input type="number" name="ticket_price" class="form-control" min="0" step="0.01">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Event</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
