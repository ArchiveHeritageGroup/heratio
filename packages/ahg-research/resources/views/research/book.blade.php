{{-- Book Reading Room - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'book'])

  {{-- Researcher Info Sidebar --}}
  @if($researcher ?? false)
    <div class="card mb-4">
      <div class="card-header small fw-bold" style="background:var(--ahg-primary);color:#fff">Your Information</div>
      <div class="card-body small">
        <dl class="mb-0">
          <dt>Name</dt>
          <dd>{{ e($researcher->first_name ?? '') }} {{ e($researcher->last_name ?? '') }}</dd>
          <dt>Email</dt>
          <dd>{{ e($researcher->email ?? '') }}</dd>
          <dt>Institution</dt>
          <dd>{{ e($researcher->institution ?? '-') }}</dd>
          <dt>Status</dt>
          <dd>
            <span class="badge bg-{{ ($researcher->status ?? '') === 'approved' ? 'success' : 'secondary' }}">
              {{ ucfirst(e($researcher->status ?? '')) }}
            </span>
          </dd>
        </dl>
      </div>
    </div>
  @endif
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
        <p class="text-muted small">List any archival materials you would like to have prepared for your visit.</p>
        <div id="material-requests">
          <div class="input-group mb-2">
            <input type="text" name="materials[]" class="form-control" placeholder="Reference number or description of material">
            <button type="button" class="btn atom-btn-outline-success btn-add-material"><i class="fas fa-plus"></i></button>
          </div>
        </div>
        <small class="text-muted">Click the + button to add more material requests.</small>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      <a href="{{ route('research.dashboard') }}" class="btn atom-btn-white me-2">Cancel</a>
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-calendar-check me-1"></i>Submit Booking
      </button>
    </div>
  </form>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-add-material')) {
          var container = document.getElementById('material-requests');
          var div = document.createElement('div');
          div.className = 'input-group mb-2';
          div.innerHTML = '<input type="text" name="materials[]" class="form-control" placeholder="Reference number or description of material">' +
            '<button type="button" class="btn atom-btn-outline-danger btn-remove-material"><i class="fas fa-minus"></i></button>';
          container.appendChild(div);
        }
        if (e.target.closest('.btn-remove-material')) {
          e.target.closest('.input-group').remove();
        }
      });
    });
  </script>
@endsection
