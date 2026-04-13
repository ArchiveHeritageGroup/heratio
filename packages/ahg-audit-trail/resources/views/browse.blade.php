{{--
  Audit Trail — Browse (cloned from ahgAuditTrailPlugin/browseSuccess.php)

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Licensed under the GNU AGPL v3 or later.
--}}
@extends('theme::layouts.2col')

@section('title', 'Audit Trail')
@section('body-class', 'browse audit-trail')

@section('title-block')
  <h1>Audit Trail</h1>
@endsection

@section('sidebar')
  <section id="facets">
    <div class="sidebar-lowering">
      <h3>Filter Audit Logs</h3>
      <form method="get" action="{{ route('audit.browse') }}">
        <div class="mb-3">
          <label class="form-label">Action Type</label>
          <select name="filter_action" class="form-select form-select-sm">
            <option value="">All Actions</option>
            @foreach($actionTypes as $value => $label)
              <option value="{{ $value }}" @selected(($currentFilters['action'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Entity Type</label>
          <select name="entity_type" class="form-select form-select-sm">
            <option value="">All Types</option>
            @foreach($entityTypes as $value => $label)
              <option value="{{ $value }}" @selected(($currentFilters['entity_type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Username</label>
          <select name="username" class="form-select form-select-sm">
            <option value="">All Users</option>
            @foreach($usernames as $username)
              <option value="{{ $username }}" @selected(($currentFilters['username'] ?? '') === $username)>{{ $username }}</option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">From Date</label>
          <input type="date" name="from_date" class="form-control form-control-sm" value="{{ $currentFilters['from_date'] ?? '' }}">
        </div>
        <div class="mb-3">
          <label class="form-label">To Date</label>
          <input type="date" name="to_date" class="form-control form-control-sm" value="{{ $currentFilters['to_date'] ?? '' }}">
        </div>
        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
          <a href="{{ route('audit.browse') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
        </div>
      </form>
      <hr class="my-4">
      <h4>Quick Links</h4>
      <ul class="list-unstyled">
        <li><a href="{{ route('audit.authentication') }}">Authentication Log</a></li>
        <li><a href="{{ route('audit.security-access') }}">Security Access Log</a></li>
        <li><a href="{{ route('audit.statistics') }}">Statistics Dashboard</a></li>
        <li><a href="{{ route('audit.settings') }}">Settings</a></li>
      </ul>
    </div>
  </section>
@endsection

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">
      Showing {{ $pager['from'] }} to {{ $pager['to'] }} of {{ $pager['total'] }} results
    </span>
    <div class="btn-group btn-group-sm">
      <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="btn btn-outline-secondary"><i class="fas fa-file-csv me-1"></i>Export CSV</a>
      <a href="{{ route('audit.export', array_merge(request()->query(), ['format' => 'json'])) }}" class="btn btn-outline-secondary"><i class="fas fa-file-code me-1"></i>Export JSON</a>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover table-sm">
      <thead class="table-light">
        <tr>
          <th>Date/Time</th>
          <th>User</th>
          <th>Action</th>
          <th>Entity</th>
          <th>Title</th>
          <th>IP</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pager['data'] as $log)
          @php
            $status = $log['status'] ?? 'success';
            $action = $log['action'] ?? '';
            $actionLabel = $log['action_label'] ?? ucfirst($action);
            $entityTypeLabel = $log['entity_type_label'] ?? ($log['entity_type'] ?? '');
            $badgeClass = match($action) {
              'create' => 'success',
              'update' => 'primary',
              'delete' => 'danger',
              default  => 'secondary',
            };
            $title = $log['entity_title'] ?? $log['entity_slug'] ?? '-';
            $titleDisplay = mb_substr((string) $title, 0, 40);
            $createdAt = $log['created_at'] ?? null;
            $createdDisplay = $createdAt ? \Carbon\Carbon::parse($createdAt)->format('Y-m-d H:i:s') : '';
          @endphp
          <tr class="{{ $status !== 'success' ? 'table-warning' : '' }}">
            <td><small>{{ $createdDisplay }}</small></td>
            <td>{{ $log['username'] ?? 'Anonymous' }}</td>
            <td><span class="badge bg-{{ $badgeClass }}">{{ $actionLabel }}</span></td>
            <td>{{ $entityTypeLabel }}</td>
            <td>{{ $titleDisplay }}</td>
            <td><small>{{ $log['ip_address'] ?? '-' }}</small></td>
            <td class="text-end">
              <div class="btn-group btn-group-sm">
                @if(in_array($action, ['update', 'create']) && (!empty($log['old_values']) || !empty($log['new_values'])))
                  <button type="button" class="btn btn-outline-warning btn-audit-compare" data-audit-id="{{ $log['id'] }}" title="Compare Changes">
                    <i class="fas fa-exchange-alt"></i>
                  </button>
                @endif
                <a href="{{ route('audit.show', $log['id']) }}" class="btn btn-outline-primary" title="View Details">
                  <i class="fas fa-eye"></i>
                </a>
              </div>
            </td>
          </tr>
        @endforeach
        @if($pager['total'] === 0)
          <tr><td colspan="7" class="text-center text-muted py-4">No audit log entries found.</td></tr>
        @endif
      </tbody>
    </table>
  </div>

  @if($pager['last_page'] > 1)
    <nav>
      <ul class="pagination pagination-sm justify-content-center">
        @if($pager['current_page'] > 1)
          <li class="page-item">
            <a class="page-link" href="{{ route('audit.browse', array_merge($currentFilters, ['page' => $pager['current_page'] - 1])) }}">&laquo;</a>
          </li>
        @endif

        @for($i = max(1, $pager['current_page'] - 3); $i <= min($pager['last_page'], $pager['current_page'] + 3); $i++)
          <li class="page-item {{ $i === $pager['current_page'] ? 'active' : '' }}">
            <a class="page-link" href="{{ route('audit.browse', array_merge($currentFilters, ['page' => $i])) }}">{{ $i }}</a>
          </li>
        @endfor

        @if($pager['current_page'] < $pager['last_page'])
          <li class="page-item">
            <a class="page-link" href="{{ route('audit.browse', array_merge($currentFilters, ['page' => $pager['current_page'] + 1])) }}">&raquo;</a>
          </li>
        @endif
      </ul>
    </nav>
  @endif

  @include('ahg-audit-trail::_compare-modal')
@endsection
