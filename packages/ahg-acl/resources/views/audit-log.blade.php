@extends('theme::layouts.2col')

@section('title', 'Audit Trail')
@section('body-class', 'admin audit-log')

@section('sidebar')
  <section id="facets">
    <div class="sidebar-lowering">
      <h3>{{ __('Filter Audit Logs') }}</h3>
      <form method="get" action="{{ route('acl.audit-log') }}">
        <div class="mb-3">
          <label class="form-label">{{ __('Action Type') }}</label>
          <select name="filter_action" class="form-select form-select-sm">
            <option value="">{{ __('All Actions') }}</option>
            @foreach($actionTypes as $a)
              <option value="{{ $a }}" {{ ($filters['action'] ?? '') === $a ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('Entity Type') }}</label>
          <select name="entity_type" class="form-select form-select-sm">
            <option value="">{{ __('All Types') }}</option>
            @foreach($entityTypes as $e)
              <option value="{{ $e }}" {{ ($filters['object_type'] ?? '') === $e ? 'selected' : '' }}>{{ $e }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('Username') }}</label>
          <select name="username" class="form-select form-select-sm">
            <option value="">{{ __('All Users') }}</option>
            @foreach($usernames as $u)
              <option value="{{ $u }}" {{ ($filters['username'] ?? '') === $u ? 'selected' : '' }}>{{ $u }}</option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('From Date') }}</label>
          <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $filters['from_date'] ?? '' }}">
        </div>

        <div class="mb-3">
          <label class="form-label">{{ __('To Date') }}</label>
          <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $filters['to_date'] ?? '' }}">
        </div>

        <input type="hidden" name="limit" value="{{ $limit }}">

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-sm">{{ __('Apply Filters') }}</button>
          <a href="{{ route('acl.audit-log') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>

      <hr class="my-4">
      <h4>{{ __('Quick Links') }}</h4>
      <ul class="list-unstyled">
        <li><a href="{{ route('acl.audit-log', ['filter_action' => 'login']) }}">Authentication Log</a></li>
        <li><a href="{{ route('acl.audit-log', ['filter_action' => 'access_request']) }}">Security Access Log</a></li>
        @if(\Route::has('audit.statistics'))
          <li><a href="{{ route('audit.statistics') }}">Statistics Dashboard</a></li>
        @endif
        @if(\Route::has('audit.settings'))
          <li><a href="{{ route('audit.settings') }}">Settings</a></li>
        @endif
      </ul>
    </div>
  </section>
@endsection

@section('title-block')
  <h1>{{ __('Audit Trail') }}</h1>
@endsection

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">
      Showing {{ $pager['from'] }} to {{ $pager['to'] }} of {{ $pager['total'] }} results
    </span>
    <div class="d-flex align-items-center gap-2">
      {{-- Page-size switch --}}
      <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Page size') }}">
        @foreach([25, 50, 100, 250] as $sz)
          <a href="{{ route('acl.audit-log', array_merge(request()->except(['page','limit']), ['limit' => $sz])) }}"
             class="btn btn-outline-secondary {{ $limit == $sz ? 'active' : '' }}">{{ $sz }}</a>
        @endforeach
      </div>
      <div class="btn-group btn-group-sm">
        <a href="{{ route('acl.audit-log', array_merge(request()->except(['page']), ['format' => 'csv'])) }}" class="btn btn-outline-secondary">Export CSV</a>
        <a href="{{ route('acl.audit-log', array_merge(request()->except(['page']), ['format' => 'json'])) }}" class="btn btn-outline-secondary">Export JSON</a>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead class="table-light">
        <tr>
          <th>{{ __('Date/Time') }}</th>
          <th>{{ __('User') }}</th>
          <th>{{ __('Action') }}</th>
          <th>{{ __('Entity') }}</th>
          <th>{{ __('Object ID') }}</th>
          <th>{{ __('IP') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($entries as $log)
          @php
            $badge = match(strtolower($log->action_category ?? 'access')) {
              'security' => 'bg-danger',
              'admin'    => 'bg-warning text-dark',
              'auth'     => 'bg-primary',
              'access'   => 'bg-info text-dark',
              default    => 'bg-secondary',
            };
          @endphp
          <tr>
            <td><small>{{ $log->created_at }}</small></td>
            <td>{{ $log->display_name ?? $log->user_name ?? 'Anonymous' }}</td>
            <td>
              <span class="badge {{ $badge }}">{{ $log->action }}</span>
            </td>
            <td>{{ $log->object_type ?? '—' }}</td>
            <td>{{ $log->object_id ?? '—' }}</td>
            <td><small><code>{{ $log->ip_address ?? '—' }}</code></small></td>
            <td class="text-end">
              @if(!empty($log->details))
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#auditDetailModal-{{ $log->id }}" title="{{ __('View Details') }}">
                  <i class="fas fa-eye"></i>
                </button>
                <div class="modal fade" id="auditDetailModal-{{ $log->id }}" tabindex="-1">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Audit entry #{{ $log->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <pre class="bg-light p-3 rounded small">{{ is_string($log->details) ? $log->details : json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                        @if($log->user_agent)
                          <hr><small class="text-muted">{{ __('User Agent') }}</small>
                          <div><code class="small">{{ $log->user_agent }}</code></div>
                        @endif
                      </div>
                    </div>
                  </div>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">No audit log entries found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($pager['last_page'] > 1)
    <nav>
      <ul class="pagination pagination-sm justify-content-center">
        @if($pager['current_page'] > 1)
          <li class="page-item">
            <a class="page-link" href="{{ route('acl.audit-log', array_merge(request()->except(['page']), ['page' => $pager['current_page'] - 1])) }}">&laquo;</a>
          </li>
        @endif

        @php
          $start = max(1, $pager['current_page'] - 3);
          $end = min($pager['last_page'], $pager['current_page'] + 3);
        @endphp
        @for($i = $start; $i <= $end; $i++)
          <li class="page-item {{ $i === $pager['current_page'] ? 'active' : '' }}">
            <a class="page-link" href="{{ route('acl.audit-log', array_merge(request()->except(['page']), ['page' => $i])) }}">{{ $i }}</a>
          </li>
        @endfor

        @if($pager['current_page'] < $pager['last_page'])
          <li class="page-item">
            <a class="page-link" href="{{ route('acl.audit-log', array_merge(request()->except(['page']), ['page' => $pager['current_page'] + 1])) }}">&raquo;</a>
          </li>
        @endif
      </ul>
    </nav>
  @endif
@endsection
