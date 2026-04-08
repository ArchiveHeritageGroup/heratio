@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'profile'])@endsection
@section('title', 'API Keys')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">API Keys</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h2"><i class="fas fa-key text-primary me-2"></i>API Keys</h1>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateKeyModal"><i class="fas fa-plus me-1"></i>Generate Key</button>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    API keys allow you to access your research data programmatically. Keep your keys secure and never share them publicly.
    <br><strong>API Base URL:</strong> <code>{{ url('/api/research') }}</code>
</div>

<div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Your API Keys</h5>
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
                            <code class="text-muted">{{ e(substr($key->api_key ?? '********', 0, 8)) }}...</code>
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
                            @if($key->is_active ?? true)
                                @if(($key->expires_at ?? null) && strtotime($key->expires_at) < time())
                                    <span class="badge bg-warning">Expired</span>
                                @else
                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                @endif
                            @else
                                <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Revoked</span>
                            @endif
                        </td>
                        <td>
                            @if($key->is_active ?? true)
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

{{-- API Documentation --}}
<div class="card mt-4">
    <div class="card-header"><h5 class="mb-0">API Documentation</h5></div>
    <div class="card-body">
        <h6>Authentication</h6>
        <p>Include your API key in the <code>X-API-Key</code> header or as an <code>api_key</code> query parameter.</p>
        <pre class="bg-light p-3 rounded"><code>curl -H "X-API-Key: YOUR_API_KEY" {{ url('/api/research/profile') }}</code></pre>

        <h6 class="mt-4">Available Endpoints</h6>
        <table class="table table-sm">
            <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td><span class="badge bg-success">GET</span></td><td>/profile</td><td>Get your researcher profile</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/projects</td><td>List your projects</td></tr>
                <tr><td><span class="badge bg-primary">POST</span></td><td>/projects</td><td>Create a project</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/collections</td><td>List your evidence sets</td></tr>
                <tr><td><span class="badge bg-primary">POST</span></td><td>/collections</td><td>Create an evidence set</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/searches</td><td>List saved searches</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/bookings</td><td>List bookings</td></tr>
                <tr><td><span class="badge bg-primary">POST</span></td><td>/bookings</td><td>Create a booking</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/bibliographies</td><td>List bibliographies</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/annotations</td><td>List annotations</td></tr>
                <tr><td><span class="badge bg-success">GET</span></td><td>/stats</td><td>Get your usage statistics</td></tr>
            </tbody>
        </table>
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
        <div class="alert alert-warning mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>The API key will only be shown once after generation. Make sure to copy it immediately.
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-key me-1"></i>Generate</button></div>
    </form>
</div></div></div>
@endsection
