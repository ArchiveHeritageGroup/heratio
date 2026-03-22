@extends('theme::layouts.1col')

@section('title', 'Audit statistics')
@section('body-class', 'admin audit statistics')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-chart-bar me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Audit statistics</h1>
      <span class="small text-muted">Last {{ $days }} days</span>
    </div>
  </div>

  {{-- Time period selector --}}
  <div class="mb-4">
    <div class="btn-group" role="group" aria-label="Time period">
      <a href="{{ route('audit.statistics', ['days' => 7]) }}"
         class="btn btn-sm {{ $days === 7 ? 'atom-btn-white' : 'atom-btn-white' }}">
        7 days
      </a>
      <a href="{{ route('audit.statistics', ['days' => 30]) }}"
         class="btn btn-sm {{ $days === 30 ? 'atom-btn-white' : 'atom-btn-white' }}">
        30 days
      </a>
      <a href="{{ route('audit.statistics', ['days' => 90]) }}"
         class="btn btn-sm {{ $days === 90 ? 'atom-btn-white' : 'atom-btn-white' }}">
        90 days
      </a>
    </div>
  </div>

  {{-- Stat cards --}}
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card border-secondary h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-secondary">Total Actions</h5>
          <p class="display-6 fw-bold mb-0">{{ number_format($totalActions) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-success h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-success">Created</h5>
          <p class="display-6 fw-bold mb-0">{{ number_format($createdCount) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-info h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-info">Updated</h5>
          <p class="display-6 fw-bold mb-0">{{ number_format($updatedCount) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-danger h-100">
        <div class="card-body text-center">
          <h5 class="card-title text-danger">Deleted</h5>
          <p class="display-6 fw-bold mb-0">{{ number_format($deletedCount) }}</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Most Active Users --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-users me-2"></i>Most Active Users</h5>
    </div>
    <div class="card-body p-0">
      @if($mostActiveUsers->count())
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>User</th>
                <th class="text-end" style="width:150px">Action Count</th>
              </tr>
            </thead>
            <tbody>
              @foreach($mostActiveUsers as $user)
                <tr>
                  <td>{{ $user->username }}</td>
                  <td class="text-end">{{ number_format($user->action_count) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted p-3 mb-0">No user activity in the selected period.</p>
      @endif
    </div>
  </div>

  {{-- Recent Failed Actions --}}
  <div class="card mb-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Recent Failed Actions</h5>
    </div>
    <div class="card-body p-0">
      @if($recentFailed->count())
        <div class="table-responsive">
          <table class="table table-bordered table-striped mb-0">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($recentFailed as $entry)
                <tr>
                  <td>{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d H:i:s') : '' }}</td>
                  <td>{{ $entry->username ?? '' }}</td>
                  <td>
                    @php
                      $actionVal = $entry->action ?? '';
                      $badgeClass = match($actionVal) {
                        'create' => 'bg-success',
                        'update' => 'bg-primary',
                        'delete' => 'bg-danger',
                        'failed' => 'bg-warning text-dark',
                        default => 'bg-secondary',
                      };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $actionVal }}</span>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-muted p-3 mb-0">No failed actions in the selected period.</p>
      @endif
    </div>
  </div>

  <a href="{{ route('audit.browse') }}" class="btn atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i> Back to Audit Trail
  </a>
@endsection
