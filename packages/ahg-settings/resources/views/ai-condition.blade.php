{{--
  AI Condition Assessment — settings + API clients + training data approval
  Cloned from AtoM ahgAiConditionPlugin/modules/aiCondition/templates/indexSuccess.php

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'AI Condition Assessment')
@section('body-class', 'admin settings')

@section('sidebar')
<div class="sidebar-content">
    {{-- Action buttons --}}
    <div class="card mb-3">
        <div class="card-header bg-primary text-white py-2">
            <h6 class="mb-0"><i class="fas fa-robot me-1"></i>{{ __('AI Condition') }}</h6>
        </div>
        <div class="card-body py-2">
            @if(\Route::has('admin.ai.condition.assess'))
            <a href="{{ route('admin.ai.condition.assess') }}" class="btn btn-success btn-sm w-100 mb-2">
                <i class="fas fa-camera me-1"></i>{{ __('New Assessment') }}
            </a>
            @endif
            @if(\Route::has('admin.ai.condition.bulk'))
            <a href="{{ route('admin.ai.condition.bulk') }}" class="btn btn-outline-primary btn-sm w-100 mb-2">
                <i class="fas fa-layer-group me-1"></i>{{ __('Bulk Scan') }}
            </a>
            @endif
            @if(\Route::has('admin.ai.condition.training'))
            <a href="{{ route('admin.ai.condition.training') }}" class="btn btn-outline-info btn-sm w-100 mb-2">
                <i class="fas fa-brain me-1"></i>{{ __('Model Training') }}
            </a>
            @endif
        </div>
    </div>

    {{-- Statistics --}}
    <div class="card mb-3">
        <div class="card-header py-2"><h6 class="mb-0">{{ __('Statistics') }}</h6></div>
        <div class="card-body py-2 small">
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Total Assessments') }}</span>
                <strong>{{ $stats['total'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Confirmed') }}</span>
                <strong class="text-success">{{ $stats['confirmed'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
                <span>{{ __('Pending Review') }}</span>
                <strong class="text-warning">{{ $stats['pending'] ?? 0 }}</strong>
            </div>
            <div class="d-flex justify-content-between">
                <span>{{ __('Avg Score') }}</span>
                <strong>{{ $stats['avg_score'] ?? '--' }}</strong>
            </div>
        </div>
    </div>
</div>
@endsection

@section('title-block')
<h1 class="h3 mb-0"><i class="fas fa-robot me-2"></i>{{ __('AI Condition Assessment') }}</h1>
<p class="text-muted small mb-3">Settings and API client management</p>
@endsection

@section('content')

@if(session('notice'))
<div class="alert alert-success alert-dismissible fade show">
    {{ session('notice') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Settings form --}}
<form method="post" action="{{ url()->current() }}">
    @csrf
    <input type="hidden" name="form_action" value="save_settings">

    <div class="card mb-3">
        <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-plug me-2"></i>{{ __('Service Connection') }}</h6></div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Service URL') }}</label>
                <div class="col-sm-7">
                    <input type="url" class="form-control form-control-sm" name="ai_condition_service_url"
                           value="{{ e($settings['ai_condition_service_url'] ?? '') }}">
                </div>
                <div class="col-sm-2">
                    <button type="button" class="btn btn-sm btn-outline-primary w-100" id="testBtn">
                        <i class="fas fa-plug me-1"></i>{{ __('Test') }}
                    </button>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('API Key') }}</label>
                <div class="col-sm-9">
                    <input type="text" class="form-control form-control-sm" name="ai_condition_api_key"
                           value="{{ e($settings['ai_condition_api_key'] ?? '') }}">
                </div>
            </div>
            <div id="testResult" style="display:none"></div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header py-2"><h6 class="mb-0"><i class="fas fa-sliders-h me-2"></i>{{ __('Assessment Defaults') }}</h6></div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Min Confidence') }}</label>
                <div class="col-sm-9">
                    <input type="number" class="form-control form-control-sm" name="ai_condition_min_confidence"
                           value="{{ e($settings['ai_condition_min_confidence'] ?? '0.5') }}"
                           min="0.1" max="0.9" step="0.05">
                    <div class="form-text">Minimum confidence threshold for damage detection (0.1 - 0.9)</div>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Overlay Enabled') }}</label>
                <div class="col-sm-9">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ai_condition_overlay_enabled" value="1"
                               {{ ($settings['ai_condition_overlay_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label small">{{ __('Generate annotated overlay images with bounding boxes') }}</label>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Auto-Scan on Upload') }}</label>
                <div class="col-sm-9">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="ai_condition_auto_scan" value="1"
                               {{ ($settings['ai_condition_auto_scan'] ?? '0') === '1' ? 'checked' : '' }}>
                        <label class="form-check-label small">{{ __('Automatically scan digital objects when uploaded') }}</label>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-3 col-form-label col-form-label-sm">{{ __('Alert Grade') }}</label>
                <div class="col-sm-9">
                    <select class="form-select form-select-sm" name="ai_condition_notify_grade">
                        @foreach(['excellent','good','fair','poor','critical'] as $g)
                        <option value="{{ $g }}" {{ ($settings['ai_condition_notify_grade'] ?? 'poor') === $g ? 'selected' : '' }}>{{ ucfirst($g) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Notify when condition grade is at or below this level</div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mb-4">
        <i class="fas fa-save me-1"></i>{{ __('Save Settings') }}
    </button>
</form>

{{-- API Clients --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('API Clients') }}</h6>
        <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
            <i class="fas fa-plus me-1"></i>{{ __('Add Client') }}
        </button>
    </div>
    <div class="card-body p-0">
        @if(empty($clients) || count($clients) === 0)
        <div class="p-3 text-center text-muted small">
            <i class="fas fa-info-circle me-1"></i>{{ __('No API clients configured.') }}
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Organization') }}</th>
                        <th>{{ __('Tier') }}</th>
                        <th class="text-center">{{ __('Usage') }}</th>
                        <th>{{ __('API Key') }}</th>
                        <th class="text-center">{{ __('Training') }}</th>
                        <th class="text-center">{{ __('Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $c)
                    <tr>
                        <td>{{ e($c->name) }}</td>
                        <td class="small">{{ e($c->organization ?? '') }}</td>
                        <td><span class="badge bg-info">{{ ucfirst($c->tier) }}</span></td>
                        <td class="text-center">
                            <span class="small">{{ $c->scans_used ?? 0 }} / {{ number_format($c->monthly_limit) }}</span>
                        </td>
                        <td>
                            <code class="small user-select-all">{{ e(substr($c->api_key, 0, 12)) }}...</code>
                        </td>
                        <td class="text-center">
                            @if($c->is_active)
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" {{ !empty($c->can_contribute_training) ? 'checked' : '' }}
                                    onchange="toggleTraining({{ $c->id }}, this.checked ? 1 : 0)"
                                    title="{{ __('Allow client to contribute training data') }}">
                            </div>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($c->is_active)
                            <span class="badge bg-success">{{ __('Active') }}</span>
                            @else
                            <span class="badge bg-danger">{{ __('Revoked') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($c->is_active)
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="revokeClient({{ $c->id }})">
                                <i class="fas fa-ban"></i>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Training Data Approval --}}
<div class="card mb-3">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>{{ __('Client Training Data Approval') }}</h6>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Review and approve client data for use as model training data. Client consent documentation must be uploaded before approval.
        </p>

        @php
            $approvalClients = collect($clients ?? [])->filter(fn($c) => $c->is_active && !empty($c->can_contribute_training));
        @endphp

        @if($approvalClients->isEmpty())
        <div class="text-center text-muted small py-3">
            <i class="fas fa-info-circle me-1"></i>{{ __('No clients have training contributions enabled. Toggle the Training switch in the API Clients table above.') }}
        </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Client') }}</th>
                        <th class="text-center">{{ __('Contributions') }}</th>
                        <th class="text-center">{{ __('Pending') }}</th>
                        <th class="text-center">{{ __('Approved') }}</th>
                        <th>{{ __('Consent Document') }}</th>
                        <th class="text-center">{{ __('Training Status') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($approvalClients as $ac)
                    @php
                        $cStats = $trainingContributions[$ac->id] ?? null;
                        $totalContrib = $cStats ? $cStats->total : 0;
                        $pendingContrib = $cStats ? $cStats->pending : 0;
                        $approvedContrib = $cStats ? $cStats->approved : 0;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ e($ac->name) }}</strong>
                            <br><small class="text-muted">{{ e($ac->organization ?? '') }}</small>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $totalContrib }}</span></td>
                        <td class="text-center">
                            @if($pendingContrib > 0)
                            <span class="badge bg-warning text-dark">{{ $pendingContrib }}</span>
                            @else
                            <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($approvedContrib > 0)
                            <span class="badge bg-success">{{ $approvedContrib }}</span>
                            @else
                            <span class="text-muted">0</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($ac->training_approval_doc))
                                <a href="/{{ e($ac->training_approval_doc) }}" target="_blank" class="small text-success">
                                    <i class="fas fa-file-alt me-1"></i>{{ __('View Document') }}
                                </a>
                            @else
                                <span class="small text-warning"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Not uploaded') }}</span>
                            @endif
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" onclick="uploadConsent({{ $ac->id }}, '{{ e($ac->name) }}')">
                                <i class="fas fa-upload"></i>
                            </button>
                        </td>
                        <td class="text-center">
                            @if($ac->training_approved)
                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Approved') }}</span>
                                @if($ac->training_approved_at)
                                <br><small class="text-muted">{{ date('d M Y', strtotime($ac->training_approved_at)) }}</small>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>{{ __('Pending') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if(!$ac->training_approved)
                                <button type="button" class="btn btn-sm btn-success" onclick="approveTraining({{ $ac->id }}, '{{ e($ac->name) }}')"
                                    {{ empty($ac->training_approval_doc) ? 'disabled title="{{ __('Upload consent document first') }}"' : '' }}>
                                    <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="pushTrainingData({{ $ac->id }})" {{ $approvedContrib < 1 ? 'disabled' : '' }}>
                                    <i class="fas fa-paper-plane me-1"></i>{{ __('Push to Training') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="revokeTrainingApproval({{ $ac->id }})">
                                    <i class="fas fa-ban"></i>
                                </button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Upload Consent Document Modal --}}
<div class="modal fade" id="uploadConsentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i>{{ __('Upload Consent Document') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Upload a signed consent/approval document from the client authorizing use of their data for model training.</p>
                <p class="small"><strong>{{ __('Client:') }}</strong> <span id="consentClientName"></span></p>
                <input type="hidden" id="consentClientId">
                <div class="mb-3">
                    <label class="form-label">Document <span class="text-danger">*</span></label>
                    <input type="file" class="form-control form-control-sm" id="consentFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="submitConsent()">
                    <i class="fas fa-upload me-1"></i>{{ __('Upload') }}
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Add Client Modal — real POST form (no JS) --}}
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ url('/admin/ai/condition/client/save') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>{{ __('Add API Client') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="name" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Organization') }}</label>
                    <input type="text" class="form-control form-control-sm" name="organization">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control form-control-sm" name="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Tier') }}</label>
                    <select class="form-select form-select-sm" name="tier">
                        <option value="free">{{ __('Free (50/month)') }}</option>
                        <option value="standard">{{ __('Standard (500/month)') }}</option>
                        <option value="pro">{{ __('Professional (5000/month)') }}</option>
                        <option value="enterprise">{{ __('Enterprise (Unlimited)') }}</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Monthly Limit') }}</label>
                    <input type="number" class="form-control form-control-sm" name="monthly_limit" value="50">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-success">{{ __('Create') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('testBtn').addEventListener('click', function() {
    var el = document.getElementById('testResult');
    el.style.display = '';
    el.innerHTML = '<div class="alert alert-info py-1 small"><i class="fas fa-spinner fa-spin me-1"></i>Testing...</div>';

    fetch('{{ url("/admin/ai/condition/api-test") }}')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var d = data.data || {};
            var models = d.models || {};
            var detector = models.detector || {};
            el.innerHTML = '<div class="alert alert-success py-1 small"><i class="fas fa-check me-1"></i>Connected! Version: ' + (d.version || 'unknown') + ', Detector: ' + (detector.mode || 'unknown') + ', GPU: ' + (d.gpu && d.gpu.available ? 'Yes' : 'No') + '</div>';
        } else {
            el.innerHTML = '<div class="alert alert-danger py-1 small"><i class="fas fa-times me-1"></i>' + (data.error || 'Connection failed') + '</div>';
        }
    })
    .catch(function() {
        el.innerHTML = '<div class="alert alert-danger py-1 small"><i class="fas fa-times me-1"></i>Network error</div>';
    });
});

function saveClient() {
    var data = 'name=' + encodeURIComponent(document.getElementById('clientName').value)
        + '&organization=' + encodeURIComponent(document.getElementById('clientOrg').value)
        + '&email=' + encodeURIComponent(document.getElementById('clientEmail').value)
        + '&tier=' + document.getElementById('clientTier').value
        + '&monthly_limit=' + document.getElementById('clientLimit').value
        + '&_token={{ csrf_token() }}';

    fetch('{{ url("/admin/ai/condition/client/save") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: data
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) location.reload();
        else alert(d.error || 'Error');
    });
}

function revokeClient(id) {
    if (!confirm('Revoke this API key? The client will lose access.')) return;
    fetch('{{ url("/admin/ai/condition/client/revoke") }}?id=' + id, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'}
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
}

function toggleTraining(id, enabled) {
    fetch('{{ url("/admin/ai/condition/client/training-toggle") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: 'id=' + id + '&enabled=' + enabled
    }).then(function(r) { return r.json(); }).then(function() {
        location.reload();
    });
}

function uploadConsent(clientId, clientName) {
    document.getElementById('consentClientId').value = clientId;
    document.getElementById('consentClientName').textContent = clientName;
    document.getElementById('consentFile').value = '';
    var modal = new bootstrap.Modal(document.getElementById('uploadConsentModal'));
    modal.show();
}

function submitConsent() {
    var clientId = document.getElementById('consentClientId').value;
    var fileInput = document.getElementById('consentFile');
    if (!fileInput.files.length) {
        alert('Please select a file.');
        return;
    }
    var formData = new FormData();
    formData.append('id', clientId);
    formData.append('consent_doc', fileInput.files[0]);
    formData.append('_token', '{{ csrf_token() }}');

    fetch('{{ url("/admin/ai/condition/client/upload-consent") }}', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            bootstrap.Modal.getInstance(document.getElementById('uploadConsentModal')).hide();
            location.reload();
        } else {
            alert(d.error || 'Upload failed');
        }
    });
}

function approveTraining(clientId, clientName) {
    if (!confirm('Approve training data usage for client "' + clientName + '"?\n\nThis will allow their contributed assessment data to be used for model training.')) return;

    fetch('{{ url("/admin/ai/condition/client/approve-training") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: 'id=' + clientId + '&approve_action=approve'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) location.reload();
        else alert(d.error || 'Approval failed');
    });
}

function revokeTrainingApproval(clientId) {
    if (!confirm('Revoke training approval for this client? Pending contributions will remain but no new data will be used.')) return;

    fetch('{{ url("/admin/ai/condition/client/approve-training") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: 'id=' + clientId + '&approve_action=revoke'
    })
    .then(function(r) { return r.json(); })
    .then(function(d) { if (d.success) location.reload(); });
}

function pushTrainingData(clientId) {
    if (!confirm('Push approved contributions to the training pipeline? This will build a dataset from the client\'s approved data.')) return;

    fetch('{{ url("/admin/ai/condition/client/push-training") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: 'client_id=' + clientId
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            alert('Training data pushed successfully!');
            location.reload();
        } else {
            alert(d.error || 'Push failed');
        }
    });
}
</script>
@endsection
