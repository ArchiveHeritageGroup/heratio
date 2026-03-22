@extends('theme::layouts.1col')

@section('title', 'Audit trail')
@section('body-class', 'browse audit')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clipboard-list me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($total)
          Showing {{ number_format($total) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Audit trail</span>
    </div>
  </div>

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('audit.browse') }}" class="card mb-4">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-2">
          <label for="type" class="form-label small">Entity type</label>
          <select name="type" id="type" class="form-select form-select-sm">
            <option value="">All types</option>
            @foreach($entityTypes as $et)
              <option value="{{ $et }}" @selected($filters['type'] === $et)>{{ $et }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="action" class="form-label small">Action</label>
          <select name="action" id="action" class="form-select form-select-sm">
            <option value="">All actions</option>
            @foreach($actions as $a)
              <option value="{{ $a }}" @selected($filters['action'] === $a)>{{ $a }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="user" class="form-label small">User</label>
          <input type="text" name="user" id="user" class="form-control form-control-sm"
                 value="{{ $filters['user'] }}" placeholder="Username or email">
        </div>
        <div class="col-md-2">
          <label for="from" class="form-label small">From</label>
          <input type="date" name="from" id="from" class="form-control form-control-sm"
                 value="{{ $filters['from'] }}">
        </div>
        <div class="col-md-2">
          <label for="to" class="form-label small">To</label>
          <input type="date" name="to" id="to" class="form-control form-control-sm"
                 value="{{ $filters['to'] }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn atom-btn-outline-light btn-sm w-100">
            <i class="fas fa-filter me-1"></i> Filter
          </button>
        </div>
      </div>
    </div>
  </form>

  @if(count($entries))
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>Date</th>
            <th>User</th>
            <th>Action</th>
            @if($table === 'ahg_audit_log')
              <th>Entity type</th>
              <th>Entity title</th>
              <th>Status</th>
            @else
              <th>Table</th>
              <th>Record ID</th>
              <th>Field</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($entries as $entry)
            <tr>
              <td>
                <a href="{{ route('audit.show', $entry['id']) }}">
                  {{ $entry['created_at'] ? \Carbon\Carbon::parse($entry['created_at'])->format('Y-m-d H:i') : '' }}
                </a>
              </td>
              <td>{{ $entry['username'] ?? '' }}</td>
              <td>
                @php
                  $actionVal = $entry['action'] ?? '';
                  $badgeClass = match($actionVal) {
                    'create' => 'bg-success',
                    'update' => 'bg-primary',
                    'delete' => 'bg-danger',
                    default => 'bg-secondary',
                  };
                @endphp
                <span class="badge {{ $badgeClass }}">{{ $actionVal }}</span>
              </td>
              @if($table === 'ahg_audit_log')
                <td>{{ $entry['entity_type'] ?? '' }}</td>
                <td>{{ $entry['entity_title'] ?? '' }}</td>
                <td>
                  @if(!empty($entry['status']))
                    @php
                      $statusClass = match($entry['status']) {
                        'success' => 'bg-success',
                        'error', 'failed' => 'bg-danger',
                        default => 'bg-secondary',
                      };
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $entry['status'] }}</span>
                  @endif
                </td>
              @else
                <td>{{ $entry['table_name'] ?? '' }}</td>
                <td>{{ $entry['record_id'] ?? '' }}</td>
                <td>{{ $entry['field_name'] ?? '' }}</td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Simple pagination --}}
    @if($totalPages > 1)
      <nav aria-label="Audit pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item @if($page <= 1) disabled @endif">
            <a class="page-link" href="{{ route('audit.browse', array_merge($filters, ['page' => $page - 1])) }}">
              Previous
            </a>
          </li>
          @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
            <li class="page-item @if($i === $page) active @endif">
              <a class="page-link" href="{{ route('audit.browse', array_merge($filters, ['page' => $i])) }}">
                {{ $i }}
              </a>
            </li>
          @endfor
          <li class="page-item @if($page >= $totalPages) disabled @endif">
            <a class="page-link" href="{{ route('audit.browse', array_merge($filters, ['page' => $page + 1])) }}">
              Next
            </a>
          </li>
        </ul>
      </nav>
    @endif
  @endif
@endsection
