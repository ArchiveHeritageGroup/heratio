{{-- Password Reset - Migrated from AtoM --}}
@extends('theme::layouts.1col')
@section('title', 'Set New Password')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
<div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-key me-2"></i>Set New Password</h5></div>
<div class="card-body">
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST">@csrf
        <input type="hidden" name="token" value="{{ $token ?? '' }}">
        <div class="mb-3"><label class="form-label">New Password <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="password" name="password" class="form-control" required minlength="8"></div>
        <div class="mb-3"><label class="form-label">Confirm Password <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="password" name="password_confirmation" class="form-control" required></div>
        <button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-save me-1"></i>Reset Password</button>
    </form>
</div></div>
</div></div>
@endsection