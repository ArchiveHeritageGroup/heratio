{{--
  Registry Admin — Manage User
  Cloned from PSIS adminUserManageSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Manage User') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-user-manage')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.users') }}">{{ __('Users') }}</a></li>
    <li class="breadcrumb-item active">{{ $user->username ?? 'User #' . $id }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ __('Manage User') }}: {{ $user->username ?? 'User #' . $id }}</h1>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Profile') }}</div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-sm-4 text-muted">{{ __('Username') }}</dt>
          <dd class="col-sm-8">{{ $user->username ?? '—' }}</dd>
          <dt class="col-sm-4 text-muted">{{ __('Email') }}</dt>
          <dd class="col-sm-8">{{ $user->email ?? '—' }}</dd>
          <dt class="col-sm-4 text-muted">{{ __('Status') }}</dt>
          <dd class="col-sm-8">
            @if(!empty($user->active))
              <span class="badge bg-success">{{ __('Active') }}</span>
            @else
              <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
            @endif
          </dd>
          <dt class="col-sm-4 text-muted">{{ __('Created') }}</dt>
          <dd class="col-sm-8">{{ !empty($user->created_at) ? date('M j, Y H:i', strtotime($user->created_at)) : '—' }}</dd>
        </dl>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold">{{ __('Actions') }}</div>
      <div class="card-body d-flex flex-column gap-2">
        @if(Route::has('registry.admin.userEdit'))
          <a href="{{ route('registry.admin.userEdit', ['id' => $id]) }}" class="btn btn-outline-primary"><i class="fas fa-user-edit me-1"></i>{{ __('Edit User') }}</a>
        @endif
        @if(Route::has('registry.admin.users.post'))
          <form method="post" action="{{ route('registry.admin.users.post') }}">
            @csrf
            <input type="hidden" name="user_id" value="{{ $id }}">
            <input type="hidden" name="form_action" value="toggle_admin">
            <button type="submit" class="btn btn-outline-warning w-100"><i class="fas fa-user-shield me-1"></i>{{ __('Toggle Admin') }}</button>
          </form>
          <form method="post" action="{{ route('registry.admin.users.post') }}" onsubmit="return confirm('Suspend this user?');">
            @csrf
            <input type="hidden" name="user_id" value="{{ $id }}">
            <input type="hidden" name="form_action" value="suspend">
            <button type="submit" class="btn btn-outline-danger w-100"><i class="fas fa-ban me-1"></i>{{ __('Suspend') }}</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="mt-3">
  <a href="{{ route('registry.admin.users') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Users') }}</a>
</div>
@endsection
