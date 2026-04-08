@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'documentTemplates'])
@endsection

@section('title', 'Document Templates')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Document Templates</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Document Templates</h1>
    <button class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#newTemplateModal"><i class="fas fa-plus me-1"></i> New Template</button>
</div>

@if(empty($templates))
    <div class="alert alert-info">No document templates defined.</div>
@else
<div class="row">
    @foreach($templates as $t)
    <div class="col-md-4 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <h5>{{ e($t->name) }}</h5>
                <span class="badge bg-light text-dark">{{ e($t->document_type) }}</span>
                <p class="text-muted small mt-2">{{ e($t->description ?? '') }}</p>
            </div>
            <div class="card-footer">
                <button class="btn btn-sm atom-btn-white edit-template-btn"
                    data-id="{{ (int) $t->id }}"
                    data-name="{{ e($t->name) }}"
                    data-type="{{ e($t->document_type) }}"
                    data-description="{{ e($t->description ?? '') }}"
                    data-fields="{{ e($t->fields_json ?? '[]') }}">Edit</button>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

<!-- New Template Modal -->
<div class="modal fade" id="newTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ url('/research/documentTemplates') }}">
                @csrf
                <input type="hidden" name="form_action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">New Document Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name * <span class="badge bg-danger ms-1">Required</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Type * <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="document_type" class="form-select">
                            <option value="research_report">Research Report</option>
                            <option value="finding_aid">Finding Aid</option>
                            <option value="accession_form">Accession Form</option>
                            <option value="condition_report">Condition Report</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="badge bg-danger ms-1">Required</span></label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fields (JSON) <span class="badge bg-danger ms-1">Required</span></label>
                        <textarea name="fields_json" class="form-control font-monospace" rows="4" placeholder='[{"name": "title", "type": "text", "required": true}]'></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn atom-btn-outline-success">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ url('/research/documentTemplates') }}">
                @csrf
                <input type="hidden" name="form_action" value="update">
                <input type="hidden" name="template_id" id="editTemplateId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Document Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name * <span class="badge bg-danger ms-1">Required</span></label>
                        <input type="text" name="name" id="editTemplateName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Document Type * <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="document_type" id="editTemplateType" class="form-select">
                            <option value="research_report">Research Report</option>
                            <option value="finding_aid">Finding Aid</option>
                            <option value="accession_form">Accession Form</option>
                            <option value="condition_report">Condition Report</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                        <textarea name="description" id="editTemplateDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fields (JSON) <span class="badge bg-secondary ms-1">Optional</span></label>
                        <textarea name="fields_json" id="editTemplateFields" class="form-control font-monospace" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn atom-btn-outline-success">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-template-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editTemplateId').value = this.dataset.id;
            document.getElementById('editTemplateName').value = this.dataset.name;
            document.getElementById('editTemplateType').value = this.dataset.type;
            document.getElementById('editTemplateDescription').value = this.dataset.description;
            try {
                var fields = JSON.parse(this.dataset.fields);
                document.getElementById('editTemplateFields').value = JSON.stringify(fields, null, 2);
            } catch(e) {
                document.getElementById('editTemplateFields').value = this.dataset.fields;
            }
            new bootstrap.Modal(document.getElementById('editTemplateModal')).show();
        });
    });
});
</script>
@endsection
