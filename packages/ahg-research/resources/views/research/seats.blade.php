@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-chair me-2"></i>Seat Management</h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
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
            <div class="col-md-3">
                @if($currentRoom)
                <span class="text-muted"><i class="fas fa-chair me-1"></i>Capacity: {{ $currentRoom->capacity ?? '-' }}</span>
                @endif
            </div>
        </form>
    </div>
</div>

@if($roomId && $currentRoom)
    @if(count($seats) > 0)
    <div class="table-responsive">
        <table class="table table-hover table-striped">
            <thead class="table-light">
                <tr>
                    <th>Seat Number</th>
                    <th>Room</th>
                    <th>Status</th>
                    <th>Current Researcher</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($seats as $seat)
                <tr>
                    <td><strong>{{ e($seat->seat_number ?? $seat->id) }}</strong></td>
                    <td>{{ e($currentRoom->name) }}</td>
                    <td>
                        @php
                            $seatStatus = $seat->status ?? 'available';
                            $seatColors = ['available' => 'success', 'occupied' => 'danger', 'reserved' => 'warning', 'maintenance' => 'secondary'];
                            $seatColor = $seatColors[$seatStatus] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $seatColor }}">{{ ucfirst($seatStatus) }}</span>
                    </td>
                    <td>
                        @if($seat->researcher_name ?? null)
                            <i class="fas fa-user me-1"></i>{{ e($seat->researcher_name) }}
                        @elseif($seat->researcher_id ?? null)
                            <i class="fas fa-user me-1"></i>Researcher #{{ $seat->researcher_id }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($seatStatus === 'occupied' || $seatStatus === 'reserved')
                        <form method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="form_action" value="release">
                            <input type="hidden" name="seat_id" value="{{ $seat->id }}">
                            <input type="hidden" name="room_id" value="{{ $roomId }}">
                            <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Release</button>
                        </form>
                        @elseif($seatStatus === 'available')
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignSeat{{ $seat->id }}"><i class="fas fa-user-plus me-1"></i>Assign</button>
                        @endif
                        @if($seatStatus !== 'maintenance')
                        <form method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="form_action" value="maintenance">
                            <input type="hidden" name="seat_id" value="{{ $seat->id }}">
                            <input type="hidden" name="room_id" value="{{ $roomId }}">
                            <button type="submit" class="btn btn-outline-secondary btn-sm" title="Set maintenance"><i class="fas fa-tools"></i></button>
                        </form>
                        @else
                        <form method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="form_action" value="activate">
                            <input type="hidden" name="seat_id" value="{{ $seat->id }}">
                            <input type="hidden" name="room_id" value="{{ $roomId }}">
                            <button type="submit" class="btn btn-outline-success btn-sm" title="Activate"><i class="fas fa-check"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>

                {{-- Assign Modal --}}
                <div class="modal fade" id="assignSeat{{ $seat->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <form method="POST">@csrf<input type="hidden" name="form_action" value="assign"><input type="hidden" name="seat_id" value="{{ $seat->id }}"><input type="hidden" name="room_id" value="{{ $roomId }}">
                    <div class="modal-header"><h5 class="modal-title">Assign Seat {{ e($seat->seat_number ?? $seat->id) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Researcher ID <span class="text-danger">*</span></label><input type="number" class="form-control" name="researcher_id" required placeholder="Enter researcher ID"></div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Assign</button></div>
                    </form>
                </div></div></div>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center text-muted py-4">
        <i class="fas fa-chair fa-3x mb-3 d-block"></i>
        No seats configured for this room yet.
    </div>
    @endif
@elseif(!$roomId)
<div class="text-center text-muted py-4">
    <i class="fas fa-hand-pointer fa-3x mb-3 d-block"></i>
    Select a reading room above to manage its seats.
</div>
@endif
@endsection
