{{--
  Registry Admin — Edit User
  Cloned from PSIS adminUserEditSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Edit User') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-user-edit')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.users') }}">{{ __('Users') }}</a></li>
    <li class="breadcrumb-item active">{{ $user->username ?? 'User #' . $id }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ __('Edit User') }}: {{ $user->username ?? 'User #' . $id }}</h1>

<form method="post" action="{{ route('registry.admin.userEdit', ['id' => $id]) }}">
  @csrf
  @method('PUT')

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Account Information') }}</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">{{ __('Username') }}</label>
          <input type="text" class="form-control" name="username" value="{{ old('username', $user->username ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Email') }}</label>
          <input type="email" class="form-control" name="email" value="{{ old('email', $user->email ?? '') }}" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Password') }}</label>
          <input type="password" class="form-control" name="password" placeholder="{{ __('Leave blank to keep current') }}">
        </div>
        <div class="col-md-6">
          <label class="form-label">{{ __('Confirm Password') }}</label>
          <input type="password" class="form-control" name="password_confirmation">
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold">{{ __('Status') }}</div>
    <div class="card-body">
      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" name="active" value="1" id="active" {{ ($user->active ?? 0) ? 'checked' : '' }}>
        <label class="form-check-label" for="active">{{ __('Active') }}</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_admin" value="1" id="is_admin" {{ ($user->is_admin ?? 0) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_admin">{{ __('Administrator') }}</label>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
    <a href="{{ route('registry.admin.users') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  </div>
</form>
@endsection
