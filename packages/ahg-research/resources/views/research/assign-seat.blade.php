{{-- Assign Seat - Migrated from AtoM --}}
@extends('theme::layouts.2col')

@section('sidebar')
  @include('research::research._sidebar', ['sidebarActive' => 'seats'])
@endsection

@section('title', 'Assign Seat')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.seats') }}">Seats</a></li>
        <li class="breadcrumb-item active">Assign Seat</li>
    </ol>
</nav>

<h1 class="h2 mb-4"><i class="fas fa-chair text-primary me-2"></i>Assign Seat</h1>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-user me-2"></i>Researcher</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ e(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}</dd>
                    <dt class="col-sm-4">Booking</dt><dd class="col-sm-8">{{ e($booking->booking_date ?? '') }} {{ e($booking->start_time ?? '') }}-{{ e($booking->end_time ?? '') }}</dd>
                    <dt class="col-sm-4">Room</dt><dd class="col-sm-8">{{ e($booking->room_name ?? '') }}</dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-chair me-2"></i>Available Seats</div>
            <div class="card-body">
                <form method="POST">
                    @csrf
                    <input type="hidden" name="booking_id" value="{{ $booking->id ?? 0 }}">
                    <div class="mb-3">
                        <label class="form-label">Select Seat <span class="badge bg-danger ms-1">Required</span></label>
                        <select name="seat_id" class="form-select" required>
                            <option value="">-- Choose a seat --</option>
                            @foreach($availableSeats ?? [] as $seat)
                                <option value="{{ $seat->id }}">{{ e($seat->label ?? 'Seat #' . $seat->id) }} ({{ e($seat->equipment_type ?? 'standard') }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Assign Seat</button>
                    <a href="{{ route('research.seats') }}" class="btn atom-btn-white">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection