@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-door-open me-2"></i>Reading Rooms</h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">{{ count($rooms) }} reading room(s)</span>
    <a href="{{ route('research.editRoom') }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>Add Room</a>
</div>

@if(count($rooms) > 0)
<div class="table-responsive">
    <table class="table table-hover table-striped">
        <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Location</th>
                <th>Capacity</th>
                <th>Status</th>
                <th>Operating Hours</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rooms as $room)
            <tr>
                <td>
                    <strong>{{ e($room->name) }}</strong>
                    @if($room->code ?? null)<br><small class="text-muted">Code: {{ e($room->code) }}</small>@endif
                </td>
                <td>{{ e($room->location ?? '-') }}</td>
                <td><i class="fas fa-chair me-1"></i>{{ $room->capacity ?? '-' }}</td>
                <td>
                    @php
                        $isActive = ($room->is_active ?? 1);
                    @endphp
                    @if($isActive)
                        <span class="badge bg-success">Active</span>
                    @else
                        <span class="badge bg-secondary">Inactive</span>
                    @endif
                </td>
                <td>
                    @if(($room->opening_time ?? null) && ($room->closing_time ?? null))
                        <i class="fas fa-clock me-1"></i>{{ substr($room->opening_time, 0, 5) }} - {{ substr($room->closing_time, 0, 5) }}
                        @if($room->days_open ?? null)<br><small class="text-muted">{{ e($room->days_open) }}</small>@endif
                    @else
                        -
                    @endif
                </td>
                <td class="text-end">
                    <a href="{{ route('research.editRoom', ['id' => $room->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@else
<div class="text-center text-muted py-4">
    <i class="fas fa-door-open fa-3x mb-3 d-block"></i>
    No reading rooms configured yet. Add a room to get started.
</div>
@endif
@endsection
