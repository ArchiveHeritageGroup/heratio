@extends('theme::layouts.1col')
@section('title', 'User Registration')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-user-plus me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('User Registration') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>{{ __('User Registration') }}</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Username <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="text" class="form-control" name="username" required></div><div class="col-md-6 mb-3"><label class="form-label">Email <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="email" class="form-control" name="email" required></div></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Password <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="password" class="form-control" name="password" required></div><div class="col-md-6 mb-3"><label class="form-label">Confirm Password <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="password" class="form-control" name="password_confirmation" required></div></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> {{ __('Cancel') }}</a></div>
  </form></div></div>
@endsection
