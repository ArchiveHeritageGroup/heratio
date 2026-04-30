@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'institutions'])@endsection
@section('title', 'Partner Institutions')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Institutions</li></ol></nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-university text-primary me-2"></i>{{ __('Partner Institutions') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#institutionModal"><i class="fas fa-plus me-1"></i>{{ __('Add Institution') }}</button>
</div>

@if(!empty($institutions))
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr><th>{{ __('Name') }}</th><th>{{ __('Code') }}</th><th>URL</th><th>{{ __('Contact') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr>
        </thead>
        <tbody>
        @foreach($institutions as $inst)
            <tr>
                <td>
                    <strong>{{ e($inst->name) }}</strong>
                    @if($inst->description)<br><small class="text-muted">{{ e(\Illuminate\Support\Str::limit($inst->description, 60)) }}</small>@endif
                </td>
                <td><code>{{ e($inst->code ?? '') }}</code></td>
                <td>
                    @if($inst->url ?? null)
                        <a href="{{ $inst->url }}" target="_blank" class="text-decoration-none">{{ preg_replace('#^https?://#', '', $inst->url) }} <i class="fas fa-external-link-alt ms-1 small"></i></a>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td>
                    @if($inst->contact_name ?? null)
                        {{ e($inst->contact_name) }}
                        @if($inst->contact_email)<br><small><a href="mailto:{{ $inst->contact_email }}">{{ e($inst->contact_email) }}</a></small>@endif
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
                <td>{!! ($inst->is_active ?? 1) ? '<span class="badge bg-success">{{ __('Active') }}</span>' : '<span class="badge bg-secondary">{{ __('Inactive') }}</span>' !!}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary edit-inst-btn" data-inst='{!! json_encode($inst, JSON_HEX_APOS | JSON_HEX_QUOT) !!}' title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this institution?')">
                        @csrf
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="institution_id" value="{{ $inst->id }}">
                        <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@else
<div class="text-center py-5">
    <i class="fas fa-university fa-4x text-muted mb-3 opacity-50"></i>
    <h4 class="text-muted">{{ __('No partner institutions yet') }}</h4>
    <p class="text-muted">Add partner institutions to enable cross-institutional research sharing.</p>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#institutionModal"><i class="fas fa-plus me-1"></i>{{ __('Add First Institution') }}</button>
</div>
@endif

{{-- Add/Edit Modal --}}
<div class="modal fade" id="institutionModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="instForm">@csrf
        <input type="hidden" name="form_action" id="instAction" value="create">
        <input type="hidden" name="institution_id" id="instId">
        <div class="modal-header"><h5 class="modal-title" id="instModalTitle">{{ __('Add Institution') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-8"><div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" id="instName" class="form-control" required></div></div>
                <div class="col-md-4"><div class="mb-3"><label class="form-label">{{ __('Code *') }}</label><input type="text" name="code" id="instCode" class="form-control" required placeholder="{{ __('e.g. UCT') }}"></div></div>
            </div>
            <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" id="instDesc" class="form-control" rows="2"></textarea></div>
            <div class="mb-3"><label class="form-label">URL</label><input type="url" name="url" id="instUrl" class="form-control" placeholder="{{ __('https://...') }}"></div>
            <div class="row">
                <div class="col-md-6"><div class="mb-3"><label class="form-label">{{ __('Contact Name') }}</label><input type="text" name="contact_name" id="instContactName" class="form-control"></div></div>
                <div class="col-md-6"><div class="mb-3"><label class="form-label">{{ __('Contact Email') }}</label><input type="email" name="contact_email" id="instContactEmail" class="form-control"></div></div>
            </div>
            <div class="form-check"><input type="checkbox" name="is_active" id="instActive" class="form-check-input" value="1" checked><label class="form-check-label" for="instActive">{{ __('Active') }}</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary" id="instSubmitBtn"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button></div>
    </form>
</div></div></div>

@push('js')
<script>
document.querySelectorAll('.edit-inst-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var i = JSON.parse(this.getAttribute('data-inst'));
        document.getElementById('instAction').value = 'update';
        document.getElementById('instId').value = i.id;
        document.getElementById('instName').value = i.name;
        document.getElementById('instCode').value = i.code || '';
        document.getElementById('instDesc').value = i.description || '';
        document.getElementById('instUrl').value = i.url || '';
        document.getElementById('instContactName').value = i.contact_name || '';
        document.getElementById('instContactEmail').value = i.contact_email || '';
        document.getElementById('instActive').checked = i.is_active == 1;
        document.getElementById('instModalTitle').textContent = 'Edit: ' + i.name;
        document.getElementById('instSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Save';
        new bootstrap.Modal(document.getElementById('institutionModal')).show();
    });
});
document.getElementById('institutionModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('instAction').value = 'create';
    document.getElementById('instForm').reset();
    document.getElementById('instModalTitle').textContent = 'Add Institution';
    document.getElementById('instSubmitBtn').innerHTML = '<i class="fas fa-plus me-1"></i>Add';
});
</script>
@endpush
@endsection
