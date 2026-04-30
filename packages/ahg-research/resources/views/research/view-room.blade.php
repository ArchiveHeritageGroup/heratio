{{-- View Room - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'rooms'])@endsection
@section('title', 'Room Details')
@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item"><a href="{{ route('research.rooms') }}">Rooms</a></li><li class="breadcrumb-item active">{{ e($room->name ?? '') }}</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-door-open text-primary me-2"></i>{{ e($room->name ?? 'Room') }}</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Room Information</div><div class="card-body">
    <dl class="row mb-0">
        <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ e($room->name ?? '') }}</dd>
        <dt class="col-sm-3">Code</dt><dd class="col-sm-9"><code>{{ e($room->code ?? '') }}</code></dd>
        <dt class="col-sm-3">Capacity</dt><dd class="col-sm-9">{{ $room->capacity ?? '-' }}</dd>
        <dt class="col-sm-3">Location</dt><dd class="col-sm-9">{{ e($room->location ?? '-') }}</dd>
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-{{ ($room->is_active ?? false) ? 'success' : 'danger' }}">{{ ($room->is_active ?? false) ? 'Active' : 'Inactive' }}</span></dd>
    </dl>
    @if($room->description ?? false)<hr><p class="mb-0">{{ e($room->description) }}</p>@endif
</div></div>
@if(!empty($seats))
<div class="card mb-4"><div class="card-header">Seats ({{ count($seats) }})</div><div class="card-body p-0">
    <table class="table table-sm mb-0"><thead class="table-light"><tr><th>{{ __('Seat') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th></tr></thead><tbody>
        @foreach($seats as $s)<tr><td>{{ e($s->label ?? 'Seat #' . $s->id) }}</td><td>{{ e($s->equipment_type ?? 'standard') }}</td><td><span class="badge bg-{{ ($s->is_occupied ?? false) ? 'danger' : 'success' }}">{{ ($s->is_occupied ?? false) ? 'Occupied' : 'Available' }}</span></td></tr>@endforeach
    </tbody></table>
</div></div>
@endif
</div><div class="col-md-4">
<div class="card mb-4"><div class="card-header"><h6 class="mb-0">{{ __('Opening Hours') }}</h6></div><div class="card-body small">
    @if(!empty($room->opening_hours)){{ e($room->opening_hours) }}@else <span class="text-muted">{{ __('Not specified') }}</span>@endif
</div></div>
<div class="d-flex flex-column gap-2">
    <a href="{{ route('research.editRoom', ['id' => $room->id ?? 0]) }}" class="btn btn-outline-primary"><i class="fas fa-edit me-1"></i>{{ __('Edit Room') }}</a>
    <a href="{{ route('research.rooms') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Rooms') }}</a>
</div>
</div></div>
@endsection