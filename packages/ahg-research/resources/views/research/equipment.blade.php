@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'equipment'])@endsection
@section('title', 'Reading Room Equipment')

@section('content')
@php
    $equipmentTypes = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'equipment_type')->where('is_active', 1)->orderBy('sort_order')->pluck('label', 'code')->toArray();
    $equipmentConditions = \Illuminate\Support\Facades\DB::table('ahg_dropdown')
        ->where('taxonomy', 'equipment_condition')->where('is_active', 1)->orderBy('sort_order')->pluck('label', 'code')->toArray();
@endphp

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{!! session('success') !!}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<h1 class="mb-4"><i class="fas fa-tools me-2"></i>Reading Room Equipment</h1>

{{-- Room Selector + Type Counts --}}
<div class="row mb-4">
    <div class="col-md-4">
        <label class="form-label">Select Reading Room</label>
        <select class="form-select" onchange="window.location.href='?room_id=' + this.value">
            <option value="">-- Select Room --</option>
            @foreach($rooms as $r)
                <option value="{{ $r->id }}" {{ ($roomId ?? '') == $r->id ? 'selected' : '' }}>{{ e($r->name) }}</option>
            @endforeach
        </select>
    </div>
    @if(($currentRoom ?? false) && !empty($equipment))
    <div class="col-md-8">
        <label class="form-label">Equipment by Type</label>
        <div>
            @php $typeCounts = collect($equipment)->groupBy('equipment_type')->map->count(); @endphp
            @foreach($typeCounts as $type => $count)
                <span class="badge bg-secondary me-1">{{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $count }}</span>
            @endforeach
        </div>
    </div>
    @endif
</div>

