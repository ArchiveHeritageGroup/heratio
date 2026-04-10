@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.index') }}">Research</a></li>
        <li class="breadcrumb-item active">Reading Rooms</li>
    </ol>
</nav>
<h1><i class="fas fa-door-open me-2"></i>Reading Rooms</h1>
@endsection
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">{{ count($rooms) }} reading room(s)</span>
    <a href="{{ route('research.editRoom') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>Add Reading Room
    </a>
</div>

@if(count($rooms) > 0)
<div class="row">
    @foreach($rooms as $room)
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title">{{ e($room->name) }}</h5>
                    @if(!empty($room->is_active))
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </div>
                @if(!empty($room->description))
                    <p class="card-text text-muted small">{{ e(mb_substr($room->description, 0, 120)) }}</p>
                @endif
                <div class="d-flex gap-3 text-muted small mt-2">
                    @if(!empty($room->code))
                        <span><i class="fas fa-tag me-1"></i>{{ e($room->code) }}</span>
                    @endif
                    <span><i class="fas fa-users me-1"></i>Capacity: {{ (int) ($room->capacity ?? 0) }}</span>
                </div>
                @if(!empty($room->location))
                    <div class="text-muted small mt-1">
                        <i class="fas fa-map-marker-alt me-1"></i>{{ e($room->location) }}
                    </div>
                @endif
                @if(!empty($room->opening_time) && !empty($room->closing_time))
                    <div class="text-muted small mt-1">
                        <i class="fas fa-clock me-1"></i>{{ substr($room->opening_time, 0, 5) }} - {{ substr($room->closing_time, 0, 5) }}
                        @if(!empty($room->days_open))
                            ({{ e($room->days_open) }})
                        @endif
                    </div>
                @endif
            </div>
            <div class="card-footer bg-transparent">
                <a href="{{ route('research.editRoom', ['id' => $room->id]) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="alert alert-info">
    <i class="fas fa-info-circle me-1"></i>
    No reading rooms configured. Add one to enable researcher bookings.
</div>
@endif
@endsection
