@extends('theme::layouts.1col')

@section('title', 'Error log')
@section('body-class', 'admin settings error-log')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-exclamation-circle me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Error log</h1>
      <span class="small text-muted">Showing {{ number_format($total) }} entries</span>
    </div>
  </div>


  {{-- Stats cards --}}
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="card border-danger h-100">
        <div class="card-body text-center">
          <h6 class="card-title text-danger mb-1">Open</h6>
          <p class="display-6 fw-bold mb-0">{{ number_format($openCount) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-success h-100">
        <div class="card-body text-center">
          <h6 class="card-title text-success mb-1">Resolved</h6>
          <p class="display-6 fw-bold mb-0">{{ number_format($resolvedCount) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-warning h-100">
        <div class="card-body text-center">
          <h6 class="card-title text-warning mb-1">Unread</h6>
          <p class="display-6 fw-bold mb-0">{{ number_format($unreadCount) }}</p>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="card border-info h-100">
        <div class="card-body text-center">
          <h6 class="card-title text-info mb-1">Today</h6>
          <p class="display-6 fw-bold mb-0">{{ number_format($todayCount) }}</p>
        </div>
      </div>
    </div>
  </div>

  {{-- Bulk actions --}}
  <div class="d-flex gap-2 mb-3">
    <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
      @csrf
      <button type="submit" name="mark_read" value="1" class="btn btn-sm atom-btn-white">
        <i class="fas fa-check-double me-1"></i> Mark All Read
      </button>
    </form>
    <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
      @csrf
      <button type="submit" name="resolve_all" value="1" class="btn btn-sm atom-btn-outline-success">
        <i class="fas fa-check-circle me-1"></i> Resolve All
      </button>
    </form>
    <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
      @csrf
      <input type="hidden" name="clear_days" value="30">
      <button type="submit" name="clear_old" value="1" class="btn btn-sm atom-btn-outline-danger">
        <i class="fas fa-trash me-1"></i> Clear Older Than 30 Days
      </button>
    </form>
  </div>

  {{-- Filter bar --}}
  <form method="GET" action="{{ route('settings.error-log') }}" class="card mb-4">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label for="status" class="form-label small">Status <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="status" id="status" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="open" @selected($filters['status'] === 'open')>Open</option>
            <option value="resolved" @selected($filters['status'] === 'resolved')>Resolved</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="level" class="form-label small">Level <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="level" id="level" class="form-select form-select-sm">
            <option value="">All levels</option>
            <option value="fatal" @selected($filters['level'] === 'fatal')>Fatal</option>
            <option value="error" @selected($filters['level'] === 'error')>Error</option>
            <option value="warning" @selected($filters['level'] === 'warning')>Warning</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="search" class="form-label small">Search <span class="badge bg-secondary ms-1">Optional</span></label>
          <input type="text" name="search" id="search" class="form-control form-control-sm"
                 value="{{ $filters['search'] }}" placeholder="Message, URL, or file...">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn atom-btn-outline-light btn-sm w-100">
            <i class="fas fa-filter me-1"></i> Filter
          </button>
        </div>
      </div>
    </div>
  </form>

  {{-- Error log table --}}
  @if($entries->count())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th style="width:140px">Date</th>
            <th style="width:80px">Level</th>
            <th style="width:60px">Code</th>
            <th>Message</th>
            <th style="width:80px">Status</th>
            <th style="width:160px">Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($entries as $entry)
            <tr class="{{ !$entry->is_read ? 'fw-bold' : '' }}">
              <td class="small">{{ \Carbon\Carbon::parse($entry->created_at)->format('Y-m-d H:i') }}</td>
              <td>
                @php
                  $levelClass = match($entry->level) {
                    'fatal' => 'bg-dark',
                    'error' => 'bg-danger',
                    'warning' => 'bg-warning text-dark',
                    default => 'bg-secondary',
                  };
                @endphp
                <span class="badge {{ $levelClass }}">{{ $entry->level }}</span>
              </td>
              <td>{{ $entry->status_code ?? '' }}</td>
              <td>
                <div class="text-truncate" style="max-width:400px" title="{{ $entry->message }}">
                  {{ Str::limit($entry->message, 120) }}
                </div>
                @if($entry->file)
                  <small class="text-muted">{{ Str::limit($entry->file, 80) }}:{{ $entry->line }}</small>
                @endif
              </td>
              <td>
                @if($entry->resolved_at)
                  <span class="badge bg-success">Resolved</span>
                @else
                  <span class="badge bg-danger">Open</span>
                @endif
              </td>
              <td>
                <div class="btn-group btn-group-sm">
                  @if($entry->resolved_at)
                    <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="reopen_id" value="{{ $entry->id }}">
                      <button type="submit" class="btn btn-sm atom-btn-white" title="Reopen">
                        <i class="fas fa-undo"></i>
                      </button>
                    </form>
                  @else
                    <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                      @csrf
                      <input type="hidden" name="resolve_id" value="{{ $entry->id }}">
                      <button type="submit" class="btn btn-sm atom-btn-outline-success" title="Resolve">
                        <i class="fas fa-check"></i>
                      </button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="delete_id" value="{{ $entry->id }}">
                    <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Delete"
                            onclick="return confirm('Delete this error entry?')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    {{-- Pagination --}}
    @if($totalPages > 1)
      <nav aria-label="Error log pagination">
        <ul class="pagination justify-content-center">
          <li class="page-item @if($page <= 1) disabled @endif">
            <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $page - 1])) }}">
              Previous
            </a>
          </li>
          @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
            <li class="page-item @if($i === $page) active @endif">
              <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $i])) }}">
                {{ $i }}
              </a>
            </li>
          @endfor
          <li class="page-item @if($page >= $totalPages) disabled @endif">
            <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $page + 1])) }}">
              Next
            </a>
          </li>
        </ul>
      </nav>
    @endif
  @else
    <div class="alert alert-info">No error log entries found.</div>
  @endif

  <a href="{{ route('settings.index') }}" class="btn atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i> Back to Settings
  </a>
@endsection
