@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'activities'])@endsection
@section('title', 'Activity Log')

@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Activity Log</li></ol></nav>

<h1 class="h2 mb-4"><i class="fas fa-stream text-primary me-2"></i>Activity Log</h1>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach($activityTypes ?? [] as $t)
                        <option value="{{ $t }}" {{ ($typeFilter ?? '') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small">From</label><input type="date" class="form-control form-control-sm" name="date_from" value="{{ $dateFrom ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label small">To</label><input type="date" class="form-control form-control-sm" name="date_to" value="{{ $dateTo ?? '' }}"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
            @if(($typeFilter ?? '') || ($dateFrom ?? '') || ($dateTo ?? ''))
                <div class="col-md-2"><a href="{{ route('research.activities') }}" class="btn btn-outline-secondary btn-sm w-100">Clear</a></div>
            @endif
        </form>
    </div>
</div>

@php
    $typeIcons = [
        'create' => ['fas fa-plus-circle', 'success'],
        'update' => ['fas fa-edit', 'primary'],
        'delete' => ['fas fa-trash', 'danger'],
        'booking' => ['fas fa-calendar-check', 'info'],
        'walk_in' => ['fas fa-walking', 'warning'],
        'clipboard_add' => ['fas fa-clipboard', 'secondary'],
        'policy_evaluated' => ['fas fa-shield-alt', 'dark'],
        'workshop' => ['fas fa-tools', 'primary'],
        'exhibition' => ['fas fa-image', 'success'],
        'lecture' => ['fas fa-chalkboard-teacher', 'info'],
        'tour' => ['fas fa-route', 'danger'],
        'view' => ['fas fa-eye', 'info'],
        'search' => ['fas fa-search', 'secondary'],
        'download' => ['fas fa-download', 'success'],
        'export' => ['fas fa-file-export', 'warning'],
    ];
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Activities</h5>
        <span class="badge bg-primary">{{ count($activities) }}</span>
    </div>
    <div class="card-body p-0">
        @if(count($activities) > 0)
        <div class="list-group list-group-flush">
            @foreach($activities as $a)
            @php
                $a = (object) $a;
                $icon = $typeIcons[$a->type ?? ''][0] ?? 'fas fa-circle';
                $color = $typeIcons[$a->type ?? ''][1] ?? 'secondary';
            @endphp
            <div class="list-group-item d-flex align-items-start gap-3">
                <div class="flex-shrink-0 mt-1">
                    <span class="badge bg-{{ $color }} rounded-circle p-2"><i class="{{ $icon }}"></i></span>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>{{ e($a->title ?? ucfirst(str_replace('_', ' ', $a->type ?? 'Activity'))) }}</strong>
                            @if($a->researcher_name ?? null)
                                <br><small class="text-muted"><i class="fas fa-user me-1"></i>{{ e($a->researcher_name) }}</small>
                            @endif
                        </div>
                        <div class="text-end">
                            <small class="text-muted">{{ $a->created_at ? date('M j, Y H:i', strtotime($a->created_at)) : '' }}</small>
                            <br><span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $a->type ?? '')) }}</span>
                        </div>
                    </div>
                    @if($a->details ?? null)
                        <small class="text-muted d-block mt-1">{{ e(\Illuminate\Support\Str::limit($a->details, 120)) }}</small>
                    @endif
                    @if($a->entity_type ?? null)
                        <small class="text-muted"><i class="fas fa-link me-1"></i>{{ ucfirst(str_replace('_', ' ', $a->entity_type)) }}{{ ($a->entity_id ?? null) ? ' #' . $a->entity_id : '' }}</small>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-stream fa-3x mb-3 opacity-50"></i>
            <h5>No activities found</h5>
            <p>Activities are logged automatically as researchers use the system.</p>
        </div>
        @endif
    </div>
</div>
@endsection
