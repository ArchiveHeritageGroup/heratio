@extends('theme::layouts.1col')
@section('title', 'Setup Two-Factor')
@section('content')
<div class="container py-4">
<div class="row justify-content-center"><div class="col-md-6"><h1><i class="fas fa-mobile-alt me-2"></i>Setup Two-Factor Authentication</h1><div class="card"><div class="card-body"><p>Two-factor authentication is required for your clearance level.</p><form method="post" action="{{ route("acl.setup-2fa-store") }}">@csrf<div class="mb-3"><label class="form-label">Verification Code <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="code" class="form-control" placeholder="Enter 6-digit code" required></div><button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-check me-1"></i>Verify & Enable</button></form></div></div></div></div>
</div>
@endsection
