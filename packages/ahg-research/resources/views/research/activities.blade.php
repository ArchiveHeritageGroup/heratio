@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-stream me-2"></i>Activity Log</h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card mb-3">
    <div class="card-header"><h5 class="mb-0">Filter Activities</h5></div>
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Activity Types</option>
                    <option value="workshop" {{ request('type') === 'workshop' ? 'selected' : '' }}>Workshop</option>
                    <option value="exhibition" {{ request('type') === 'exhibition' ? 'selected' : '' }}>Exhibition</option>
                    <option value="lecture" {{ request('type') === 'lecture' ? 'selected' : '' }}>Lecture</option>
                    <option value="seminar" {{ request('type') === 'seminar' ? 'selected' : '' }}>Seminar</option>
                    <option value="tour" {{ request('type') === 'tour' ? 'selected' : '' }}>Tour</option>
                    <option value="other" {{ request('type') === 'other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_from" value="{{ request('date_from') }}" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_to" value="{{ request('date_to') }}" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Activities</h5>
        <span class="badge bg-primary">{{ count($activities) }} activity/activities</span>
    </div>
    <div class="card-body">
        @if(count($activities) > 0)
        <div class="timeline">
            @foreach($activities as $activity)
            <div class="d-flex mb-3 pb-3 border-bottom">
                <div class="me-3 text-center" style="min-width: 50px;">
                    @php
                        $iconMap = [
                            'workshop' => 'fa-tools',
                            'exhibition' => 'fa-image',
                            'lecture' => 'fa-chalkboard-teacher',
                            'seminar' => 'fa-users',
                            'tour' => 'fa-route',
                        ];
                        $colorMap = [
                            'workshop' => 'text-primary',
                            'exhibition' => 'text-success',
                            'lecture' => 'text-info',
                            'seminar' => 'text-warning',
                            'tour' => 'text-danger',
                        ];
                        $type = $activity->activity_type ?? $activity->type ?? 'other';
                        $icon = $iconMap[$type] ?? 'fa-circle';
                        $color = $colorMap[$type] ?? 'text-secondary';
                    @endphp
                    <i class="fas {{ $icon }} fa-lg {{ $color }}"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <h6 class="mb-1 fw-bold">{{ e($activity->title ?? $activity->name ?? 'Activity') }}</h6>
                        <small class="text-muted">
                            @if($activity->start_date ?? null)
                                {{ \Carbon\Carbon::parse($activity->start_date)->format('j M Y') }}
                                @if($activity->end_date ?? null)
                                    - {{ \Carbon\Carbon::parse($activity->end_date)->format('j M Y') }}
                                @endif
                            @endif
                        </small>
                    </div>
                    @if($activity->description ?? null)
                    <p class="mb-1 text-muted small">{{ e($activity->description) }}</p>
                    @endif
                    <div class="d-flex gap-2">
                        @if($type !== 'other')
                        <span class="badge bg-outline-secondary border">{{ ucfirst($type) }}</span>
                        @endif
                        @if($activity->room_name ?? null)
                        <small class="text-muted"><i class="fas fa-door-open me-1"></i>{{ e($activity->room_name) }}</small>
                        @endif
                        @if($activity->capacity ?? null)
                        <small class="text-muted"><i class="fas fa-users me-1"></i>Capacity: {{ $activity->capacity }}</small>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-stream fa-3x mb-3 d-block"></i>
            No activities found.
        </div>
        @endif
    </div>
</div>
@endsection
