{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

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
      if ($ev->event_date >= $today) { $upcomingCount++; } else { $pastCount++; }
      if (($filter === 'upcoming' && $ev->event_date < $today) || ($filter === 'past' && $ev->event_date >= $today)) { continue; }
      $month = \Carbon\Carbon::parse($ev->event_date)->format('F Y');
      $grouped[$month][] = $ev;
    }
  }
@endphp

<div class="row">
  <div class="col-md-8">
    <nav aria-label="{{ __('breadcrumb') }}">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title ?? '' }}</a></li>
        <li class="breadcrumb-item active">{{ __('Events') }}</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1>{{ __('Exhibition Events') }}</h1>
      <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEventModal">
        <i class="fas fa-plus"></i> {{ __('Add Event') }}
      </button>
    </div>

    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2">
          <span class="small text-muted">{{ __('Filter:') }}</span>
          <a href="?" class="btn btn-sm {{ empty($filter) ? 'btn-primary' : 'btn-outline-primary' }}">{{ __('All') }}</a>
          <a href="?filter=upcoming" class="btn btn-sm {{ $filter == 'upcoming' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('Upcoming') }}</a>
          <a href="?filter=past" class="btn btn-sm {{ $filter == 'past' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ __('Past') }}</a>
        </div>
      </div>
    </div>

    @if(empty($events) || (is_object($events) && method_exists($events, 'isEmpty') && $events->isEmpty()))
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-calendar fa-3x text-muted mb-3"></i>
          <h5>{{ __('No events scheduled') }}</h5>
          <p class="text-muted">{{ __('Schedule events like openings, talks, workshops, and tours.') }}</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="fas fa-plus"></i> {{ __('Schedule First Event') }}
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
                        @if(!empty($event->start_time))
                          <i class="fas fa-clock me-1"></i> {{ \Carbon\Carbon::parse($event->start_time)->format('H:i') }}@if(!empty($event->end_time)) - {{ \Carbon\Carbon::parse($event->end_time)->format('H:i') }}@endif
                        @endif
                        @if(!empty($event->event_type))
                          <span class="badge bg-info ms-2">{{ $event->event_type_label ?? $event->event_type }}</span>
                        @endif
                        @if($isPast)
                          <span class="badge bg-secondary ms-2">{{ __('Past') }}</span>
                        @elseif($isToday)
                          <span class="badge bg-success ms-2">{{ __('Today') }}</span>
                        @endif
                      </p>
                    </div>
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-secondary" title="{{ __('Edit') }}"
                              data-bs-toggle="modal" data-bs-target="#editEventModal"
                              data-action="{{ route('exhibition.events.update', ['id' => $exId, 'eventId' => $event->id]) }}"
                              data-title="{{ $event->title ?? '' }}"
                              data-event-type="{{ $event->event_type ?? '' }}"
                              data-event-date="{{ $event->event_date ?? '' }}"
                              data-start-time="{{ !empty($event->start_time) ? substr($event->start_time, 0, 5) : '' }}"
                              data-end-time="{{ !empty($event->end_time) ? substr($event->end_time, 0, 5) : '' }}"
                              data-location="{{ $event->location_notes ?? '' }}"
                              data-description="{{ $event->description ?? '' }}"
                              data-max-attendees="{{ $event->max_attendees ?? '' }}"
                              data-requires-registration="{{ !empty($event->requires_registration) ? '1' : '0' }}"
                              data-is-free="{{ !empty($event->is_free) ? '1' : '0' }}"
                              data-ticket-price="{{ $event->ticket_price ?? '' }}"
                              data-presenter="{{ $event->presenter_name ?? '' }}">
                        <i class="fas fa-edit"></i>
                      </button>
                      <form method="post" action="{{ route('exhibition.events.delete', ['id' => $exId, 'eventId' => $event->id]) }}" class="d-inline"
                            data-confirm="{{ __('Delete event ":title"?', ['title' => $event->title ?? '']) }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                      </form>
                    </div>
                  </div>
                  @if(!empty($event->description))
                    <p class="small mb-2">{{ mb_substr($event->description, 0, 200) }}{{ strlen($event->description) > 200 ? '...' : '' }}</p>
                  @endif
                  <div class="d-flex gap-3 small text-muted">
                    @if(!empty($event->location_notes))
                      <span><i class="fas fa-map-marker me-1"></i> {{ $event->location_notes }}</span>
                    @endif
                    @if(!empty($event->presenter_name))
                      <span><i class="fas fa-user me-1"></i> {{ $event->presenter_name }}</span>
                    @endif
                    @if(!empty($event->max_attendees))
                      <span><i class="fas fa-users me-1"></i> {{ __('Capacity: :count', ['count' => $event->max_attendees]) }}</span>
                    @endif
                    @if(!empty($event->requires_registration))
                      <span class="text-warning"><i class="fas fa-ticket me-1"></i> {{ __('Registration Required') }}</span>
                    @endif
                    @if(!empty($event->is_free))
                      <span class="text-success"><i class="fas fa-gift me-1"></i> {{ __('Free') }}</span>
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
        <h5 class="mb-0">{{ __('Exhibition Info') }}</h5>
      </div>
      <div class="card-body">
        <h6>{{ $exhibition->title ?? '' }}</h6>
        <p class="small text-muted mb-2">
          <span class="badge bg-secondary">{{ $exhibition->status ?? '' }}</span>
        </p>
        @if(!empty($exhibition->opening_date))
          <p class="small mb-0">
            <i class="fas fa-calendar me-1"></i>
            {{ $exhibition->opening_date }}
            @if(!empty($exhibition->closing_date)) - {{ $exhibition->closing_date }} @endif
          </p>
        @endif
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Event Types') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled small mb-0">
          @foreach(($eventTypes ?? []) as $label)
            <li class="mb-1">{{ $label }}</li>
          @endforeach
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">{{ __('Summary') }}</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="d-flex justify-content-between mb-2">
            <span>{{ __('Total Events') }}</span>
            <strong>{{ is_countable($events) ? count($events) : 0 }}</strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span>{{ __('Upcoming') }}</span>
            <strong>{{ $upcomingCount }}</strong>
          </li>
          <li class="d-flex justify-content-between">
            <span>{{ __('Past') }}</span>
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
        <h5 class="modal-title">{{ __('Add Event') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="{{ route('exhibition.events.store', ['id' => $exId]) }}">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="addTitle">{{ __('Event Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="addTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addEventType">{{ __('Event Type') }} <span class="text-danger">*</span></label>
              <select name="event_type" id="addEventType" class="form-select" required>
                @foreach(($eventTypes ?? []) as $code => $label)
                  <option value="{{ $code }}" @selected($code === 'other')>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addEventDate">{{ __('Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="event_date" id="addEventDate" class="form-control" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addStartTime">{{ __('Start Time') }}</label>
              <input type="time" name="start_time" id="addStartTime" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addEndTime">{{ __('End Time') }}</label>
              <input type="time" name="end_time" id="addEndTime" class="form-control">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addLocation">{{ __('Location') }}</label>
              <input type="text" name="location_notes" id="addLocation" class="form-control" maxlength="255" placeholder="{{ __('e.g., Main Hall, Lecture Theatre, Gallery A') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="addPresenter">{{ __('Presenter') }}</label>
              <input type="text" name="presenter_name" id="addPresenter" class="form-control" maxlength="255">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="addDescription">{{ __('Description') }}</label>
            <textarea name="description" id="addDescription" class="form-control" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label" for="addMaxAttendees">{{ __('Capacity') }}</label>
              <input type="number" name="max_attendees" id="addMaxAttendees" class="form-control" min="1" placeholder="{{ __('Max attendees') }}">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="requires_registration" class="form-check-input" value="1" id="addRegRequired">
                <label class="form-check-label" for="addRegRequired">{{ __('Registration Required') }}</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="is_free" class="form-check-input js-free-toggle" value="1" id="addIsFree" data-target="#addPriceRow" checked>
                <label class="form-check-label" for="addIsFree">{{ __('Free Event') }}</label>
              </div>
            </div>
          </div>
          <div class="mb-3 d-none" id="addPriceRow">
            <label class="form-label" for="addTicketPrice">{{ __('Ticket Price') }}</label>
            <input type="number" name="ticket_price" id="addTicketPrice" class="form-control" min="0" step="0.01">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Add Event') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="editEventModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">{{ __('Edit Event') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="post" action="" id="editEventForm">
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label" for="editTitle">{{ __('Event Title') }} <span class="text-danger">*</span></label>
            <input type="text" name="title" id="editTitle" class="form-control" maxlength="255" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editEventType">{{ __('Event Type') }} <span class="text-danger">*</span></label>
              <select name="event_type" id="editEventType" class="form-select" required>
                @foreach(($eventTypes ?? []) as $code => $label)
                  <option value="{{ $code }}" @selected($code === 'other')>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editEventDate">{{ __('Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="event_date" id="editEventDate" class="form-control" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editStartTime">{{ __('Start Time') }}</label>
              <input type="time" name="start_time" id="editStartTime" class="form-control">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editEndTime">{{ __('End Time') }}</label>
              <input type="time" name="end_time" id="editEndTime" class="form-control">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editLocation">{{ __('Location') }}</label>
              <input type="text" name="location_notes" id="editLocation" class="form-control" maxlength="255" placeholder="{{ __('e.g., Main Hall, Lecture Theatre, Gallery A') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label" for="editPresenter">{{ __('Presenter') }}</label>
              <input type="text" name="presenter_name" id="editPresenter" class="form-control" maxlength="255">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="editDescription">{{ __('Description') }}</label>
            <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label" for="editMaxAttendees">{{ __('Capacity') }}</label>
              <input type="number" name="max_attendees" id="editMaxAttendees" class="form-control" min="1" placeholder="{{ __('Max attendees') }}">
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="requires_registration" class="form-check-input" value="1" id="editRegRequired">
                <label class="form-check-label" for="editRegRequired">{{ __('Registration Required') }}</label>
              </div>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">&nbsp;</label>
              <div class="form-check mt-2">
                <input type="checkbox" name="is_free" class="form-check-input js-free-toggle" value="1" id="editIsFree" data-target="#editPriceRow" checked>
                <label class="form-check-label" for="editIsFree">{{ __('Free Event') }}</label>
              </div>
            </div>
          </div>
          <div class="mb-3 d-none" id="editPriceRow">
            <label class="form-label" for="editTicketPrice">{{ __('Ticket Price') }}</label>
            <input type="number" name="ticket_price" id="editTicketPrice" class="form-control" min="0" step="0.01">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('js')
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.dataset.confirm)) { e.preventDefault(); } });
  });

  function syncPrice(cb) { document.querySelector(cb.dataset.target).classList.toggle('d-none', cb.checked); }
  document.querySelectorAll('.js-free-toggle').forEach(function (cb) {
    cb.addEventListener('change', function () { syncPrice(cb); });
    syncPrice(cb);
  });

  document.getElementById('editEventModal').addEventListener('show.bs.modal', function (event) {
    var b = event.relatedTarget, d = b.dataset;
    document.getElementById('editEventForm').action = d.action;
    document.getElementById('editTitle').value = d.title || '';
    document.getElementById('editEventType').value = d.eventType || '';
    document.getElementById('editEventDate').value = d.eventDate || '';
    document.getElementById('editStartTime').value = d.startTime || '';
    document.getElementById('editEndTime').value = d.endTime || '';
    document.getElementById('editLocation').value = d.location || '';
    document.getElementById('editPresenter').value = d.presenter || '';
    document.getElementById('editDescription').value = d.description || '';
    document.getElementById('editMaxAttendees').value = d.maxAttendees || '';
    document.getElementById('editRegRequired').checked = d.requiresRegistration === '1';
    var free = document.getElementById('editIsFree');
    free.checked = d.isFree === '1';
    syncPrice(free);
    document.getElementById('editTicketPrice').value = d.ticketPrice || '';
  });
});
</script>
@endpush
