@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'rooms'])@endsection
@section('title', $isNew ? 'Add Reading Room' : 'Edit Reading Room')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.rooms') }}">Rooms</a></li>
        <li class="breadcrumb-item active">{{ $isNew ? 'Add' : 'Edit' }}</li>
    </ol>
</nav>

<h1><i class="fas fa-door-open text-primary me-2"></i>{{ $isNew ? 'Add Reading Room' : 'Edit Reading Room' }}</h1>

<div class="card">
  <div class="card-body">
    <form method="POST">
      @csrf
      @if(!$isNew)<input type="hidden" name="id" value="{{ $room->id }}">@endif

      <div class="row">
        {{-- Left: Basic Information --}}
        <div class="col-md-6">
          <h5 class="mb-3 border-bottom pb-2">{{ __('Basic Information') }}</h5>

          <div class="mb-3">
            <label class="form-label">{{ __('Room Name *') }}</label>
            <input type="text" name="name" class="form-control" required value="{{ e($room->name ?? '') }}">
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Room Code') }}</label>
              <input type="text" name="code" class="form-control" value="{{ e($room->code ?? '') }}" placeholder="{{ __('e.g. RR-01') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Capacity') }}</label>
              <input type="number" name="capacity" class="form-control" value="{{ $room->capacity ?? 10 }}" min="1">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Location') }}</label>
            <input type="text" name="location" class="form-control" value="{{ e($room->location ?? '') }}" placeholder="{{ __('e.g. Building A, Floor 2') }}">
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Description') }}</label>
            <textarea name="description" class="form-control" rows="3">{{ e($room->description ?? '') }}</textarea>
          </div>

          <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ ($room->is_active ?? 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="isActive">{{ __('Active') }}</label>
          </div>
        </div>

        {{-- Right: Operating Hours + Additional --}}
        <div class="col-md-6">
          <h5 class="mb-3 border-bottom pb-2">{{ __('Operating Hours') }}</h5>

          <div class="row mb-3">
            <div class="col-md-6">
              <label class="form-label">{{ __('Opening Time') }}</label>
              <input type="time" name="opening_time" class="form-control" value="{{ substr($room->opening_time ?? '09:00:00', 0, 5) }}">
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Closing Time') }}</label>
              <input type="time" name="closing_time" class="form-control" value="{{ substr($room->closing_time ?? '17:00:00', 0, 5) }}">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Days Open') }}</label>
            <input type="text" name="days_open" class="form-control" value="{{ e($room->days_open ?? 'Mon,Tue,Wed,Thu,Fri') }}" placeholder="{{ __('Mon,Tue,Wed,Thu,Fri') }}">
            <small class="text-muted">{{ __('Comma-separated list of days') }}</small>
          </div>

          <h5 class="mb-3 mt-4 border-bottom pb-2">{{ __('Additional Information') }}</h5>

          <div class="mb-3">
            <label class="form-label">{{ __('Amenities') }}</label>
            <textarea name="amenities" class="form-control" rows="2" placeholder="{{ __('WiFi, Power outlets, Lockers...') }}">{{ e($room->amenities ?? '') }}</textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">{{ __('Rules') }}</label>
            <textarea name="rules" class="form-control" rows="3" placeholder="{{ __('No food or drinks, Handle materials with care...') }}">{{ e($room->rules ?? '') }}</textarea>
          </div>

          {{-- Booking Policy --}}
          <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>{{ __('Booking Policy') }}</h6></div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">{{ __('Advance Booking (days)') }}</label>
                  <input type="number" name="advance_booking_days" class="form-control" value="{{ $room->advance_booking_days ?? 14 }}" min="1" max="90">
                  <small class="text-muted">{{ __('How far in advance') }}</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ __('Max Hours per Booking') }}</label>
                  <input type="number" name="max_booking_hours" class="form-control" value="{{ $room->max_booking_hours ?? 4 }}" min="1" max="12">
                  <small class="text-muted">{{ __('Maximum duration') }}</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">{{ __('Cancellation Notice (hrs)') }}</label>
                  <input type="number" name="cancellation_hours" class="form-control" value="{{ $room->cancellation_hours ?? 24 }}" min="0" max="72">
                  <small class="text-muted">{{ __('Required notice') }}</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <hr>

      <div class="d-flex justify-content-between">
        <a href="{{ route('research.rooms') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isNew ? 'Create Room' : 'Save Changes' }}</button>
      </div>
    </form>
  </div>
</div>
@endsection
