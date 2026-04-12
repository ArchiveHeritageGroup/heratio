{{--
  Registry Admin — Institution Users
  Cloned from PSIS adminInstitutionUsersSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('theme::layouts.1col')

@section('title', __('Institution Users') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-institution-users')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.institutions') }}">{{ __('Institutions') }}</a></li>
    <li class="breadcrumb-item active">{{ $institution->name ?? 'Institution #' . $id }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">
    <i class="fas fa-users me-2"></i>{{ __('Users at :name', ['name' => $institution->name ?? '']) }}
  </h1>
  <span class="badge bg-secondary fs-6">{{ $users->count() }} {{ __('members') }}</span>
</div>

@if($users->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Username') }}</th>
        <th>{{ __('Email') }}</th>
        <th>{{ __('Role') }}</th>
        <th>{{ __('Joined') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($users as $u)
      <tr>
        <td><strong>{{ $u->username ?? '—' }}</strong></td>
        <td>{{ $u->email ?? '' }}</td>
        <td>
          @php
            $roleClass = match($u->role ?? '') {
              'owner' => 'bg-danger',
              'admin' => 'bg-primary',
              'member' => 'bg-secondary',
              default => 'bg-light text-dark border',
            };
          @endphp
          <span class="badge {{ $roleClass }}">{{ ucfirst($u->role ?? 'member') }}</span>
        </td>
        <td><small class="text-muted">{{ !empty($u->created_at) ? date('M j, Y', strtotime($u->created_at)) : '—' }}</small></td>
        <td class="text-end">
          @if(Route::has('registry.admin.userManage'))
            <a href="{{ route('registry.admin.userManage', ['id' => (int) $u->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-user-cog"></i></a>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@else
<div class="text-center py-5">
  <i class="fas fa-users fa-3x text-muted mb-3"></i>
  <h5>{{ __('No users in this institution') }}</h5>
</div>
@endif

<div class="mt-3">
  <a href="{{ route('registry.admin.institutions') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Institutions') }}</a>
</div>
@endsection
