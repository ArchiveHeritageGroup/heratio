@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'adminQuotas'])@endsection
@section('title', 'Quota Management')

@php
    if (! function_exists('ahg_quota_human_bytes')) {
        function ahg_quota_human_bytes($bytes) {
            if ($bytes === null) return null;
            $units = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0; $n = (float) $bytes;
            while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
            return round($n, $i === 0 ? 0 : 1) . ' ' . $units[$i];
        }
    }
    if (! function_exists('ahg_quota_bar_class')) {
        function ahg_quota_bar_class($pct, $softWarn = 80) {
            if ($pct >= 100) return 'bg-danger';
            if ($pct >= $softWarn) return 'bg-warning';
            return 'bg-success';
        }
    }
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">{{ __('Quota Management') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">@foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-gauge-high text-primary me-2"></i>{{ __('Quota Management') }}</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPolicyModal"><i class="fas fa-plus me-1"></i>{{ __('Add Policy') }}</button>
</div>

{{-- (a) Usage vs limit --}}
<div class="card mb-4">
    <div class="card-header bg-light"><strong><i class="fas fa-users me-2"></i>{{ __('Usage vs limit') }}</strong></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Researcher') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th width="120">{{ __('Period') }}</th>
                    <th width="260">{{ __('Downloads') }}</th>
                    <th width="260">{{ __('Storage') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($usage as $row)
                <tr>
                    <td>
                        <strong>{{ e($row['name'] ?: '(unnamed)') }}</strong>
                        @if(!empty($row['email']))<br><small class="text-muted">{{ e($row['email']) }}</small>@endif
                    </td>
                    <td><span class="badge bg-secondary">{{ e($row['status'] ?? '-') }}</span></td>
                    <td><span class="badge bg-info text-dark">{{ e($row['period']) }}</span></td>
                    <td>
                        @if($row['download_limit'] === null)
                            <span class="text-muted">{{ $row['download_usage'] }} / <em>{{ __('unlimited') }}</em></span>
                        @else
                            <div class="small mb-1">{{ $row['download_usage'] }} / {{ $row['download_limit'] }} ({{ $row['download_pct'] }}%)</div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar {{ ahg_quota_bar_class($row['download_pct']) }}" role="progressbar" style="width: {{ min(100, $row['download_pct']) }}%"></div>
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($row['storage_limit'] === null)
                            <span class="text-muted">{{ ahg_quota_human_bytes($row['storage_usage']) }} / <em>{{ __('unlimited') }}</em></span>
                        @else
                            <div class="small mb-1">{{ ahg_quota_human_bytes($row['storage_usage']) }} / {{ ahg_quota_human_bytes($row['storage_limit']) }} ({{ $row['storage_pct'] }}%)</div>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar {{ ahg_quota_bar_class($row['storage_pct']) }}" role="progressbar" style="width: {{ min(100, $row['storage_pct']) }}%"></div>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No researchers found') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- (b) Quota policies CRUD --}}
<div class="card">
    <div class="card-header bg-light"><strong><i class="fas fa-sliders me-2"></i>{{ __('Quota policies') }}</strong></div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>{{ __('Scope') }}</th>
                    <th>{{ __('Scope key') }}</th>
                    <th>{{ __('Period') }}</th>
                    <th>{{ __('Max downloads') }}</th>
                    <th>{{ __('Max storage') }}</th>
                    <th>{{ __('Soft warn %') }}</th>
                    <th>{{ __('Active') }}</th>
                    <th width="100">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody>
            @forelse($policies as $p)
                <tr>
                    <td><span class="badge bg-primary">{{ e($p->scope) }}</span></td>
                    <td><code>{{ e($p->scope_key) }}</code></td>
                    <td>{{ e($p->period) }}</td>
                    <td>{{ $p->max_downloads === null ? __('unlimited') : $p->max_downloads }}</td>
                    <td>{{ $p->max_storage_bytes === null ? __('unlimited') : ahg_quota_human_bytes($p->max_storage_bytes) }}</td>
                    <td>{{ (int) $p->soft_warn_pct }}%</td>
                    <td>{!! ((int) ($p->is_active ?? 1)) ? '<span class="badge bg-success">' . e(__('Active')) . '</span>' : '<span class="badge bg-danger">' . e(__('Inactive')) . '</span>' !!}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary edit-policy-btn"
                            data-policy='{!! json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) !!}'
                            title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this policy?')">
                            @csrf
                            <input type="hidden" name="form_action" value="delete">
                            <input type="hidden" name="policy_id" value="{{ $p->id }}">
                            <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No quota policies configured') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Add/Edit Policy Modal --}}
<div class="modal fade" id="addPolicyModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" id="policyForm">@csrf
        <input type="hidden" name="form_action" id="policyAction" value="create">
        <input type="hidden" name="policy_id" id="policyId">
        <div class="modal-header"><h5 class="modal-title" id="policyModalTitle">{{ __('Add Quota Policy') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Scope *') }}</label>
                    <select name="scope" id="policyScope" class="form-select" required>
                        @foreach($scopeOptions as $opt)
                            <option value="{{ e($opt['code']) }}">{{ e($opt['label']) }}</option>
                        @endforeach
                    </select>
                </div></div>
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Scope key') }}</label>
                    <input type="text" name="scope_key" id="policyScopeKey" class="form-control" placeholder="{{ __('* / type code / id') }}">
                    <small class="text-muted">{{ __('Use * for global. Role = researcher type code; User = researcher id; Project = project id.') }}</small>
                </div></div>
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Period *') }}</label>
                    <select name="period" id="policyPeriod" class="form-select" required>
                        @foreach($periodOptions as $opt)
                            <option value="{{ e($opt['code']) }}">{{ e($opt['label']) }}</option>
                        @endforeach
                    </select>
                </div></div>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Max downloads') }}</label>
                    <input type="number" name="max_downloads" id="policyMaxDl" class="form-control" min="0" placeholder="{{ __('blank = unlimited') }}">
                </div></div>
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Max storage (bytes)') }}</label>
                    <input type="number" name="max_storage_bytes" id="policyMaxStorage" class="form-control" min="0" placeholder="{{ __('blank = unlimited') }}">
                    <small class="text-muted" id="policyStorageHint"></small>
                </div></div>
                <div class="col-md-4"><div class="mb-3">
                    <label class="form-label">{{ __('Soft warn % *') }}</label>
                    <input type="number" name="soft_warn_pct" id="policySoftWarn" class="form-control" value="80" min="1" max="100" required>
                </div></div>
            </div>
            <div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" id="policyNotes" class="form-control" rows="2"></textarea></div>
            <div class="form-check mb-2"><input type="checkbox" name="is_active" id="policyActive" class="form-check-input" value="1" checked><label class="form-check-label" for="policyActive">{{ __('Active') }}</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn btn-primary" id="policySubmitBtn"><i class="fas fa-plus me-1"></i>{{ __('Add Policy') }}</button></div>
    </form>
</div></div></div>

@push('js')
<script>
function ahgQuotaHumanBytes(bytes) {
    if (bytes === null || bytes === '' || bytes === undefined) return '';
    var units = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0, n = parseFloat(bytes);
    if (isNaN(n)) return '';
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return (Math.round(n * 10) / 10) + ' ' + units[i];
}
function ahgQuotaUpdateStorageHint() {
    var v = document.getElementById('policyMaxStorage').value;
    document.getElementById('policyStorageHint').textContent = v ? ahgQuotaHumanBytes(v) : '';
}
document.getElementById('policyMaxStorage').addEventListener('input', ahgQuotaUpdateStorageHint);

document.querySelectorAll('.edit-policy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var p = JSON.parse(this.getAttribute('data-policy'));
        document.getElementById('policyAction').value = 'update';
        document.getElementById('policyId').value = p.id;
        document.getElementById('policyScope').value = p.scope;
        document.getElementById('policyScopeKey').value = p.scope_key || '';
        document.getElementById('policyPeriod').value = p.period;
        document.getElementById('policyMaxDl').value = (p.max_downloads === null || p.max_downloads === undefined) ? '' : p.max_downloads;
        document.getElementById('policyMaxStorage').value = (p.max_storage_bytes === null || p.max_storage_bytes === undefined) ? '' : p.max_storage_bytes;
        document.getElementById('policySoftWarn').value = p.soft_warn_pct || 80;
        document.getElementById('policyNotes').value = p.notes || '';
        document.getElementById('policyActive').checked = (p.is_active == 1);
        ahgQuotaUpdateStorageHint();
        document.getElementById('policyModalTitle').textContent = 'Edit Policy: ' + p.scope + ' / ' + (p.scope_key || '*');
        document.getElementById('policySubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Save Changes';
        new bootstrap.Modal(document.getElementById('addPolicyModal')).show();
    });
});

document.getElementById('addPolicyModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('policyAction').value = 'create';
    document.getElementById('policyForm').reset();
    document.getElementById('policyStorageHint').textContent = '';
    document.getElementById('policyModalTitle').textContent = 'Add Quota Policy';
    document.getElementById('policySubmitBtn').innerHTML = '<i class="fas fa-plus me-1"></i>Add Policy';
});
</script>
@endpush
@endsection
