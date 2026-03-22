@extends('theme::layouts.1col')
@section('title', 'Two-Factor Verify')
@section('content')
<div class="container py-4">
<div class="row justify-content-center"><div class="col-md-5"><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication</h5></div><div class="card-body"><p>Enter your authentication code to continue.</p><form method="post" action="{{ route("acl.verify-2fa") }}">@csrf<div class="mb-3"><label class="form-label">Code <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="code" class="form-control text-center fs-4" maxlength="6" required autofocus></div><button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-check me-1"></i>Verify</button></form></div></div></div></div>
</div>
@endsection
