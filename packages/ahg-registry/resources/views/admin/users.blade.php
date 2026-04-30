{{--
  Registry Admin — User Approval
  Cloned from PSIS adminUsersSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('User Approval') . ' — ' . __('Admin'))
@section('body-class', 'registry registry-admin-users')

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item active">{{ __('User Approval') }}</li>
  </ol>
</nav>

<h1 class="h3 mb-4">{{ __('User Approval') }}</h1>

{{-- Pending users --}}
<div class="card mb-4">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span><i class="fas fa-clock me-1 text-warning"></i> {{ __('Pending Approval') }}</span>
    <span class="badge bg-warning text-dark">{{ $pendingUsers->count() }}</span>
  </div>
  @if($pendingUsers->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Registered') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($pendingUsers as $u)
          <tr>
            <td>{{ $u->username ?? '—' }}</td>
            <td>{{ $u->email ?? '' }}</td>
            <td><small class="text-muted">{{ !empty($u->created_at) ? date('M j, Y H:i', strtotime($u->created_at)) : '—' }}</small></td>
            <td class="text-end">
              @if(Route::has('registry.admin.users.post'))
                <form method="post" action="{{ route('registry.admin.users.post') }}" class="d-inline-flex align-items-center gap-2">
                  @csrf
                  <input type="hidden" name="user_id" value="{{ (int) $u->id }}">
                  <input type="hidden" name="form_action" value="approve">
                  <div class="form-check form-check-inline mb-0">
                    <input class="form-check-input" type="checkbox" name="make_admin" value="1" id="admin-{{ (int) $u->id }}">
                    <label class="form-check-label small" for="admin-{{ (int) $u->id }}">{{ __('Admin') }}</label>
                  </div>
                  <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> {{ __('Approve') }}</button>
                </form>
                <form method="post" action="{{ route('registry.admin.users.post') }}" class="d-inline" onsubmit="return confirm('Reject and delete this user account?');">
                  @csrf
                  <input type="hidden" name="user_id" value="{{ (int) $u->id }}">
                  <input type="hidden" name="form_action" value="reject">
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i> {{ __('Reject') }}</button>
                </form>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @else
    <div class="card-body text-center text-muted py-4">
      <i class="fas fa-check-circle fa-2x mb-2"></i>
      <p class="mb-0">{{ __('No pending registrations.') }}</p>
    </div>
  @endif
</div>

{{-- Recent active users --}}
<div class="card">
  <div class="card-header fw-semibold">
    <i class="fas fa-users me-1 text-success"></i> {{ __('Recent Active Users') }}
  </div>
  @if($activeUsers->isNotEmpty())
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('ID') }}</th>
            <th>{{ __('Name') }}</th>
            <th>{{ __('Email') }}</th>
            <th>{{ __('Last login') }}</th>
            <th class="text-end">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($activeUsers as $u)
          <tr>
            <td>{{ (int) $u->id }}</td>
            <td>{{ $u->username ?? '—' }}</td>
            <td>{{ $u->email ?? '' }}</td>
            <td><small class="text-muted">{{ !empty($u->last_login_at) ? date('M j, Y H:i', strtotime($u->last_login_at)) : '—' }}</small></td>
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
    <div class="card-body text-center text-muted py-4">
      <p class="mb-0">{{ __('No active users found.') }}</p>
    </div>
  @endif
</div>
@endsection
