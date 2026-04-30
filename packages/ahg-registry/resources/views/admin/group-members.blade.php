{{--
  Registry Admin — Group Members
  Cloned from PSIS adminGroupMembersSuccess.php.
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  This file is part of Heratio. AGPL-3.0-or-later.
--}}
@extends('ahg-registry::layouts.registry')

@section('title', __('Group Members') . ' — ' . ($group->name ?? ''))
@section('body-class', 'registry registry-admin-group-members')

@php
  $gid = (int) ($group->id ?? 0);
  $items = $members instanceof \Illuminate\Support\Collection ? $members : collect($members ?? []);
  $total = $items->count();
  $page = (int) request('page', 1);
  $limit = 50;
@endphp

@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.index') }}">{{ __('Registry') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.dashboard') }}">{{ __('Admin') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('registry.admin.groups') }}">{{ __('Groups') }}</a></li>
    @if(Route::has('registry.admin.groupEdit'))
      <li class="breadcrumb-item"><a href="{{ route('registry.admin.groupEdit', ['id' => $gid]) }}">{{ $group->name ?? '' }}</a></li>
    @endif
    <li class="breadcrumb-item active">{{ __('Members') }}</li>
  </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h1 class="h3 mb-1">{{ __('Members') }}: {{ $group->name ?? '' }}</h1>
    <span class="text-muted">{{ number_format($total) }} {{ __('members') }}</span>
  </div>
  <div class="btn-group btn-group-sm">
    @if(Route::has('registry.admin.groupEdit'))
      <a href="{{ route('registry.admin.groupEdit', ['id' => $gid]) }}" class="btn btn-outline-secondary"><i class="fas fa-edit me-1"></i>{{ __('Edit Group') }}</a>
    @endif
    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal"><i class="fas fa-user-plus me-1"></i>{{ __('Add Member') }}</button>
    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#emailModal"><i class="fas fa-envelope me-1"></i>{{ __('Email All') }}</button>
  </div>
</div>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check me-1"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row g-2 mb-3">
  <div class="col-md-6">
    @if(Route::has('registry.admin.groupMembers'))
    <form method="get" action="{{ route('registry.admin.groupMembers', ['id' => $gid]) }}">
      <div class="input-group input-group-sm">
        <input type="text" class="form-control" name="q" value="{{ e(request('q', '')) }}" placeholder="{{ __('Search by name or email...') }}">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
      </div>
    </form>
    @endif
  </div>
  <div class="col-md-3">
    <select class="form-select form-select-sm" name="status">
      <option value="">{{ __('All statuses') }}</option>
      <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>{{ __('Active') }}</option>
      <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
    </select>
  </div>
</div>

@if($items->isNotEmpty())
<div class="table-responsive">
  <table class="table table-hover table-striped align-middle">
    <thead class="table-light">
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Email') }}</th>
        <th class="text-center">{{ __('Role') }}</th>
        <th class="text-center">{{ __('Status') }}</th>
        <th>{{ __('Joined') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($items as $m)
      <tr class="{{ empty($m->is_active) ? 'table-secondary' : '' }}">
        <td>{{ $m->name ?? '—' }}</td>
        <td><a href="mailto:{{ $m->email ?? '' }}">{{ $m->email ?? '' }}</a></td>
        <td class="text-center">
          @php
            $roles = ['organizer' => 'Organizer', 'co_organizer' => 'Co-organizer', 'speaker' => 'Speaker', 'sponsor' => 'Sponsor', 'member' => 'Member'];
            $mRole = $m->role ?? 'member';
          @endphp
          <span class="badge bg-secondary">{{ __($roles[$mRole] ?? ucfirst($mRole)) }}</span>
        </td>
        <td class="text-center">
          @if(!empty($m->is_active))
            <span class="badge bg-success">{{ __('Active') }}</span>
          @else
            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
          @endif
        </td>
        <td><small class="text-muted">{{ !empty($m->joined_at) ? date('j M Y', strtotime($m->joined_at)) : (!empty($m->created_at) ? date('j M Y', strtotime($m->created_at)) : '—') }}</small></td>
        <td class="text-end">
          @if(Route::has('registry.admin.groupMembers.post'))
            <form method="post" action="{{ route('registry.admin.groupMembers.post', ['id' => $gid]) }}" class="d-inline">
              @csrf
              <input type="hidden" name="form_action" value="toggle_active">
              <input type="hidden" name="member_id" value="{{ (int) $m->id }}">
              <button type="submit" class="btn btn-sm btn-outline-{{ !empty($m->is_active) ? 'warning' : 'success' }}" title="{{ !empty($m->is_active) ? __('Deactivate') : __('Activate') }}">
                <i class="fas fa-{{ !empty($m->is_active) ? 'pause' : 'play' }}"></i>
              </button>
            </form>
            <form method="post" action="{{ route('registry.admin.groupMembers.post', ['id' => $gid]) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove this member?') }}');">
              @csrf
              <input type="hidden" name="form_action" value="remove">
              <input type="hidden" name="member_id" value="{{ (int) $m->id }}">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove') }}">
                <i class="fas fa-trash"></i>
              </button>
            </form>
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
  <p class="text-muted">{{ __('No members found.') }}</p>
</div>
@endif

<div class="modal fade" id="addMemberModal" tabindex="-1">
  <div class="modal-dialog">
    @if(Route::has('registry.admin.groupMembers.post'))
    <form method="post" action="{{ route('registry.admin.groupMembers.post', ['id' => $gid]) }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>{{ __('Add Member') }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="form_action" value="add">
          <div class="mb-3">
            <label class="form-label">{{ __('Email') }} <span class="text-danger">*</span></label>
            <input type="email" class="form-control" name="new_email" required>
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Name') }}</label>
            <input type="text" class="form-control" name="new_name">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Role') }}</label>
            <select class="form-select" name="new_role">
              <option value="member">{{ __('Member') }}</option>
              <option value="co_organizer">{{ __('Co-organizer') }}</option>
              <option value="organizer">{{ __('Organizer') }}</option>
              <option value="speaker">{{ __('Speaker') }}</option>
              <option value="sponsor">{{ __('Sponsor') }}</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-primary"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button>
        </div>
      </div>
    </form>
    @endif
  </div>
</div>

<div class="modal fade" id="emailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    @if(Route::has('registry.admin.groupEmail'))
    <form method="post" action="{{ route('registry.admin.groupEmail', ['id' => $gid]) }}">
      @csrf
      <div class="modal-content">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>{{ __('Email All Active Members') }}</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small">
            <i class="fas fa-info-circle me-1"></i>
            {{ __('This will send an email to all active members of this group.') }}
            <strong>{{ (int) ($group->member_count ?? 0) }} {{ __('recipients') }}</strong>.
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Subject') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="email_subject" required placeholder="{{ __('e.g., Next meeting reminder...') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">{{ __('Message') }} <span class="text-danger">*</span></label>
            <textarea class="form-control" name="email_body" rows="8" required placeholder="{{ __('Type your message here. Plain text — line breaks are preserved.') }}"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="submit" class="btn btn-success" onclick="return confirm('{{ __('Send email to all active members?') }}');"><i class="fas fa-paper-plane me-1"></i>{{ __('Send Email') }}</button>
        </div>
      </div>
    </form>
    @endif
  </div>
</div>
@endsection