@if($currentRoom ?? false)
<div class="row">
    {{-- Equipment Table --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Equipment in {{ e($currentRoom->name) }}</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEquipmentModal"><i class="fas fa-plus me-1"></i>Add Equipment</button>
            </div>
            <div class="card-body p-0">
                @if(!empty($equipment))
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Type</th><th>Brand/Model</th><th>Location</th><th>Condition</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        @foreach($equipment as $item)
                            <tr class="{{ ($item->is_available ?? 1) ? '' : 'table-warning' }}">
                                <td>
                                    <strong>{{ e($item->name) }}</strong>
                                    @if($item->code)<br><small class="text-muted">{{ e($item->code) }}</small>@endif
                                </td>
                                <td><span class="badge bg-secondary">{{ $equipmentTypes[$item->equipment_type] ?? ucfirst(str_replace('_', ' ', $item->equipment_type)) }}</span></td>
                                <td>{{ e(trim(($item->brand ?? '') . ' ' . ($item->model ?? ''))) ?: '-' }}</td>
                                <td>{{ e($item->location ?? '-') }}</td>
                                <td>
                                    @php $cc = ['excellent'=>'success','good'=>'primary','fair'=>'warning','needs_repair'=>'danger','out_of_service'=>'dark']; @endphp
                                    <span class="badge bg-{{ $cc[$item->condition_status ?? ''] ?? 'secondary' }}">{{ $equipmentConditions[$item->condition_status ?? ''] ?? ucfirst(str_replace('_', ' ', $item->condition_status ?? 'unknown')) }}</span>
                                </td>
                                <td>{!! ($item->is_available ?? 1) ? '<span class="badge bg-success">Available</span>' : '<span class="badge bg-danger">Unavailable</span>' !!}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='editEquipment(@json($item))'><i class="fas fa-edit"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="logMaintenance({{ $item->id }})"><i class="fas fa-wrench"></i></button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="card-body text-center text-muted py-4"><i class="fas fa-info-circle fa-2x mb-2"></i><p>No equipment configured for this room.</p></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Equipment Types Sidebar --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-list me-2"></i>Equipment Types</h6></div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small">
                    @foreach($equipmentTypes as $code => $label)
                        <li class="mb-1"><span class="badge bg-secondary">{{ $code }}</span> {{ $label }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('dropdown.index') }}?taxonomy=equipment_type" class="btn btn-sm btn-outline-secondary mt-2 w-100"><i class="fas fa-cog me-1"></i>Manage in Dropdown Manager</a>
            </div>
        </div>
    </div>
</div>
@elseif(!($roomId ?? false))
<div class="text-center text-muted py-5"><i class="fas fa-hand-pointer fa-3x mb-3"></i><p>Select a reading room above to manage its equipment.</p></div>
@endif

{{-- Add/Edit Equipment Modal --}}
<div class="modal fade" id="addEquipmentModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="equipmentForm">@csrf
        <input type="hidden" name="form_action" id="equipmentAction" value="create">
        <input type="hidden" name="equipment_id" id="equipmentId">
        <input type="hidden" name="room_id" value="{{ $roomId ?? '' }}">
        <div class="modal-header"><h5 class="modal-title" id="equipmentModalTitle">Add Equipment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" id="eqName" class="form-control" required></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">Code/ID</label><input type="text" name="code" id="eqCode" class="form-control" placeholder="e.g. MF-001"></div></div>
            </div>
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">Type *</label><select name="equipment_type" id="eqType" class="form-select" required>@foreach($equipmentTypes as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">Location</label><input type="text" name="location" id="eqLocation" class="form-control" placeholder="e.g. Table A"></div></div>
            </div>
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">Brand</label><input type="text" name="brand" id="eqBrand" class="form-control"></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">Model</label><input type="text" name="model" id="eqModel" class="form-control"></div></div>
            </div>
            <div class="row">
                <div class="col-6"><div class="mb-3"><label class="form-label">Serial Number</label><input type="text" name="serial_number" id="eqSerial" class="form-control"></div></div>
                <div class="col-6"><div class="mb-3"><label class="form-label">Max Booking Hours</label><input type="number" name="max_booking_hours" id="eqMaxHours" class="form-control" value="4" min="1" max="8"></div></div>
            </div>
            <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="eqDescription" class="form-control" rows="2"></textarea></div>
            <div class="form-check mb-3"><input type="checkbox" name="requires_training" id="eqTraining" class="form-check-input" value="1"><label class="form-check-label" for="eqTraining">Requires training to use</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div></div>

{{-- Maintenance Modal --}}
<div class="modal fade" id="maintenanceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf
        <input type="hidden" name="form_action" value="maintenance">
        <input type="hidden" name="equipment_id" id="maintenanceEquipmentId">
        <input type="hidden" name="room_id" value="{{ $roomId ?? '' }}">
        <div class="modal-header"><h5 class="modal-title">Log Maintenance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label">Description *</label><textarea name="maintenance_description" class="form-control" rows="3" required placeholder="Describe the maintenance performed..."></textarea></div>
            <div class="mb-3"><label class="form-label">New Condition</label>
                <select name="new_condition" class="form-select">
                    @foreach($equipmentConditions as $code => $label)
                        <option value="{{ $code }}" {{ $code === 'good' ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3"><label class="form-label">Next Maintenance Date</label><input type="date" name="next_maintenance_date" class="form-control"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Log Maintenance</button></div>
    </form>
</div></div></div>

@push('js')
<script>
function editEquipment(item) {
    document.getElementById('equipmentModalTitle').textContent = 'Edit Equipment';
    document.getElementById('equipmentAction').value = 'update';
    document.getElementById('equipmentId').value = item.id;
    document.getElementById('eqName').value = item.name;
    document.getElementById('eqCode').value = item.code || '';
    document.getElementById('eqType').value = item.equipment_type;
    document.getElementById('eqLocation').value = item.location || '';
    document.getElementById('eqBrand').value = item.brand || '';
    document.getElementById('eqModel').value = item.model || '';
    document.getElementById('eqSerial').value = item.serial_number || '';
    document.getElementById('eqMaxHours').value = item.max_booking_hours || 4;
    document.getElementById('eqDescription').value = item.description || '';
    document.getElementById('eqTraining').checked = item.requires_training == 1;
    new bootstrap.Modal(document.getElementById('addEquipmentModal')).show();
}

function logMaintenance(equipmentId) {
    document.getElementById('maintenanceEquipmentId').value = equipmentId;
    new bootstrap.Modal(document.getElementById('maintenanceModal')).show();
}

document.getElementById('addEquipmentModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('equipmentModalTitle').textContent = 'Add Equipment';
    document.getElementById('equipmentAction').value = 'create';
    document.getElementById('equipmentForm').reset();
});
</script>
@endpush
@endsection
