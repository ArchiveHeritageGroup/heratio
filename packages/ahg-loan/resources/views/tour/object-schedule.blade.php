@extends('theme::layouts.1col')

@section('title', 'Tour schedule - ' . ($object['title'] ?? ('Object #' . $objectId)))
@section('body-class', 'view loan tour-schedule')

@section('content')

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-start flex-wrap mb-3">
    <div>
      <h1 class="h3 mb-1">
        <i class="bi bi-calendar-range me-1"></i>
        Touring schedule
      </h1>
      <div class="text-muted">
        {{ $object['title'] ?? ('Object #' . $objectId) }}
        @if(!empty($object['identifier']))
          <span class="badge bg-secondary ms-1">{{ $object['identifier'] }}</span>
        @endif
      </div>
    </div>
    <a href="{{ route('loan.index') }}" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> All loans
    </a>
  </div>

  <div class="row g-4">

    {{-- Existing timeline of commitments --}}
    <div class="col-lg-7">
      <div class="card">
        <div class="card-header bg-light fw-semibold">
          <i class="bi bi-list-task me-1"></i> Committed engagements
        </div>
        <div class="card-body p-0">
          @if(empty($timeline))
            <div class="p-4 text-muted text-center">
              No tour stops, loans, or on-display windows recorded for this object yet.
            </div>
          @else
            <div class="table-responsive">
              <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Type</th>
                    <th>Venue / institution</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($timeline as $row)
                    @php
                      $badge = $row['source'] === 'tour' ? 'primary'
                             : ($row['source'] === 'loan' ? 'info' : 'warning');
                    @endphp
                    <tr>
                      <td><span class="badge bg-{{ $badge }} text-uppercase">{{ $row['ref'] }}</span></td>
                      <td>{{ $row['label'] ?: '—' }}</td>
                      <td><span class="text-nowrap">{{ $row['start'] }}</span></td>
                      <td><span class="text-nowrap">{{ $row['end'] }}</span></td>
                      <td><small class="text-muted">{{ str_replace('_', ' ', (string) $row['status']) }}</small></td>
                      <td class="text-end">
                        @if($row['source'] === 'tour' && !empty($row['booking_id']))
                          <form method="POST"
                                action="{{ route('loan.tour.cancel', ['objectId' => $objectId, 'bookingId' => $row['booking_id']]) }}"
                                onsubmit="return confirm('Cancel this tour booking and free the window?');"
                                class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel booking">
                              <i class="bi bi-x-circle"></i>
                            </button>
                          </form>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Check + book form --}}
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header bg-light fw-semibold">
          <i class="bi bi-plus-circle me-1"></i> Book a venue for a date range
        </div>
        <div class="card-body">

          @if(!empty($conflicts))
            <div class="alert alert-danger">
              <div class="fw-semibold mb-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ count($conflicts) }} conflict(s) - this object is already committed:
              </div>
              <ul class="mb-0 ps-3">
                @foreach($conflicts as $c)
                  <li>
                    <strong>{{ ucfirst($c['source']) }}</strong>
                    {{ $c['ref'] }}
                    @if(!empty($c['label'])) ({{ $c['label'] }}) @endif
                    - {{ $c['start'] }} to {{ $c['end'] }}
                  </li>
                @endforeach
              </ul>
            </div>
          @endif

          <form method="POST" action="{{ route('loan.tour.book', $objectId) }}">
            @csrf

            <div class="mb-3">
              <label class="form-label">Venue / hosting institution <span class="text-danger">*</span></label>
              <input type="text" name="venue_name" class="form-control" required
                     value="{{ old('venue_name', $attempt['venue_name'] ?? '') }}"
                     placeholder="e.g. National Gallery, City Museum">
            </div>

            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">City</label>
                <input type="text" name="venue_city" class="form-control"
                       value="{{ old('venue_city', $attempt['venue_city'] ?? '') }}">
              </div>
              <div class="col">
                <label class="form-label">Country</label>
                <input type="text" name="venue_country" class="form-control"
                       value="{{ old('venue_country', $attempt['venue_country'] ?? '') }}">
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col">
                <label class="form-label">Start date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" class="form-control" required
                       value="{{ old('start_date', $attempt['start_date'] ?? '') }}">
              </div>
              <div class="col">
                <label class="form-label">End date <span class="text-danger">*</span></label>
                <input type="date" name="end_date" class="form-control" required
                       value="{{ old('end_date', $attempt['end_date'] ?? '') }}">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Commitment</label>
              <select name="status" class="form-select">
                <option value="committed" @selected(($attempt['status'] ?? 'committed') === 'committed')>Committed (firm)</option>
                <option value="tentative" @selected(($attempt['status'] ?? '') === 'tentative')>Tentative (pencilled in)</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2">{{ old('notes', $attempt['notes'] ?? '') }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary w-100">
              <i class="bi bi-check2-circle me-1"></i> Check availability and book
            </button>
            <div class="form-text mt-2">
              The booking is only saved when the window is clear of other tour stops,
              committed loans, and on-display periods for this object.
            </div>
          </form>

        </div>
      </div>
    </div>

  </div>

@endsection
