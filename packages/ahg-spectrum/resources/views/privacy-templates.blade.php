@extends('theme::layouts.1col')

@section('title', __('Privacy Template Library'))

@section('content')
<h1 class="h3 mb-4"><i class="fas fa-file-alt me-2"></i>{{ __('Privacy Template Library') }}</h1>

<div class="mb-3">
    <a href="{{ route('ahgspectrum.privacy-admin') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadTemplateModal">
        <i class="fas fa-upload me-1"></i>{{ __('Upload Template') }}
    </button>
</div>

<style>
.template-card .card-header { color: #ffffff !important; font-weight: 600; }
.template-card .card-header h6 { color: #ffffff !important; }
.template-card .card-header i { color: #ffffff !important; }
</style>

@php
$templateArray = [];
if (isset($templates)) {
    foreach ($templates as $t) {
        $templateArray[] = (object)[
            'id' => $t->id,
            'category' => $t->category,
            'name' => $t->name,
            'content' => $t->content ?? '',
            'file_path' => $t->file_path ?? null,
            'file_name' => $t->file_name ?? null,
            'file_size' => $t->file_size ?? 0,
        ];
    }
}

$categories = [
    'paia_manual' => ['icon' => 'fa-book', 'color' => '#1a5f2a', 'label' => 'PAIA Manuals'],
    'privacy_notice' => ['icon' => 'fa-shield-alt', 'color' => '#17a2b8', 'label' => 'Privacy Notices'],
    'dsar_response' => ['icon' => 'fa-reply', 'color' => '#d4a200', 'label' => 'DSAR Responses'],
    'breach_notification' => ['icon' => 'fa-exclamation-triangle', 'color' => '#dc3545', 'label' => 'Breach Notifications'],
    'consent_form' => ['icon' => 'fa-check-square', 'color' => '#28a745', 'label' => 'Consent Forms'],
    'retention_schedule' => ['icon' => 'fa-calendar-alt', 'color' => '#6c757d', 'label' => 'Retention Schedules'],
];
@endphp

<div class="row">
@foreach($categories as $cat => $info)
    @php
    $catTemplates = [];
    foreach ($templateArray as $t) {
        if ($t->category === $cat) {
            $catTemplates[] = $t;
        }
    }
    @endphp
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100 template-card">
            <div class="card-header" style="background-color: {{ $info['color'] }};">
                <h6 class="mb-0" style="color: #fff;"><i class="fas {{ $info['icon'] }} me-2" style="color: #fff;"></i>{{ __($info['label']) }}</h6>
            </div>
            <ul class="list-group list-group-flush">
            @if(count($catTemplates) > 0)
                @foreach($catTemplates as $t)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ $t->name }}</strong>
                            @if($t->file_name)
                                <br><small class="text-muted">
                                    <i class="fas fa-file-word text-primary me-1"></i>
                                    {{ $t->file_name }}
                                    ({{ round(($t->file_size ?? 0) / 1024) }} KB)
                                </small>
                            @endif
                        </div>
                        <div class="btn-group btn-group-sm">
                            @if($t->file_path)
                                <a href="{{ route('ahgspectrum.privacy-templates') }}/download?id={{ $t->id }}" class="btn btn-outline-success" title="{{ __('Download') }}">
                                    <i class="fas fa-download"></i>
                                </a>
                            @endif
                            <button class="btn btn-outline-warning" onclick="replaceTemplate({{ $t->id }}, '{{ addslashes($t->name) }}')" title="{{ __('Replace') }}">
                                <i class="fas fa-sync"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteTemplate({{ $t->id }})" title="{{ __('Delete') }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </li>
                @endforeach
            @else
                <li class="list-group-item text-muted text-center"><small>{{ __('No templates') }}</small></li>
            @endif
            </ul>
        </div>
    </div>
@endforeach
</div>

<!-- Upload Template Modal -->
<div class="modal fade" id="uploadTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgspectrum.privacy-templates') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_action" value="upload">
                <div class="modal-header" style="background-color: #1a5f2a;">
                    <h5 class="modal-title" style="color: #fff;"><i class="fas fa-upload me-2"></i>{{ __('Upload Template') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Category *') }}</label>
                        <select name="category" class="form-select" required>
                            <option value="">-- Select --</option>
                            <option value="paia_manual">{{ __('PAIA Manual') }}</option>
                            <option value="privacy_notice">{{ __('Privacy Notice') }}</option>
                            <option value="dsar_response">{{ __('DSAR Response') }}</option>
                            <option value="breach_notification">{{ __('Breach Notification') }}</option>
                            <option value="consent_form">{{ __('Consent Form') }}</option>
                            <option value="retention_schedule">{{ __('Retention Schedule') }}</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Template Name *') }}</label>
                        <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g., PAIA Manual - Standard') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Word Document (.docx) *') }}</label>
                        <input type="file" name="template_file" class="form-control" accept=".docx,.doc" required>
                        <small class="text-muted">{{ __('Upload a .docx file') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>{{ __('Upload') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Replace Template Modal -->
<div class="modal fade" id="replaceTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgspectrum.privacy-templates') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="form_action" value="replace">
                <input type="hidden" name="id" id="replace_id">
                <div class="modal-header" style="background-color: #d4a200;">
                    <h5 class="modal-title" style="color: #fff;"><i class="fas fa-sync me-2"></i>{{ __('Replace Template') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Replacing: <strong id="replace_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">{{ __('New Word Document (.docx) *') }}</label>
                        <input type="file" name="template_file" class="form-control" accept=".docx,.doc" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-sync me-1"></i>{{ __('Replace') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function replaceTemplate(id, name) {
    document.getElementById('replace_id').value = id;
    document.getElementById('replace_name').textContent = name;
    new bootstrap.Modal(document.getElementById('replaceTemplateModal')).show();
}

function deleteTemplate(id) {
    if (confirm('Delete this template?')) {
        window.location.href = '{{ route('ahgspectrum.privacy-templates') }}/delete?id=' + id;
    }
}
</script>
@endsection
