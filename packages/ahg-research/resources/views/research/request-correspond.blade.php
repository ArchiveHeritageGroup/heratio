{{-- Request Correspondence - Migrated from AtoM --}}
@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'workspace'])@endsection
@section('title', 'Request Correspondence')
@section('content')
<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li><li class="breadcrumb-item active">Correspondence</li></ol></nav>
<h1 class="h2 mb-4"><i class="fas fa-envelope text-primary me-2"></i>Request Correspondence</h1>
<div class="row"><div class="col-md-8">
<div class="card mb-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff">Messages</div>
    <div class="card-body">
        @forelse($messages ?? [] as $msg)
        <div class="d-flex mb-3 {{ ($msg->sender_type ?? '') === 'staff' ? '' : 'flex-row-reverse' }}">
            <div class="card {{ ($msg->sender_type ?? '') === 'staff' ? 'bg-light' : 'bg-primary text-white' }}" style="max-width:80%;">
                <div class="card-body py-2 px-3">
                    <p class="mb-1">{{ e($msg->content ?? '') }}</p>
                    <small class="{{ ($msg->sender_type ?? '') === 'staff' ? 'text-muted' : 'text-white-50' }}">{{ e($msg->sender_name ?? '') }} &middot; {{ $msg->created_at ?? '' }}</small>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-4 text-muted">No messages yet.</div>
        @endforelse
    </div>
    <div class="card-footer">
        <form method="POST" class="d-flex gap-2">@csrf <input type="hidden" name="request_id" value="{{ $request_id ?? 0 }}">
            <input type="text" name="message" class="form-control" placeholder="Type a message..." required>
            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
        </form>
    </div>
</div>
</div><div class="col-md-4">
<div class="card"><div class="card-header"><h6 class="mb-0">Request Details</h6></div><div class="card-body">
    <dl class="row mb-0 small">
        <dt class="col-sm-5">Request #</dt><dd class="col-sm-7">{{ $requestDetail->id ?? '' }}</dd>
        <dt class="col-sm-5">Status</dt><dd class="col-sm-7"><span class="badge bg-{{ match($requestDetail->status ?? '') { 'approved' => 'success', 'denied' => 'danger', 'pending' => 'warning', default => 'secondary' } }}">{{ ucfirst($requestDetail->status ?? '') }}</span></dd>
        <dt class="col-sm-5">Type</dt><dd class="col-sm-7">{{ ucfirst(str_replace('_', ' ', $requestDetail->request_type ?? '')) }}</dd>
        <dt class="col-sm-5">Created</dt><dd class="col-sm-7">{{ $requestDetail->created_at ?? '' }}</dd>
    </dl>
</div></div>
</div></div>
@endsection