@extends('theme::layouts.2col')

@section('sidebar')
    @include('ahg-research::research._sidebar', ['sidebarActive' => 'validationQueue'])
@endsection

@section('title', 'Validation Queue')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Validation Queue</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2">Validation Queue <span class="badge bg-warning">{{ (int) $pendingCount }} pending</span></h1>
</div>

<!-- Stats bar -->
<div class="row mb-4">
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-warning">{{ (int) ($stats['pending'] ?? 0) }}</div><small class="text-muted">Pending</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-success">{{ (int) ($stats['accepted'] ?? 0) }}</div><small class="text-muted">Accepted</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-danger">{{ (int) ($stats['rejected'] ?? 0) }}</div><small class="text-muted">Rejected</small></div></div></div>
    <div class="col-md-3"><div class="card text-center"><div class="card-body py-2"><div class="fs-4 fw-bold text-info">{{ ($stats['avg_confidence'] ?? null) !== null ? number_format((float)$stats['avg_confidence'] * 100, 1) . '%' : '-' }}</div><small class="text-muted">Avg Confidence</small></div></div></div>
</div>

<!-- Filter bar -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Status <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="status" class="form-select form-select-sm">
                    <option value="pending" {{ request('status', 'pending') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>Accepted</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="modified" {{ request('status') === 'modified' ? 'selected' : '' }}>Modified</option>
                    <option value="" {{ request('status') === '' ? 'selected' : '' }}>All</option>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Result Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="result_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['entity','summary','translation','transcription','form_field','face'] as $rt)
                    <option value="{{ $rt }}" {{ request('result_type') === $rt ? 'selected' : '' }}>{{ ucfirst($rt) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Extraction Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="extraction_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach(['ocr','ner','summarize','translate','spellcheck','face_detection','form_extraction'] as $et)
                    <option value="{{ $et }}" {{ request('extraction_type') === $et ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $et)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-0">Min Confidence <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="number" name="min_confidence" class="form-control form-control-sm" style="width:100px" min="0" max="1" step="0.01" value="{{ request('min_confidence', '') }}" placeholder="0.00">
            </div>
            <div class="col-auto"><button type="submit" class="btn atom-btn-outline-light btn-sm">Filter</button></div>
        </form>
    </div>
</div>

@if(empty($queue['items'] ?? []))
    <div class="alert alert-success">No items matching your filters.</div>
@else
<div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
        <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
                <th><input type="checkbox" id="selectAll"></th>
                <th>Object</th>
                <th>Extraction</th>
                <th>Result Type</th>
                <th>Model</th>
                <th>Confidence</th>
                <th>Reviewer</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @foreach($queue['items'] as $item)
            <tr>
                <td><input type="checkbox" class="queue-item" value="{{ (int) $item->result_id }}"></td>
                <td>
                    @if(!empty($item->object_title))
                        <strong>{{ Str::limit($item->object_title, 50) }}</strong>
                    @else
                        Object #{{ (int) ($item->object_id ?? 0) }}
                    @endif
                </td>
                <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $item->extraction_type ?? '') }}</span></td>
                <td><span class="badge bg-light text-dark">{{ $item->result_type ?? '' }}</span></td>
                <td><small class="text-muted">{{ Str::limit($item->model_version ?? '-', 20) }}</small></td>
                <td>
                    @if(isset($item->confidence))
                    @php $pct = round((float)$item->confidence * 100); $color = $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'); @endphp
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress" style="width:60px;height:6px">
                            <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                        </div>
                        <small>{{ $pct }}%</small>
                    </div>
                    @else - @endif
                </td>
                <td>
                    @if(!empty($item->reviewer_first_name))
                        <small>{{ $item->reviewer_first_name }} {{ $item->reviewer_last_name }}</small>
                    @else
                        <small class="text-muted">-</small>
                    @endif
                </td>
                <td><span class="badge bg-{{ match($item->status ?? '') { 'pending' => 'warning', 'accepted' => 'success', 'rejected' => 'danger', 'modified' => 'info', default => 'secondary' } }}">{{ ucfirst($item->status ?? 'pending') }}</span></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn atom-btn-white preview-btn" data-id="{{ (int) $item->result_id }}" data-data="{{ e($item->data_json ?? '{}') }}" title="Preview"><i class="fas fa-eye"></i></button>
                        @if(($item->status ?? '') === 'pending')
                        <button class="btn atom-btn-outline-success validate-btn" data-id="{{ (int) $item->result_id }}" data-action="accept" title="Accept"><i class="fas fa-check"></i></button>
                        <button class="btn atom-btn-white modify-btn" data-id="{{ (int) $item->result_id }}" data-data="{{ e($item->data_json ?? '{}') }}" title="Edit & Accept"><i class="fas fa-edit"></i></button>
                        <button class="btn atom-btn-outline-danger validate-btn" data-id="{{ (int) $item->result_id }}" data-action="reject" title="Reject"><i class="fas fa-times"></i></button>
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="d-flex gap-2 mt-3">
    <button class="btn atom-btn-outline-success" id="bulkAccept"><i class="fas fa-check-double me-1"></i>Bulk Accept Selected</button>
    <button class="btn atom-btn-outline-danger" id="bulkReject"><i class="fas fa-times-circle me-1"></i>Bulk Reject Selected</button>
</div>
@endif

<!-- Data Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Data Preview</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><pre id="previewData" class="bg-light p-3 rounded" style="max-height:400px;overflow:auto;white-space:pre-wrap"></pre></div>
        </div>
    </div>
</div>

<!-- Modify Modal -->
<div class="modal fade" id="modifyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Edit & Accept</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="text-muted">Edit the JSON data below, then click "Accept with Changes".</p>
                <textarea id="modifyData" class="form-control font-monospace" rows="12"></textarea>
                <input type="hidden" id="modifyResultId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="modifyAcceptBtn" class="btn atom-btn-outline-success">Accept with Changes</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    document.getElementById('selectAll')?.addEventListener('change', function() {
        var checked = this.checked;
        document.querySelectorAll('.queue-item').forEach(function(cb) { cb.checked = checked; });
    });

    document.querySelectorAll('.preview-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            try {
                var data = JSON.parse(this.dataset.data);
                document.getElementById('previewData').textContent = JSON.stringify(data, null, 2);
            } catch(e) {
                document.getElementById('previewData').textContent = this.dataset.data;
            }
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        });
    });

    document.querySelectorAll('.modify-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('modifyResultId').value = this.dataset.id;
            try {
                var data = JSON.parse(this.dataset.data);
                document.getElementById('modifyData').value = JSON.stringify(data, null, 2);
            } catch(e) {
                document.getElementById('modifyData').value = this.dataset.data;
            }
            new bootstrap.Modal(document.getElementById('modifyModal')).show();
        });
    });

    document.getElementById('modifyAcceptBtn')?.addEventListener('click', function() {
        var resultId = document.getElementById('modifyResultId').value;
        var raw = document.getElementById('modifyData').value;
        try { var modified = JSON.parse(raw); } catch(e) { alert('Invalid JSON'); return; }
        fetch('/research/validate/' + resultId, {
            method: 'POST', headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({form_action: 'modify', modified_data: modified})
        }).then(function(r){return r.json();}).then(function(d){
            if(d.success) location.reload(); else alert(d.error||'Error');
        });
    });

    document.querySelectorAll('.validate-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var action = this.dataset.action;
            var body = {form_action: action};
            if (action === 'reject') {
                var reason = prompt('Rejection reason:');
                if (reason === null) return;
                body.reason = reason;
            }
            fetch('/research/validate/' + this.dataset.id, {
                method: 'POST', headers: {'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify(body)
            }).then(function(r){return r.json();}).then(function(d){
                if(d.success) location.reload(); else alert(d.error||'Error');
            });
        });
    });

    document.getElementById('bulkAccept')?.addEventListener('click', function() {
        var ids = Array.from(document.querySelectorAll('.queue-item:checked')).map(function(cb){return parseInt(cb.value);});
        if (!ids.length) return alert('Select items first');
        fetch('/research/bulk-validate', { method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken}, body:JSON.stringify({result_ids:ids,form_action:'accept'}) }).then(function(r){return r.json();}).then(function(d){ if(d.success) location.reload(); });
    });

    document.getElementById('bulkReject')?.addEventListener('click', function() {
        var ids = Array.from(document.querySelectorAll('.queue-item:checked')).map(function(cb){return parseInt(cb.value);});
        if (!ids.length) return alert('Select items first');
        var reason = prompt('Rejection reason:');
        if (reason === null) return;
        fetch('/research/bulk-validate', { method:'POST', headers:{'Content-Type':'application/json', 'X-CSRF-TOKEN': csrfToken}, body:JSON.stringify({result_ids:ids,form_action:'reject',reason:reason||''}) }).then(function(r){return r.json();}).then(function(d){ if(d.success) location.reload(); });
    });
});
</script>
@endsection
