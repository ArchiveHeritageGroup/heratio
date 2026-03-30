@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-key me-2"></i>API Key Management</h1>@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Your API Keys</h5>
        <button class="btn atom-btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#generateKeyModal"><i class="fas fa-plus me-1"></i>Generate New Key</button>
    </div>
    <div class="card-body p-0">
        @if(count($apiKeys) > 0)
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Key</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($apiKeys as $key)
                    <tr>
                        <td class="fw-bold">{{ e($key->name ?? 'API Key') }}</td>
                        <td>
                            <code class="text-muted">
                                @if($key->key_prefix ?? null)
                                    {{ e($key->key_prefix) }}...
                                @else
                                    {{ e(substr($key->api_key ?? $key->key_hash ?? '********', 0, 8)) }}...
                                @endif
                            </code>
                        </td>
                        <td>{{ $key->created_at ? \Carbon\Carbon::parse($key->created_at)->format('j M Y') : '-' }}</td>
                        <td>{{ $key->last_used_at ? \Carbon\Carbon::parse($key->last_used_at)->format('j M Y H:i') : 'Never' }}</td>
                        <td>
                            @if($key->expires_at ?? null)
                                @if(\Carbon\Carbon::parse($key->expires_at)->isPast())
                                    <span class="text-danger">{{ \Carbon\Carbon::parse($key->expires_at)->format('j M Y') }}</span>
                                @else
                                    {{ \Carbon\Carbon::parse($key->expires_at)->format('j M Y') }}
                                @endif
                            @else
                                <span class="text-muted">Never</span>
                            @endif
                        </td>
                        <td>
                            @if(($key->status ?? 'active') === 'active')
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                            @elseif(($key->status ?? '') === 'revoked')
                                <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Revoked</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($key->status ?? 'unknown') }}</span>
                            @endif
                        </td>
                        <td>
                            @if(($key->status ?? 'active') === 'active')
                            <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this API key? This cannot be undone.')">
                                @csrf
                                <input type="hidden" name="form_action" value="revoke">
                                <input type="hidden" name="key_id" value="{{ $key->id }}">
                                <button type="submit" class="btn atom-btn-outline-danger btn-sm" title="Revoke"><i class="fas fa-ban me-1"></i>Revoke</button>
                            </form>
                            @else
                            <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-key fa-3x mb-3 d-block"></i>
            No API keys yet. Generate one to access the research API.
        </div>
        @endif
    </div>
</div>

{{-- Generate New Key Modal --}}
<div class="modal fade" id="generateKeyModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="generate">
    <div class="modal-header"><h5 class="modal-title">Generate New API Key</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Key Name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
            <input type="text" class="form-control" name="name" required placeholder="e.g. My Research App">
            <div class="form-text">A descriptive name to identify this key.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Permissions <span class="badge bg-secondary ms-1">Optional</span></label>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="permissions[]" value="read" id="perm_read" checked><label class="form-check-label" for="perm_read">Read (collections, annotations, bibliographies) <span class="badge bg-secondary ms-1">Optional</span></label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="permissions[]" value="write" id="perm_write"><label class="form-check-label" for="perm_write">Write (create/update collections, annotations) <span class="badge bg-secondary ms-1">Optional</span></label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="permissions[]" value="search" id="perm_search"><label class="form-check-label" for="perm_search">Search (query the catalogue) <span class="badge bg-secondary ms-1">Optional</span></label></div>
        </div>
        <div class="mb-3">
            <label class="form-label">Expiry Date <span class="badge bg-secondary ms-1">Optional</span></label>
            <input type="date" class="form-control" name="expires_at">
            <div class="form-text">Leave empty for no expiration.</div>
        </div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-key me-1"></i>Generate Key</button></div>
    </form>
</div></div></div>
@endsection
