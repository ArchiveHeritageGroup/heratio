@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'rooms'])@endsection
@section('title', 'Reading Room Seats')

@section('content')
@php
    $seatTypes = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'seat_type')->where('is_active', 1)
        ->orderBy('sort_order')->get();
@endphp
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{!! session('success') !!}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1 class="mb-4"><i class="fas fa-chair me-2"></i>Reading Room Seats</h1>

{{-- Room Selector + Occupancy --}}
<div class="row mb-4">
    <div class="col-md-4">
        <label class="form-label">{{ __('Select Reading Room') }}</label>
        <select class="form-select" onchange="window.location.href='?room_id=' + this.value">
            <option value="">-- Select Room --</option>
            @foreach($rooms as $r)
                <option value="{{ $r->id }}" {{ ($roomId ?? '') == $r->id ? 'selected' : '' }}>{{ e($r->name) }} (Capacity: {{ $r->capacity ?? '?' }})</option>
            @endforeach
        </select>
    </div>
    @if($currentRoom ?? false)
    <div class="col-md-8">
        @php
            $totalSeats = count($seats ?? []);
            $activeSeats = collect($seats)->where('is_active', 1)->count();
            $occupiedSeats = collect($seats)->whereIn('status', ['occupied', 'reserved'])->count();
            $availableSeats = $activeSeats - $occupiedSeats;
            $occupancyPct = $activeSeats > 0 ? round(($occupiedSeats / $activeSeats) * 100) : 0;
        @endphp
        <div class="row">
            <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body text-center py-2"><h4 class="mb-0">{{ $totalSeats }}</h4><small>Total Seats</small></div></div></div>
            <div class="col-md-3"><div class="card bg-success text-white"><div class="card-body text-center py-2"><h4 class="mb-0">{{ $availableSeats }}</h4><small>Available</small></div></div></div>
            <div class="col-md-3"><div class="card bg-warning text-dark"><div class="card-body text-center py-2"><h4 class="mb-0">{{ $occupiedSeats }}</h4><small>Occupied</small></div></div></div>
            <div class="col-md-3"><div class="card bg-info text-white"><div class="card-body text-center py-2"><h4 class="mb-0">{{ $occupancyPct }}%</h4><small>Occupancy</small></div></div></div>
        </div>
    </div>
    @endif
</div>

