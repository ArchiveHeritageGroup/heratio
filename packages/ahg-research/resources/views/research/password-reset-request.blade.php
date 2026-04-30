{{-- Password Reset Request - Migrated from AtoM --}}
@extends('theme::layouts.1col')
@section('title', 'Reset Password')
@section('content')
<div class="row justify-content-center"><div class="col-md-6">
<div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0"><i class="fas fa-key me-2"></i>{{ __('Reset Your Password') }}</h5></div>
<div class="card-body">
<p>Enter your email address and we will send you a link to reset your password.</p>
    <form method="POST">@csrf
        <div class="mb-3"><label class="form-label">Email Address <span class="text-danger">*</span> <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="email" name="email" class="form-control" required></div>
        <button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-paper-plane me-1"></i>{{ __('Send Reset Link') }}</button>
    </form>
    <div class="text-center mt-3"><a href="{{ route('login') }}">Back to Login</a></div>
</div></div>
</div></div>
@endsection