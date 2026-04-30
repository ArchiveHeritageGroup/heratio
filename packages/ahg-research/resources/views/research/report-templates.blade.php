@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reports'])@endsection
@section('title', 'Report Templates')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.reports') }}">Reports</a></li>
        <li class="breadcrumb-item active">Templates</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-layer-group text-primary me-2"></i>{{ __('Report Templates') }}</h1>
    <div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createTemplateModal"><i class="fas fa-plus me-1"></i>{{ __('New Template') }}</button>
        <a href="{{ route('research.reports') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
</div>

<div class="row">
@foreach($templates as $tpl)
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    {{ e($tpl->name) }}
                    @if($tpl->is_system)<span class="badge bg-info ms-2">{{ __('System') }}</span>@endif
                </h6>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary edit-tpl-btn"
                        data-id="{{ $tpl->id }}"
                        data-name="{{ e($tpl->name) }}"
                        data-description="{{ e($tpl->description ?? '') }}"
                        data-sections="{{ e(implode("\n", json_decode($tpl->sections_config ?? '[]', true) ?: [])) }}"
                        data-system="{{ $tpl->is_system }}"
                        title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></button>
                    @if(!$tpl->is_system)
                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this template?')">
                        @csrf
                        <input type="hidden" name="form_action" value="delete">
                        <input type="hidden" name="template_id" value="{{ $tpl->id }}">
                        <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                    </form>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if($tpl->description)
                    <p class="text-muted small mb-2">{{ e($tpl->description) }}</p>
                @endif
                <strong class="small">{{ __('Sections:') }}</strong>
                @php $sections = json_decode($tpl->sections_config ?? '[]', true) ?: []; @endphp
                @if(!empty($sections))
                    <ol class="small mb-0 ps-3 mt-1">
                        @foreach($sections as $s)
                            @php $parts = explode(':', $s, 2); @endphp
                            <li>
                                <span class="badge bg-light text-dark me-1">{{ $parts[0] }}</span>
                                {{ $parts[1] ?? ucfirst($parts[0]) }}
                            </li>
                        @endforeach
                    </ol>
                @else
                    <small class="text-muted">{{ __('No sections (blank template)') }}</small>
                @endif
            </div>
            <div class="card-footer small text-muted">
                Code: <code>{{ $tpl->code }}</code>
            </div>
        </div>
    </div>
@endforeach
</div>

{{-- Create Template Modal --}}
<div class="modal fade" id="createTemplateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="create">
    <div class="modal-header"><h5 class="modal-title">{{ __('New Template') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" class="form-control" required placeholder="{{ __('e.g. Conservation Report') }}"></div>
        <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="mb-3">
            <label class="form-label">{{ __('Sections (one per line)') }}</label>
            <textarea name="sections_raw" class="form-control" rows="8" placeholder="{{ __('type:Title
text:Introduction
text:Methodology
heading:Findings
text:Discussion
bibliography') }}"></textarea>
            <small class="text-muted">Format: <code>type:Title</code> — Types: text, heading, title_page, toc, bibliography, collection_list, annotation_list</small>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Create') }}</button></div>
    </form>
</div></div></div>

{{-- Edit Template Modal --}}
<div class="modal fade" id="editTemplateModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="update"><input type="hidden" name="template_id" id="editTplId">
    <div class="modal-header"><h5 class="modal-title">{{ __('Edit Template') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">{{ __('Name *') }}</label><input type="text" name="name" id="editTplName" class="form-control" required></div>
        <div class="mb-3"><label class="form-label">{{ __('Description') }}</label><textarea name="description" id="editTplDesc" class="form-control" rows="2"></textarea></div>
        <div class="mb-3">
            <label class="form-label">{{ __('Sections (one per line)') }}</label>
            <textarea name="sections_raw" id="editTplSections" class="form-control" rows="8"></textarea>
            <small class="text-muted">Format: <code>type:Title</code></small>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button></div>
    </form>
</div></div></div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-tpl-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editTplId').value = this.dataset.id;
            document.getElementById('editTplName').value = this.dataset.name;
            document.getElementById('editTplDesc').value = this.dataset.description;
            document.getElementById('editTplSections').value = this.dataset.sections;
            new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
        });
    });
});
</script>
@endpush
@endsection
