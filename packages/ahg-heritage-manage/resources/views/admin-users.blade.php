@extends('theme::layouts.1col')
@section('title', 'User Management')
@section('body-class', 'admin heritage')

@php
$users = $userData['users'] ?? [];
$total = $userData['total'] ?? 0;
$page = $userData['page'] ?? 1;
$pages = $userData['pages'] ?? 1;
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._admin-sidebar')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-users me-2"></i>User Management</h1>

    <!-- Search -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form method="get" class="row g-3">
          <div class="col-md-5">
            <input type="text" class="form-control" name="search" placeholder="{{ __('Search by username or email...') }}" value="{{ request('search', '') }}">
          </div>
          <div class="col-md-4">
            <select class="form-select" name="trust_level">
              <option value="">{{ __('All Trust Levels') }}</option>
              @foreach($trustLevels ?? [] as $level)
              <option value="{{ $level->code }}" {{ request('trust_level') === $level->code ? 'selected' : '' }}>{{ $level->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3"><button type="submit" class="btn atom-btn-secondary w-100"><i class="fas fa-search me-2"></i>{{ __('Search') }}</button></div>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Users') }}</h5>
        <span class="badge bg-secondary">{{ number_format($total) }} total</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr><th>{{ __('Username') }}</th><th>{{ __('Email') }}</th><th>{{ __('Trust Level') }}</th><th>{{ __('Status') }}</th><th>{{ __('Joined') }}</th><th></th></tr>
            </thead>
            <tbody>
              @forelse($users as $user)
              <tr>
                <td><strong>{{ $user->username ?? 'N/A' }}</strong></td>
                <td>{{ $user->email ?? '' }}</td>
                <td>@if($user->trust_name)<span class="badge bg-info">{{ $user->trust_name }}</span>@else<span class="badge bg-secondary">{{ __('None') }}</span>@endif</td>
                <td>@if($user->active)<span class="badge bg-success">{{ __('Active') }}</span>@else<span class="badge bg-secondary">{{ __('Inactive') }}</span>@endif</td>
                <td><small class="text-muted">{{ date('Y-m-d', strtotime($user->created_at)) }}</small></td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#trustModal" data-user-id="{{ $user->id }}" data-username="{{ $user->username }}"><i class="fas fa-shield-alt"></i></button>
                </td>
              </tr>
              @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
      @if($pages > 1)
      <div class="card-footer bg-transparent">
        <nav><ul class="pagination mb-0 justify-content-center">
          @for($i = 1; $i <= min($pages, 10); $i++)
          <li class="page-item {{ $i == $page ? 'active' : '' }}">
            <a class="page-link" href="?page={{ $i }}&search={{ urlencode(request('search','')) }}&trust_level={{ urlencode(request('trust_level','')) }}">{{ $i }}</a>
          </li>
          @endfor
        </ul></nav>
      </div>
      @endif
    </div>

    <!-- Trust Level Modal -->
    <div class="modal fade" id="trustModal" tabindex="-1">
      <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">{{ __('Assign Trust Level') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="post" action="{{ route('heritage.admin-users') }}">@csrf
          <div class="modal-body">
            <input type="hidden" name="user_id" id="modal_user_id">
            <p>Assigning trust level to: <strong id="modal_username"></strong></p>
            <div class="mb-3">
              <label for="trust_level_id" class="form-label">Trust Level <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select class="form-select" name="trust_level_id" id="trust_level_id" required>
                @foreach($trustLevels ?? [] as $level)
                <option value="{{ $level->id }}">{{ $level->name }} (Level {{ $level->level }})</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3"><label for="expires_at" class="form-label">Expires At <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="date" class="form-control" name="expires_at" id="expires_at"></div>
            <div class="mb-3"><label for="notes" class="form-label">Notes <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea class="form-control" name="notes" id="notes" rows="2"></textarea></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button type="submit" class="btn atom-btn-secondary">{{ __('Assign Trust Level') }}</button></div>
        </form>
      </div></div>
    </div>
    <script>document.getElementById('trustModal')?.addEventListener('show.bs.modal',function(e){var b=e.relatedTarget;document.getElementById('modal_user_id').value=b.getAttribute('data-user-id');document.getElementById('modal_username').textContent=b.getAttribute('data-username');});</script>
  </div>
</div>
@endsection