@if($currentRoom ?? false)
<div class="row">
    {{-- Seat Table --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Seats in {{ e($currentRoom->name) }}</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSeatModal"><i class="fas fa-plus me-1"></i>Add Seat</button>
            </div>
            <div class="card-body p-0">
                @if(count($seats) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>{{ __('Seat #') }}</th><th>{{ __('Label') }}</th><th>{{ __('Type') }}</th><th>{{ __('Zone') }}</th><th>{{ __('Amenities') }}</th><th>{{ __('Status') }}</th><th>{{ __('Researcher') }}</th><th>{{ __('Actions') }}</th></tr>
                        </thead>
                        <tbody>
                        @foreach($seats as $seat)
                            <tr class="{{ ($seat->is_active ?? 1) ? '' : 'table-secondary' }}">
                                <td><strong>{{ e($seat->seat_number ?? $seat->id) }}</strong></td>
                                <td>{{ e($seat->seat_label ?? '-') }}</td>
                                <td><span class="badge bg-secondary">{{ ucfirst($seat->seat_type ?? 'standard') }}</span></td>
                                <td>{{ e($seat->zone ?? '-') }}</td>
                                <td>
                                    @if($seat->has_power ?? false)<i class="fas fa-plug text-success me-1" title="{{ __('Power') }}"></i>@endif
                                    @if($seat->has_lamp ?? false)<i class="fas fa-lightbulb text-warning me-1" title="{{ __('Lamp') }}"></i>@endif
                                    @if($seat->has_computer ?? false)<i class="fas fa-desktop text-primary me-1" title="{{ __('Computer') }}"></i>@endif
                                    @if($seat->has_magnifier ?? false)<i class="fas fa-search-plus text-info" title="{{ __('Magnifier') }}"></i>@endif
                                </td>
                                <td>
                                    @php $seatStatus = $seat->status ?? (($seat->is_active ?? 1) ? 'available' : 'inactive'); @endphp
                                    @php $sc = ['available'=>'success','occupied'=>'danger','reserved'=>'warning','maintenance'=>'secondary','inactive'=>'dark']; @endphp
                                    <span class="badge bg-{{ $sc[$seatStatus] ?? 'secondary' }}">{{ ucfirst($seatStatus) }}</span>
                                </td>
                                <td>{{ e($seat->researcher_name ?? '-') }}</td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick='editSeat(@json($seat))'><i class="fas fa-edit"></i></button>
                                        @if($seatStatus === 'available')
                                            <button class="btn btn-sm btn-outline-success assign-btn" data-id="{{ $seat->id }}" data-number="{{ e($seat->seat_number ?? $seat->id) }}"><i class="fas fa-user-plus"></i></button>
                                        @endif
                                        @if(in_array($seatStatus, ['occupied', 'reserved']))
                                            <form method="POST" class="d-inline">@csrf<input type="hidden" name="form_action" value="release"><input type="hidden" name="seat_id" value="{{ $seat->id }}"><input type="hidden" name="room_id" value="{{ $roomId }}"><button class="btn btn-sm btn-outline-warning" title="{{ __('Release') }}"><i class="fas fa-sign-out-alt"></i></button></form>
                                        @endif
                                        @if(($seat->is_active ?? 1))
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Deactivate this seat?')">@csrf<input type="hidden" name="form_action" value="delete"><input type="hidden" name="seat_id" value="{{ $seat->id }}"><input type="hidden" name="room_id" value="{{ $roomId }}"><button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button></form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="card-body text-center text-muted py-4">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <p>No seats configured. Add seats or use Bulk Create.</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sidebar: Bulk Create + Seat Types --}}
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-magic me-2"></i>Bulk Create Seats</h6></div>
            <div class="card-body">
                <form method="POST">
                    @csrf
                    <input type="hidden" name="form_action" value="bulk_create">
                    <input type="hidden" name="room_id" value="{{ $roomId }}">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Pattern *') }}</label>
                        <input type="text" name="pattern" class="form-control" placeholder="{{ __('e.g. A1-A10 or 1-20') }}" required>
                        <small class="text-muted">Examples: A1-A10, 1-20, B1-B5</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Seat Type') }}</label>
                        <select name="seat_type" class="form-select">
                            @foreach($seatTypes as $st)
                                <option value="{{ $st->code }}">{{ $st->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Zone') }}</label>
                        <input type="text" name="zone" class="form-control" placeholder="{{ __('e.g. Main Hall') }}">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus-circle me-1"></i>Create Seats</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Seat Types</h6></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    @foreach($seatTypes as $st)
                        <li class="mb-1"><span class="badge bg-secondary">{{ $st->code }}</span> {{ $st->label }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('dropdown.index') }}?taxonomy=seat_type" class="btn btn-sm btn-outline-secondary mt-2 w-100"><i class="fas fa-cog me-1"></i>Manage in Dropdown Manager</a>
            </div>
        </div>
    </div>
</div>
@elseif(!($roomId ?? false))
<div class="text-center text-muted py-5">
    <i class="fas fa-hand-pointer fa-3x mb-3"></i>
    <p>Select a reading room above to manage its seats.</p>
</div>
@endif

{{-- Add/Edit Seat Modal --}}
<div class="modal fade" id="addSeatModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="seatForm">@csrf
        <input type="hidden" name="form_action" id="seatAction" value="create">
        <input type="hidden" name="seat_id" id="seatId">
        <input type="hidden" name="room_id" value="{{ $roomId ?? '' }}">
        <div class="modal-header"><h5 class="modal-title" id="seatModalTitle">{{ __('Add Seat') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">{{ __('Seat Number *') }}</label><input type="text" name="seat_number" id="seatNumber" class="form-control" required></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">{{ __('Label') }}</label><input type="text" name="seat_label" id="seatLabel" class="form-control"></div></div>
            </div>
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">{{ __('Type') }}</label><select name="seat_type" id="seatType" class="form-select">@foreach($seatTypes as $st)<option value="{{ $st->code }}">{{ $st->label }}</option>@endforeach</select></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">{{ __('Zone') }}</label><input type="text" name="zone" id="seatZone" class="form-control"></div></div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Amenities') }}</label>
                <div class="row">
                    <div class="col-6">
                        <div class="form-check"><input type="checkbox" name="has_power" id="hasPower" class="form-check-input" value="1" checked><label class="form-check-label" for="hasPower">{{ __('Power outlet') }}</label></div>
                        <div class="form-check"><input type="checkbox" name="has_lamp" id="hasLamp" class="form-check-input" value="1" checked><label class="form-check-label" for="hasLamp">{{ __('Reading lamp') }}</label></div>
                    </div>
                    <div class="col-6">
                        <div class="form-check"><input type="checkbox" name="has_computer" id="hasComputer" class="form-check-input" value="1"><label class="form-check-label" for="hasComputer">{{ __('Computer') }}</label></div>
                        <div class="form-check"><input type="checkbox" name="has_magnifier" id="hasMagnifier" class="form-check-input" value="1"><label class="form-check-label" for="hasMagnifier">{{ __('Magnifier') }}</label></div>
                    </div>
                </div>
            </div>
            <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" id="seatNotes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary">{{ __('Save') }}</button></div>
    </form>
</div></div></div>

{{-- Assign Seat Modal --}}
<div class="modal fade" id="assignSeatModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="assign"><input type="hidden" name="seat_id" id="assignSeatId"><input type="hidden" name="room_id" value="{{ $roomId ?? '' }}">
    <div class="modal-header"><h5 class="modal-title">Assign Seat <span id="assignSeatNumber"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">{{ __('Researcher *') }}</label><select id="assignResearcherSearch" name="researcher_id" required></select></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-success"><i class="fas fa-user-plus me-1"></i>Assign</button></div>
    </form>
</div></div></div>

@push('css')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush
@push('js')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
function editSeat(seat) {
    document.getElementById('seatModalTitle').textContent = 'Edit Seat';
    document.getElementById('seatAction').value = 'update';
    document.getElementById('seatId').value = seat.id;
    document.getElementById('seatNumber').value = seat.seat_number || '';
    document.getElementById('seatLabel').value = seat.seat_label || '';
    document.getElementById('seatType').value = seat.seat_type || 'standard';
    document.getElementById('seatZone').value = seat.zone || '';
    document.getElementById('hasPower').checked = seat.has_power == 1;
    document.getElementById('hasLamp').checked = seat.has_lamp == 1;
    document.getElementById('hasComputer').checked = seat.has_computer == 1;
    document.getElementById('hasMagnifier').checked = seat.has_magnifier == 1;
    document.getElementById('seatNotes').value = seat.notes || '';
    new bootstrap.Modal(document.getElementById('addSeatModal')).show();
}

document.getElementById('addSeatModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('seatModalTitle').textContent = 'Add Seat';
    document.getElementById('seatAction').value = 'create';
    document.getElementById('seatForm').reset();
});

// Assign buttons
document.querySelectorAll('.assign-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('assignSeatId').value = this.dataset.id;
        document.getElementById('assignSeatNumber').textContent = this.dataset.number;
        new bootstrap.Modal(document.getElementById('assignSeatModal')).show();
    });
});

// TomSelect for researcher search in assign modal
var assignEl = document.getElementById('assignResearcherSearch');
if (assignEl) {
    new TomSelect(assignEl, {
        valueField: 'id', labelField: 'name', searchField: ['name', 'email'],
        loadThrottle: 300,
        load: function(q, cb) { if (q.length<2) return cb(); fetch('/research/researcher-autocomplete?query='+encodeURIComponent(q)).then(function(r){return r.json()}).then(cb).catch(function(){cb()}); },
        render: {
            option: function(i) { return '<div><strong>'+i.name+'</strong><br><small class="text-muted">'+(i.email||'')+'</small></div>'; },
            item: function(i) { return '<div>'+i.name+'</div>'; }
        }
    });
}
</script>
@endpush
@endsection
