@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-door-open me-2"></i>{{ $isNew ? 'Add Reading Room' : 'Edit Reading Room' }}</h1>@endsection
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-body">
        <form method="POST">
            @csrf
            @if(!$isNew)<input type="hidden" name="id" value="{{ $room->id }}">@endif

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" value="{{ e($room->name ?? '') }}" required></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Code</label><input type="text" class="form-control" name="code" value="{{ e($room->code ?? '') }}" placeholder="e.g. RR-01"></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3"><label class="form-label">Location</label><input type="text" class="form-control" name="location" value="{{ e($room->location ?? '') }}" placeholder="Building, floor, wing"></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Capacity</label><input type="number" class="form-control" name="capacity" value="{{ $room->capacity ?? 10 }}" min="1"></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Opening Time</label><input type="time" class="form-control" name="opening_time" value="{{ substr($room->opening_time ?? '09:00:00', 0, 5) }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Closing Time</label><input type="time" class="form-control" name="closing_time" value="{{ substr($room->closing_time ?? '17:00:00', 0, 5) }}"></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Days Open</label><input type="text" class="form-control" name="days_open" value="{{ e($room->days_open ?? 'Mon,Tue,Wed,Thu,Fri') }}" placeholder="Mon,Tue,Wed,Thu,Fri"></div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Advance Booking (days)</label><input type="number" class="form-control" name="advance_booking_days" value="{{ $room->advance_booking_days ?? 14 }}" min="1"></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Max Booking Hours</label><input type="number" class="form-control" name="max_booking_hours" value="{{ $room->max_booking_hours ?? 4 }}" min="1"></div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3"><label class="form-label">Cancellation Hours</label><input type="number" class="form-control" name="cancellation_hours" value="{{ $room->cancellation_hours ?? 24 }}" min="0"></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Status</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" {{ ($room->is_active ?? 1) ? 'checked' : '' }}>
                    <label class="form-check-label" for="isActive">Active</label>
                </div>
            </div>

            <div class="mb-3"><label class="form-label">Amenities</label><textarea class="form-control" name="amenities" rows="2" placeholder="Wi-Fi, power outlets, microfilm readers, etc.">{{ e($room->amenities ?? '') }}</textarea></div>

            <div class="mb-3"><label class="form-label">Rules</label><textarea class="form-control" name="rules" rows="3" placeholder="Reading room rules and regulations">{{ e($room->rules ?? '') }}</textarea></div>

            <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2">{{ e($room->description ?? '') }}</textarea></div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('research.rooms') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back to Rooms</a>
                <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i>{{ $isNew ? 'Create Room' : 'Save Changes' }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
