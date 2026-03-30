@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-sync-alt me-2"></i>Researcher Access Renewal</h1>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Current Researcher Card</h5></div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Name</dt>
            <dd class="col-sm-8">{{ e($researcher->first_name ?? '') }} {{ e($researcher->last_name ?? '') }}</dd>

            <dt class="col-sm-4">Email</dt>
            <dd class="col-sm-8">{{ e($researcher->email ?? '-') }}</dd>

            <dt class="col-sm-4">Institution</dt>
            <dd class="col-sm-8">{{ e($researcher->institution ?? '-') }}</dd>

            <dt class="col-sm-4">Researcher ID</dt>
            <dd class="col-sm-8">#{{ $researcher->id }}</dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8">
                @if($researcher->status === 'approved')
                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                @elseif($researcher->status === 'expired')
                    <span class="badge bg-danger"><i class="fas fa-exclamation-circle me-1"></i>Expired</span>
                @else
                    <span class="badge bg-secondary">{{ ucfirst($researcher->status) }}</span>
                @endif
            </dd>

            <dt class="col-sm-4">Registration Date</dt>
            <dd class="col-sm-8">{{ $researcher->created_at ? \Carbon\Carbon::parse($researcher->created_at)->format('j F Y') : '-' }}</dd>

            <dt class="col-sm-4">Expiry Date</dt>
            <dd class="col-sm-8">
                @if($researcher->expires_at ?? null)
                    @if(\Carbon\Carbon::parse($researcher->expires_at)->isPast())
                        <span class="text-danger fw-bold">{{ \Carbon\Carbon::parse($researcher->expires_at)->format('j F Y') }} (Expired)</span>
                    @else
                        {{ \Carbon\Carbon::parse($researcher->expires_at)->format('j F Y') }}
                        <small class="text-muted">({{ \Carbon\Carbon::parse($researcher->expires_at)->diffForHumans() }})</small>
                    @endif
                @else
                    <span class="text-muted">Not set</span>
                @endif
            </dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-redo me-2"></i>Request Renewal</h5></div>
    <div class="card-body">
        <p class="text-muted">Submit a renewal request to extend your researcher access. An administrator will review your request.</p>
        <form method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Reason for Renewal <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea class="form-control" name="reason" rows="4" placeholder="Please describe why you need to renew your researcher access (e.g., ongoing research project, continued study...)"></textarea>
                <div class="form-text">Providing a reason helps administrators process your request faster.</div>
            </div>
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-paper-plane me-1"></i>Submit Renewal Request</button>
        </form>
    </div>
</div>
@endsection
