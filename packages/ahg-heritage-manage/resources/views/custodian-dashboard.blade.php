@extends('theme::layouts.1col')

@section('title', 'Heritage Custodian Dashboard')
@section('body-class', 'admin heritage custodian')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h1 class="mb-0"><i class="fas fa-hard-hat"></i> Custodian Dashboard</h1>
  </div>
  <p class="text-muted mb-4">Batch operations, activity monitoring, and contributor tracking</p>

  <div class="row">
    {{-- Sidebar --}}
    <div class="col-md-3">
      @include('ahg-heritage-manage::partials._admin-sidebar')

      {{-- Batch Jobs stats card in sidebar --}}
      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h6 class="mb-0"><i class="fas fa-tasks"></i> Batch Jobs</h6>
        </div>
        <div class="card-body p-0">
          <ul class="list-group list-group-flush">
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Running
              <span class="badge {{ $runningJobs > 0 ? 'bg-primary' : 'bg-secondary' }} rounded-pill">{{ $runningJobs }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Completed Today
              <span class="badge bg-success rounded-pill">{{ $completedToday }}</span>
            </li>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              Items This Month
              <span class="badge bg-info rounded-pill">{{ $itemsThisMonth }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>

    {{-- Main content --}}
    <div class="col-md-9">
      {{-- Quick Actions --}}
      <div class="row mb-4">
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div class="mb-3"><i class="fas fa-layer-group fa-2x text-primary"></i></div>
              <h6 class="card-title">Batch Operations</h6>
              <p class="card-text text-muted small">Run bulk updates, imports, and exports across collections</p>
              <a href="{{ route('heritage.custodian') }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-play me-1"></i> Launch
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div class="mb-3"><i class="fas fa-history fa-2x text-info"></i></div>
              <h6 class="card-title">Audit Trail</h6>
              <p class="card-text text-muted small">Review detailed change logs and user actions</p>
              <a href="{{ route('audit.browse') }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-search me-1"></i> View
              </a>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card shadow-sm h-100">
            <div class="card-body text-center py-4">
              <div class="mb-3"><i class="fas fa-key fa-2x text-warning"></i></div>
              <h6 class="card-title">Access Requests</h6>
              <p class="card-text text-muted small">Manage pending access and permission requests</p>
              <a href="{{ route('acl.access-requests') }}" class="btn btn-sm atom-btn-white">
                <i class="fas fa-clipboard-list me-1"></i> Review
              </a>
            </div>
          </div>
        </div>
      </div>

      {{-- Activity by Category --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Activity by Category (30 days)</h5>
        </div>
        <div class="card-body">
          @if($activityByCategory->isNotEmpty())
            <div class="d-flex flex-wrap gap-2">
              @php
                $actionColors = [
                  'create' => 'success',
                  'update' => 'primary',
                  'delete' => 'danger',
                  'view'   => 'info',
                  'browse' => 'info',
                  'index'  => 'info',
                  'search' => 'secondary',
                  'download' => 'warning',
                  'login'  => 'dark',
                  'logout' => 'dark',
                  'export' => 'warning',
                  'import' => 'success',
                  'access_request' => 'warning',
                ];
              @endphp
              @foreach($activityByCategory as $cat)
                @php $color = $actionColors[$cat->action] ?? 'secondary'; @endphp
                <span class="badge bg-{{ $color }} fs-6 py-2 px-3">
                  {{ ucfirst(str_replace('_', ' ', $cat->action)) }}
                  <span class="badge bg-light text-dark ms-1">{{ number_format($cat->total) }}</span>
                </span>
              @endforeach
            </div>
          @else
            <p class="text-muted mb-0">No activity recorded in the last 30 days.</p>
          @endif
        </div>
      </div>

      {{-- Top Contributors --}}
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Contributors (30 days)</h5>
        </div>
        <div class="card-body p-0">
          @if($topContributors->isNotEmpty())
            <table class="table table-bordered table-hover mb-0">
              <thead>
                <tr style="background:var(--ahg-primary);color:#fff">
                  <th style="width:60px;" class="text-center">#</th>
                  <th>Username</th>
                  <th class="text-end" style="width:150px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                @foreach($topContributors as $index => $contributor)
                  <tr>
                    <td class="text-center">
                      @if($index === 0)
                        <i class="fas fa-medal text-warning"></i>
                      @elseif($index === 1)
                        <i class="fas fa-medal text-secondary"></i>
                      @elseif($index === 2)
                        <i class="fas fa-medal" style="color:#cd7f32;"></i>
                      @else
                        <span class="text-muted">{{ $index + 1 }}</span>
                      @endif
                    </td>
                    <td>
                      <i class="fas fa-user-circle text-muted me-1"></i>
                      {{ $contributor->username }}
                    </td>
                    <td class="text-end">
                      <span class="badge bg-primary rounded-pill">{{ number_format($contributor->action_count) }}</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          @else
            <div class="p-3">
              <p class="text-muted mb-0">No contributor activity recorded in the last 30 days.</p>
            </div>
          @endif
        </div>
      </div>

      {{-- Recent Activity --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
          <h5 class="mb-0"><i class="fas fa-stream"></i> Recent Activity</h5>
          <span class="badge bg-secondary">Last 20 entries</span>
        </div>
        <div class="card-body p-0">
          @if($recentActivity->isNotEmpty())
            <div class="list-group list-group-flush">
              @php
                $actionBadgeColors = [
                  'create' => 'success',
                  'update' => 'primary',
                  'delete' => 'danger',
                  'view'   => 'info',
                  'browse' => 'info',
                  'index'  => 'info',
                  'search' => 'secondary',
                  'download' => 'warning',
                  'login'  => 'dark',
                  'logout' => 'dark',
                  'export' => 'warning',
                  'import' => 'success',
                  'access_request' => 'warning',
                ];
              @endphp
              @foreach($recentActivity as $entry)
                @php $badgeColor = $actionBadgeColors[$entry->action] ?? 'secondary'; @endphp
                <div class="list-group-item">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <span class="badge bg-{{ $badgeColor }} me-2">{{ ucfirst(str_replace('_', ' ', $entry->action)) }}</span>
                      @if($entry->username)
                        <strong>{{ $entry->username }}</strong>
                      @else
                        <span class="text-muted">Anonymous</span>
                      @endif
                      @if($entry->entity_title)
                        <span class="text-muted mx-1">&mdash;</span>
                        <span>{{ \Illuminate\Support\Str::limit($entry->entity_title, 60) }}</span>
                      @endif
                      @if($entry->entity_type)
                        <small class="text-muted ms-1">({{ $entry->entity_type }})</small>
                      @endif
                    </div>
                    <small class="text-muted text-nowrap ms-2" title="{{ $entry->created_at }}">
                      {{ \Carbon\Carbon::parse($entry->created_at)->diffForHumans() }}
                    </small>
                  </div>
                </div>
              @endforeach
            </div>
          @else
            <div class="p-3">
              <p class="text-muted mb-0">No recent activity recorded.</p>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
