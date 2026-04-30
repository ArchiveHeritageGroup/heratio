@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'adminTypes'])@endsection
@section('title', 'Researcher Types')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Researcher Types</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-user-tag text-primary me-2"></i>{{ __('Researcher Types') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTypeModal"><i class="fas fa-plus me-1"></i>{{ __('Add Type') }}</button>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Max Advance Days') }}</th>
                    <th>{{ __('Max Hours/Day') }}</th>
                    <th>{{ __('Max Materials') }}</th>
                    <th>{{ __('Auto Approve') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th width="100">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($types as $type)
                <tr>
                    <td>
                        <strong>{{ e($type->name) }}</strong>
                        @if($type->description)<br><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($type->description, 50)) }}</small>@endif
                    </td>
                    <td><code>{{ $type->code }}</code></td>
                    <td>{{ $type->max_booking_days_advance ?? 14 }} days</td>
                    <td>{{ $type->max_booking_hours_per_day ?? 4 }} hrs</td>
                    <td>{{ $type->max_materials_per_booking ?? 10 }}</td>
                    <td>{!! ($type->auto_approve ?? 0) ? '<span class="badge bg-success">{{ __('Yes') }}</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
                    <td>{!! ($type->is_active ?? 1) ? '<span class="badge bg-success">{{ __('Active') }}</span>' : '<span class="badge bg-danger">{{ __('Inactive') }}</span>' !!}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-type-btn"
                            data-type='{!! json_encode($type, JSON_HEX_APOS | JSON_HEX_QUOT) !!}'
                            title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this type?')">
                            @csrf
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="type_id" value="{{ $type->id }}">
                            <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No researcher types configured</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add/Edit Type Modal --}}
<div class="modal fade" id="addTypeModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="typeForm">@csrf
        <input type="hidden" name="form_action" id="typeAction" value="create">
        <input type="hidden" name="type_id" id="typeId">
        <div class="modal-header"><h5 class="modal-title" id="typeModalTitle">{{ __('Add Researcher Type') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-8"><div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" id="typeName" class="form-control" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Code *') }}</label><input type="text" name="code" id="typeCode" class="form-control" required placeholder="{{ __('e.g. academic') }}"></div></div>
            </div>
            <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" id="typeDesc" class="form-control" rows="2"></textarea></div>

            <h6 class="border-bottom pb-2 mt-3">{{ __('Booking Limits') }}</h6>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Max Advance Days') }}</label><input type="number" name="max_booking_days_advance" id="typeAdvDays" class="form-control" value="14" min="1"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Max Hours/Day') }}</label><input type="number" name="max_booking_hours_per_day" id="typeMaxHrs" class="form-control" value="4" min="1"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Max Materials/Booking') }}</label><input type="number" name="max_materials_per_booking" id="typeMaxMat" class="form-control" value="10" min="1"></div></div>
            </div>

            <h6 class="border-bottom pb-2 mt-3">{{ __('Permissions') }}</h6>
            <div class="row">
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="can_remote_access" id="typeRemote" class="form-check-input" value="1"><label class="form-check-label" for="typeRemote">{{ __('Remote Access') }}</label></div></div>
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="can_request_reproductions" id="typeRepro" class="form-check-input" value="1" checked><label class="form-check-label" for="typeRepro">{{ __('Request Reproductions') }}</label></div></div>
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="can_export_data" id="typeExport" class="form-check-input" value="1" checked><label class="form-check-label" for="typeExport">{{ __('Export Data') }}</label></div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="requires_id_verification" id="typeIdVerify" class="form-check-input" value="1" checked><label class="form-check-label" for="typeIdVerify">{{ __('Requires ID Verification') }}</label></div></div>
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="auto_approve" id="typeAutoApprove" class="form-check-input" value="1"><label class="form-check-label" for="typeAutoApprove">{{ __('Auto Approve') }}</label></div></div>
                <div class="col-md-4"><div class="form-check mb-2"><input type="checkbox" name="is_active" id="typeActive" class="form-check-input" value="1" checked><label class="form-check-label" for="typeActive">{{ __('Active') }}</label></div></div>
            </div>

            <h6 class="border-bottom pb-2 mt-3">{{ __('Other') }}</h6>
            <div class="row">
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Expiry (months)') }}</label><input type="number" name="expiry_months" id="typeExpiry" class="form-control" value="12" min="1"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Priority Level') }}</label><input type="number" name="priority_level" id="typePriority" class="form-control" value="5" min="1" max="10"></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Sort Order') }}</label><input type="number" name="sort_order" id="typeSortOrder" class="form-control" value="100"></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary" id="typeSubmitBtn"><i class="fas fa-plus me-1"></i>{{ __('Add Type') }}</button></div>
    </form>
</div></div></div>

@push('js')
<script>
document.querySelectorAll('.edit-type-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var t = JSON.parse(this.getAttribute('data-type'));
        document.getElementById('typeAction').value = 'update';
        document.getElementById('typeId').value = t.id;
        document.getElementById('typeName').value = t.name;
        document.getElementById('typeCode').value = t.code;
        document.getElementById('typeDesc').value = t.description || '';
        document.getElementById('typeAdvDays').value = t.max_booking_days_advance || 14;
        document.getElementById('typeMaxHrs').value = t.max_booking_hours_per_day || 4;
        document.getElementById('typeMaxMat').value = t.max_materials_per_booking || 10;
        document.getElementById('typeRemote').checked = t.can_remote_access == 1;
        document.getElementById('typeRepro').checked = t.can_request_reproductions == 1;
        document.getElementById('typeExport').checked = t.can_export_data == 1;
        document.getElementById('typeIdVerify').checked = t.requires_id_verification == 1;
        document.getElementById('typeAutoApprove').checked = t.auto_approve == 1;
        document.getElementById('typeActive').checked = t.is_active == 1;
        document.getElementById('typeExpiry').value = t.expiry_months || 12;
        document.getElementById('typePriority').value = t.priority_level || 5;
        document.getElementById('typeSortOrder').value = t.sort_order || 100;
        document.getElementById('typeModalTitle').textContent = 'Edit Type: ' + t.name;
        document.getElementById('typeSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Save Changes';
        new bootstrap.Modal(document.getElementById('addTypeModal')).show();
    });
});

document.getElementById('addTypeModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('typeAction').value = 'create';
    document.getElementById('typeForm').reset();
    document.getElementById('typeModalTitle').textContent = 'Add Researcher Type';
    document.getElementById('typeSubmitBtn').innerHTML = '<i class="fas fa-plus me-1"></i>Add Type';
});
</script>
@endpush
@endsection
