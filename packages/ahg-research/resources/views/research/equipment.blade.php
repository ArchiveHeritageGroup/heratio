@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-tools me-2"></i>Equipment</h1>@endsection
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Select Reading Room</label>
                <select name="room_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Select a room --</option>
                    @foreach($rooms as $r)
                    <option value="{{ $r->id }}" {{ $roomId == $r->id ? 'selected' : '' }}>{{ e($r->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                @if($currentRoom)
                <span class="text-muted"><i class="fas fa-door-open me-1"></i>{{ e($currentRoom->name) }}</span>
                @endif
            </div>
        </form>
    </div>
</div>

@if($roomId && $currentRoom)
    @if(count($equipment) > 0)
    <div class="row">
        @foreach($equipment as $item)
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">
                            @php
                                $eqIcons = ['microfilm_reader' => 'fa-film', 'scanner' => 'fa-scanner', 'computer' => 'fa-desktop', 'camera' => 'fa-camera', 'projector' => 'fa-video', 'printer' => 'fa-print', 'magnifier' => 'fa-search-plus'];
                                $eqIcon = $eqIcons[$item->equipment_type ?? ''] ?? 'fa-cog';
                            @endphp
                            <i class="fas {{ $eqIcon }} me-2 text-primary"></i>{{ e($item->name ?? 'Equipment') }}
                        </h5>
                        @php
                            $eqStatus = $item->status ?? 'available';
                            $eqColors = ['available' => 'success', 'in_use' => 'warning', 'booked' => 'info', 'maintenance' => 'secondary', 'out_of_order' => 'danger'];
                            $eqColor = $eqColors[$eqStatus] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $eqColor }}">{{ ucfirst(str_replace('_', ' ', $eqStatus)) }}</span>
                    </div>
                    @if($item->equipment_type ?? null)
                    <small class="text-muted d-block mb-1"><i class="fas fa-tag me-1"></i>{{ ucfirst(str_replace('_', ' ', $item->equipment_type)) }}</small>
                    @endif
                    @if($item->description ?? null)
                    <p class="card-text small text-muted">{{ e(\Illuminate\Support\Str::limit($item->description, 100)) }}</p>
                    @endif
                    @if($eqStatus === 'available')
                    <button class="btn atom-btn-white btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#bookEquipment{{ $item->id }}"><i class="fas fa-calendar-plus me-1"></i>Book</button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Book Equipment Modal --}}
        <div class="modal fade" id="bookEquipment{{ $item->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
            <form method="POST">@csrf<input type="hidden" name="form_action" value="book"><input type="hidden" name="equipment_id" value="{{ $item->id }}"><input type="hidden" name="room_id" value="{{ $roomId }}">
            <div class="modal-header"><h5 class="modal-title">Book: {{ e($item->name ?? 'Equipment') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Booking Date <span class="text-danger">*</span></label><input type="date" class="form-control" name="booking_date" value="{{ date('Y-m-d') }}" required></div>
                <div class="row">
                    <div class="col-md-6"><div class="mb-3"><label class="form-label">From <span class="text-danger">*</span></label><input type="time" class="form-control" name="time_from" value="09:00" required></div></div>
                    <div class="col-md-6"><div class="mb-3"><label class="form-label">To <span class="text-danger">*</span></label><input type="time" class="form-control" name="time_to" value="10:00" required></div></div>
                </div>
                <div class="mb-3"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="2" placeholder="Purpose or special requirements"></textarea></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-calendar-check me-1"></i>Book Equipment</button></div>
            </form>
        </div></div></div>
        @endforeach
    </div>
    @else
    <div class="text-center text-muted py-4">
        <i class="fas fa-tools fa-3x mb-3 d-block"></i>
        No equipment registered for this room yet.
    </div>
    @endif
@elseif(!$roomId)
<div class="text-center text-muted py-4">
    <i class="fas fa-hand-pointer fa-3x mb-3 d-block"></i>
    Select a reading room above to view its equipment.
</div>
@endif
@endsection
