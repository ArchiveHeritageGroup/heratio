{{-- Book Reading Room - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'book'])


@endsection

@section('content')
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <h1 class="mb-4"><i class="fas fa-calendar-plus me-2"></i>Book Reading Room</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ e($error) }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row">
  <div class="col-md-8">
  <form action="{{ route('research.book.store') }}" method="POST">
    @csrf

    {{-- Reading Room Selection --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-door-open me-2"></i>Select Reading Room</div>
      <div class="card-body">
        <div class="row">
          @forelse($rooms ?? [] as $room)
            <div class="col-md-6 mb-3">
              <div class="card {{ old('room_id') == $room->id ? 'border-primary' : '' }}">
                <div class="card-body">
                  <div class="form-check">
                    <input type="radio" name="room_id" id="room_{{ $room->id }}" value="{{ $room->id }}"
                           class="form-check-input" {{ old('room_id') == $room->id ? 'checked' : '' }} required>
                    <label for="room_{{ $room->id }}" class="form-check-label">
                      <strong>{{ e($room->name) }}</strong>
                     <span class="badge bg-secondary ms-1">Required</span></label>
                  </div>
                  @if($room->description ?? false)
                    <small class="text-muted d-block mt-1">{{ e($room->description) }}</small>
                  @endif
                  <div class="mt-2">
                    <small>
                      <i class="fas fa-chair me-1"></i>{{ $room->capacity ?? '?' }} seats
                      @if($room->equipment ?? false)
                        <span class="ms-2"><i class="fas fa-tools me-1"></i>{{ e($room->equipment) }}</span>
                      @endif
                    </small>
                  </div>
                </div>
              </div>
            </div>
          @empty
            <div class="col-12">
              <p class="text-muted">No reading rooms available.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>

    {{-- Date & Time --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-clock me-2"></i>Date &amp; Time</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="date" class="form-label">Date <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="date" name="date" id="date" class="form-control" value="{{ old('date') }}" min="{{ date('Y-m-d') }}" required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="time" name="start_time" id="start_time" class="form-control" value="{{ old('start_time') }}" required>
          </div>
          <div class="col-md-4 mb-3">
            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="time" name="end_time" id="end_time" class="form-control" value="{{ old('end_time') }}" required>
          </div>
        </div>
      </div>
    </div>

    {{-- Purpose --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-clipboard me-2"></i>Purpose</div>
      <div class="card-body">
        <div class="mb-3">
          <label for="purpose" class="form-label">Purpose of Visit <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
          <textarea name="purpose" id="purpose" class="form-control" rows="3" required>{{ old('purpose') }}</textarea>
        </div>
        <div class="mb-3">
          <label for="notes" class="form-label">Additional Notes <span class="badge bg-secondary ms-1">Optional</span></label>
          <textarea name="notes" id="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
        </div>
      </div>
    </div>

    {{-- Material Requests --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-archive me-2"></i>Material Requests</div>
      <div class="card-body">
        <p class="text-muted small">Search and select archival materials you would like to have prepared for your visit.</p>
        <select id="material-select" name="materials[]" multiple placeholder="{{ __('Type to search records...') }}"></select>
        <small class="text-muted d-block mt-2">Start typing a title, identifier or reference number to search.</small>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <a href="{{ route('research.dashboard') }}" class="btn atom-btn-white me-2">Cancel</a>
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-calendar-check me-1"></i>Submit Booking
      </button>
    </div>
  </form>
  </div>

  <div class="col-md-4">
    {{-- Your Information --}}
    @if($researcher ?? false)
    <div class="card mb-4">
      <div class="card-header small fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-user-graduate me-1"></i> Your Information
      </div>
      <div class="card-body">
        <h6 class="mb-1">{{ e(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}</h6>
        <small class="text-muted d-block">{{ e($researcher->email ?? '') }}</small>
        <small class="text-muted d-block">{{ e($researcher->institution ?: 'Independent Researcher') }}</small>
        <span class="badge bg-{{ ($researcher->status ?? '') === 'approved' ? 'success' : 'secondary' }} mt-2">{{ ucfirst($researcher->status ?? '') }}</span>
      </div>
    </div>
    @endif

    {{-- Information Notice --}}
    <div class="card mb-4">
      <div class="card-header small fw-bold" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-info-circle me-1"></i> Information
      </div>
      <ul class="list-group list-group-flush small">
        <li class="list-group-item"><i class="fas fa-check-circle text-warning me-2"></i>Bookings require confirmation</li>
        <li class="list-group-item"><i class="fas fa-id-card text-primary me-2"></i>Bring valid ID on visit day</li>
        <li class="list-group-item"><i class="fas fa-clock text-danger me-2"></i>Cancel at least 24h in advance</li>
        <li class="list-group-item"><i class="fas fa-archive text-success me-2"></i>Request materials in advance for faster service</li>
        <li class="list-group-item"><i class="fas fa-phone text-info me-2"></i>Contact the reading room for special requirements</li>
      </ul>
    </div>
  </div>
  </div>

  <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var el = document.getElementById('material-select');
      if (!el || typeof TomSelect === 'undefined') return;
      new TomSelect(el, {
        valueField: 'id',
        labelField: 'name',
        searchField: ['name'],
        maxItems: 20,
        plugins: ['remove_button'],
        load: function(query, callback) {
          if (!query.length || query.length < 2) return callback();
          fetch('{{ url("informationobject/autocomplete") }}?query=' + encodeURIComponent(query) + '&limit=15')
            .then(function(r) { return r.json(); })
            .then(function(data) { callback(data); })
            .catch(function() { callback(); });
        },
        render: {
          option: function(item, escape) {
            return '<div class="d-flex justify-content-between align-items-center">'
              + '<span>' + escape(item.name) + '</span>'
              + (item.slug ? '<small class="text-muted ms-2">' + escape(item.slug) + '</small>' : '')
              + '</div>';
          },
          item: function(item, escape) {
            return '<div><i class="fas fa-archive me-1"></i>' + escape(item.name) + '</div>';
          }
        }
      });
    });
  </script>
@endsection
