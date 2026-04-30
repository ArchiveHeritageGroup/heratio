@extends('theme::layouts.1col')

@section('title', 'System Error Log')
@section('body-class', 'admin settings error-log')

@section('content')
  <h1><i class="fas fa-exclamation-triangle text-danger me-2"></i>System Error Log</h1>

  {{-- Stats Row --}}
  <div class="row mb-3">
    <div class="col-md-2">
      <div class="card border-danger">
        <div class="card-body text-center py-2">
          <div class="h4 mb-0 text-danger">{{ number_format($openCount) }}</div>
          <small class="text-muted">Open</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-success">
        <div class="card-body text-center py-2">
          <div class="h4 mb-0 text-success">{{ number_format($resolvedCount) }}</div>
          <small class="text-muted">Resolved</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card border-warning">
        <div class="card-body text-center py-2">
          <div class="h4 mb-0 text-warning">{{ number_format($unreadCount) }}</div>
          <small class="text-muted">Unread</small>
        </div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card">
        <div class="card-body text-center py-2">
          <div class="h4 mb-0">{{ number_format($todayCount) }}</div>
          <small class="text-muted">Today</small>
        </div>
      </div>
    </div>
    <div class="col-md-4 d-flex align-items-center justify-content-end gap-2">
      <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
        @csrf
        <input type="hidden" name="mark_read" value="1">
        <button type="submit" class="btn btn-sm btn-outline-secondary" {{ $unreadCount === 0 ? 'disabled' : '' }}>
          <i class="fas fa-eye me-1"></i>Mark All Read
        </button>
      </form>
      <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
        @csrf
        <input type="hidden" name="resolve_all" value="1">
        <button type="submit" class="btn btn-sm btn-outline-success" {{ $openCount === 0 ? 'disabled' : '' }} onclick="return confirm('Resolve all open errors?')">
          <i class="fas fa-check-double me-1"></i>Resolve All
        </button>
      </form>
      <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
        @csrf
        <input type="hidden" name="clear_old" value="1">
        <input type="hidden" name="clear_days" value="30">
        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete logs older than 30 days?')">
          <i class="fas fa-trash me-1"></i>Clear 30d+
        </button>
      </form>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card mb-3">
    <div class="card-body py-2">
      <form method="GET" action="{{ route('settings.error-log') }}" class="row g-2 align-items-center">
        <div class="col-auto">
          <select name="status" class="form-select form-select-sm">
            <option value="open" @selected($filters['status'] === 'open')>{{ __('Open') }}</option>
            <option value="resolved" @selected($filters['status'] === 'resolved')>{{ __('Resolved') }}</option>
            <option value="all" @selected($filters['status'] === 'all')>{{ __('All') }}</option>
          </select>
        </div>
        <div class="col-auto">
          <select name="level" class="form-select form-select-sm">
            <option value="">{{ __('All levels') }}</option>
            <option value="fatal" @selected($filters['level'] === 'fatal')>{{ __('Fatal') }}</option>
            <option value="error" @selected($filters['level'] === 'error')>{{ __('Error') }}</option>
            <option value="warning" @selected($filters['level'] === 'warning')>{{ __('Warning') }}</option>
          </select>
        </div>
        <div class="col">
          <input type="text" name="search" class="form-control form-control-sm"
                 placeholder="{{ __('Search message, URL, file, exception...') }}"
                 value="{{ $filters['search'] }}">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="fas fa-search me-1"></i>Filter
          </button>
          <a href="{{ route('settings.error-log') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>
    </div>
  </div>

  {{-- Error Log Table --}}
  <div class="card">
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-dark">
          <tr>
            <th style="width:40px">#</th>
            <th style="width:140px">{{ __('Time') }}</th>
            <th style="width:70px">{{ __('Level') }}</th>
            <th>{{ __('Error') }}</th>
            <th style="width:160px">{{ __('Location') }}</th>
            <th style="width:120px">{{ __('Client') }}</th>
            <th style="width:100px">{{ __('Actions') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($entries as $entry)
            <tr class="{{ $entry->resolved_at ? 'table-light text-muted' : (!$entry->is_read ? 'table-warning' : '') }}">
              <td class="small text-muted">{{ $entry->id }}</td>
              <td class="small text-nowrap">
                {{ $entry->created_at }}
                @if($entry->request_id ?? null)
                  <br><code class="small">{{ substr($entry->request_id, 0, 12) }}...</code>
                @endif
              </td>
              <td>
                @if($entry->resolved_at)
                  <span class="badge bg-success">FIXED</span>
                @elseif($entry->level === 'fatal')
                  <span class="badge bg-danger">FATAL</span>
                @elseif($entry->level === 'error')
                  <span class="badge bg-warning text-dark">ERROR</span>
                @else
                  <span class="badge bg-secondary">{{ strtoupper($entry->level) }}</span>
                @endif
                @if($entry->status_code)
                  <span class="badge bg-dark">{{ $entry->status_code }}</span>
                @endif
              </td>
              <td>
                <div class="fw-bold small">{{ e($entry->exception_class ?? '') }}</div>
                <div class="small" style="word-break:break-word">{{ e($entry->message) }}</div>
                @if($entry->url ?? ($entry->request_url ?? null))
                  <div class="small text-muted" style="word-break:break-all">
                    <span class="badge bg-light text-dark">{{ $entry->request_method ?? ($entry->http_method ?? ($entry->method ?? 'GET')) }}</span>
                    {{ e($entry->url ?? $entry->request_url) }}
                  </div>
                @endif
                @if($entry->resolved_at)
                  <div class="small text-success"><i class="fas fa-check me-1"></i>Resolved {{ $entry->resolved_at }}</div>
                @endif
              </td>
              <td class="small text-truncate" style="max-width:160px" title="{{ e($entry->file ?? '') }}">
                {{ $entry->file ? basename($entry->file) . ':' . $entry->line : '-' }}
              </td>
              <td class="small">
                {{ $entry->ip_address ?? ($entry->client_ip ?? ($entry->ip ?? '-')) }}
                @if($entry->user_id ?? null)
                  <br><span class="badge bg-info">user:{{ $entry->user_id }}</span>
                @endif
              </td>
              <td class="text-nowrap">
                @if($entry->resolved_at)
                  <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="reopen_id" value="{{ $entry->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ __('Reopen') }}">
                      <i class="fas fa-undo"></i>
                    </button>
                  </form>
                @else
                  <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="resolve_id" value="{{ $entry->id }}">
                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Resolve') }}">
                      <i class="fas fa-check"></i>
                    </button>
                  </form>
                @endif
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="collapse" data-bs-target="#trace-{{ $entry->id }}" title="{{ __('Details') }}">
                  <i class="fas fa-chevron-down"></i>
                </button>
                <form method="POST" action="{{ route('settings.error-log') }}" class="d-inline">
                  @csrf
                  <input type="hidden" name="delete_id" value="{{ $entry->id }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"
                          onclick="return confirm('Delete this entry?')">
                    <i class="fas fa-times"></i>
                  </button>
                </form>
              </td>
            </tr>
            @if($entry->trace ?? ($entry->stack_trace ?? null))
              <tr class="collapse" id="trace-{{ $entry->id }}">
                <td colspan="7">
                  <pre class="bg-dark text-light p-2 rounded small mb-0" style="max-height:300px;overflow:auto">{{ e($entry->trace ?? $entry->stack_trace) }}</pre>
                  @if($entry->user_agent ?? ($entry->ua ?? null))
                    <small class="text-muted">UA: {{ e(substr($entry->user_agent ?? $entry->ua, 0, 150)) }}</small>
                  @endif
                </td>
              </tr>
            @endif
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">
                <i class="fas fa-check-circle fa-2x mb-2 d-block text-success"></i>
                No errors logged.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if($totalPages > 1)
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Page {{ $page }} / {{ $totalPages }} ({{ number_format($total) }} total)</small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            @if($page > 1)
              <li class="page-item">
                <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $page - 1])) }}">&laquo;</a>
              </li>
            @endif
            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
              <li class="page-item @if($i === $page) active @endif">
                <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $i])) }}">{{ $i }}</a>
              </li>
            @endfor
            @if($page < $totalPages)
              <li class="page-item">
                <a class="page-link" href="{{ route('settings.error-log', array_merge($filters, ['page' => $page + 1])) }}">&raquo;</a>
              </li>
            @endif
          </ul>
        </nav>
      </div>
    @endif
  </div>

  <div class="mt-3">
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
      <i class="fas fa-arrow-left me-1"></i>Back to Settings
    </a>
  </div>
@endsection
