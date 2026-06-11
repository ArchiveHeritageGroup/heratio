{{--
  heratio#1205 - Capture queue (admin) - the actionable workflow on top of the
  at-risk register. Operators pull records into a working queue, track their
  status (queued -> in progress -> captured / deferred), assign them, note them,
  and pull them back out. Status values are read live from the Dropdown Manager
  group `capture_queue_status` (never hardcoded). Jurisdiction-neutral, BS5.
  Backed by AhgCore\Services\CaptureQueueService via CaptureQueueController.
--}}
@extends('theme::layouts.1col')
@section('title', __('Capture queue'))

@section('content')
@php
  $available = $available ?? false;
  $rows = $rows ?? [];
  $statuses = $statuses ?? [];
  $counts = $counts ?? ['total' => 0, 'by_status' => []];
  $throughput = $throughput ?? ['captured_7d' => 0, 'captured_30d' => 0, 'still_queued' => 0, 'captured_total' => 0];
  $filterStatus = $filterStatus ?? '';
  // code -> {label,color,icon} for rendering a status badge from its stored code.
  $statusMap = [];
  foreach ($statuses as $s) { $statusMap[$s['code']] = $s; }
@endphp

<div class="container-fluid py-3">

  <div class="d-flex flex-wrap align-items-baseline gap-2 mb-1">
    <h1 class="h4 mb-0"><i class="fas fa-list-check me-2 text-primary"></i>{{ __('Capture queue') }}</h1>
    <span class="text-muted small">{{ __('Records queued for digitisation, and where each one is') }}</span>
    @if(Route::has('capture-priority.index'))
      <a href="{{ route('capture-priority.index') }}" class="ms-auto btn btn-sm btn-outline-secondary">
        <i class="fas fa-triangle-exclamation me-1"></i>{{ __('Back to at-risk register') }}
      </a>
    @endif
  </div>
  <p class="text-muted small mb-3" style="max-width:820px">
    {{ __('This is the working queue built from the at-risk register. Add a record from the register, then move it through capture: track its status, assign it to a team member, and note anything the operator needs to know. It turns the priority list into real, trackable digitisation work.') }}
  </p>

  @if(session('status'))
    <div class="alert alert-success py-2"><i class="fas fa-circle-check me-1"></i>{{ session('status') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-warning py-2"><i class="fas fa-circle-exclamation me-1"></i>{{ session('error') }}</div>
  @endif

  @if(!$available)
    {{-- Feature unavailable (table not yet created on this install): honest empty state, never a 500. --}}
    <div class="alert alert-info">
      <i class="fas fa-circle-info me-1"></i>{{ __('The capture queue is not available on this install yet. It will appear automatically once the queue store has been initialised.') }}
    </div>
  @else

  {{-- Status filter pills + counts. Built entirely from the configured dropdown group. --}}
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('capture-priority.queue') }}"
       class="btn btn-sm {{ $filterStatus === '' ? 'btn-primary' : 'btn-outline-secondary' }}">
      {{ __('All') }} <span class="badge bg-light text-dark ms-1">{{ number_format($counts['total'] ?? 0) }}</span>
    </a>
    @foreach($statuses as $s)
      @php $n = $counts['by_status'][$s['code']] ?? 0; @endphp
      <a href="{{ route('capture-priority.queue', ['status' => $s['code']]) }}"
         class="btn btn-sm {{ $filterStatus === $s['code'] ? 'btn-primary' : 'btn-outline-secondary' }}">
        @if(!empty($s['icon']))<i class="fas fa-{{ $s['icon'] }} me-1"></i>@endif{{ $s['label'] }}
        <span class="badge bg-light text-dark ms-1">{{ number_format($n) }}</span>
      </a>
    @endforeach
    {{-- Export the current queue to CSV, carrying the active status filter. Read-only, streamed server-side. --}}
    <a href="{{ route('capture-priority.queue.export', $filterStatus !== '' ? ['status' => $filterStatus] : []) }}"
       class="ms-auto btn btn-sm btn-outline-primary">
      <i class="fas fa-file-csv me-1"></i>{{ __('Export CSV') }}
    </a>
  </div>

  {{-- Throughput summary: a small at-a-glance strip of how the queue is moving. Built from
       CaptureQueueService::counts() + throughput() (a single guarded aggregate); zeros on an
       empty / unavailable queue. Jurisdiction-neutral copy. --}}
  <div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('In queue') }}</div>
          <div class="h4 mb-0">{{ number_format($counts['total'] ?? 0) }}</div>
          <div class="text-muted small">{{ number_format($throughput['still_queued'] ?? 0) }} {{ __('not yet captured') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('Captured (7 days)') }}</div>
          <div class="h4 mb-0 text-success">{{ number_format($throughput['captured_7d'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('in the last week') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('Captured (30 days)') }}</div>
          <div class="h4 mb-0 text-success">{{ number_format($throughput['captured_30d'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('in the last month') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body py-2">
          <div class="text-muted small text-uppercase">{{ __('Captured (total)') }}</div>
          <div class="h4 mb-0">{{ number_format($throughput['captured_total'] ?? 0) }}</div>
          <div class="text-muted small">{{ __('all time') }}</div>
        </div>
      </div>
    </div>
  </div>

  @if(empty($statuses))
    <div class="alert alert-warning py-2">
      <i class="fas fa-circle-exclamation me-1"></i>{{ __('No capture-queue statuses are configured. Add values to the "Capture Queue Status" group in the Dropdown Manager to enable status tracking.') }}
    </div>
  @endif

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-transparent d-flex flex-wrap align-items-center gap-2 py-2">
      <span class="fw-semibold">{{ __('Queue') }}</span>
      @if($filterStatus !== '' && isset($statusMap[$filterStatus]))
        <span class="text-muted small">{{ __('filtered by') }} &ldquo;{{ $statusMap[$filterStatus]['label'] }}&rdquo;</span>
      @endif
      <span class="ms-auto text-muted small">{{ number_format(count($rows)) }} {{ __('shown') }}</span>
    </div>

    @if(empty($rows))
      <div class="card-body text-muted">
        <i class="fas fa-inbox me-1"></i>{{ __('Nothing in the capture queue yet. Open the at-risk register and use "Add to capture queue" on the records you want to digitise next.') }}
      </div>
    @else
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>{{ __('Record') }}</th>
            <th style="width:6rem" class="text-end">{{ __('Score') }}</th>
            <th style="width:13rem">{{ __('Status') }}</th>
            <th style="width:13rem">{{ __('Assigned to') }}</th>
            <th>{{ __('Note') }}</th>
            <th style="width:5rem" class="text-end">{{ __('Remove') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rows as $r)
            @php $st = $statusMap[$r['status']] ?? null; @endphp
            <tr>
              <td>
                @if(!empty($r['slug']))
                  <a href="{{ url('/'.$r['slug']) }}" target="_blank" rel="noopener">{{ $r['title'] }}</a>
                @else
                  {{ $r['title'] }}
                @endif
                <span class="text-muted small d-block">#{{ $r['information_object_id'] }}</span>
              </td>
              <td class="text-end">
                <span class="badge bg-light text-dark border">{{ $r['priority_score'] }}</span>
              </td>
              <td>
                {{-- Status change: a dropdown-driven select that auto-submits. Options come ONLY
                     from the configured capture_queue_status group. --}}
                <form method="post" action="{{ route('capture-priority.queue.status') }}" class="d-flex align-items-center gap-1">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r['id'] }}">
                  <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" @disabled(empty($statuses))>
                    @foreach($statuses as $s)
                      <option value="{{ $s['code'] }}" @selected($r['status'] === $s['code'])>{{ $s['label'] }}</option>
                    @endforeach
                    @if(!isset($statusMap[$r['status']]))
                      {{-- Stored status no longer in the group (renamed/disabled): show it so it is never silently hidden. --}}
                      <option value="{{ $r['status'] }}" selected>{{ $r['status'] }} ({{ __('inactive') }})</option>
                    @endif
                  </select>
                </form>
                @if($r['captured_at'])
                  <span class="text-muted small d-block mt-1"><i class="fas fa-check me-1"></i>{{ __('captured') }} {{ $r['captured_at'] }}</span>
                @endif
              </td>
              <td>
                <form method="post" action="{{ route('capture-priority.queue.assign') }}" class="d-flex align-items-center gap-1">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r['id'] }}">
                  <input type="text" name="assigned_to" value="{{ $r['assigned_to'] }}" maxlength="190"
                         class="form-control form-control-sm" placeholder="{{ __('Unassigned') }}">
                  <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Save assignee') }}"><i class="fas fa-floppy-disk"></i></button>
                </form>
              </td>
              <td class="small text-muted">{{ $r['note'] }}</td>
              <td class="text-end">
                <form method="post" action="{{ route('capture-priority.queue.remove') }}"
                      onsubmit="return confirm('{{ __('Remove this record from the capture queue?') }}');">
                  @csrf
                  <input type="hidden" name="id" value="{{ $r['id'] }}">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Remove from queue') }}"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @endif
  </div>

  <p class="text-muted small mt-3 mb-0" style="max-width:820px">
    <i class="fas fa-circle-info me-1"></i>{{ __('The score shown is a snapshot taken when the record was queued, so the queue stays stable even as the live at-risk register shifts. Status values are configured in the Dropdown Manager and can be renamed or extended without code changes.') }}
  </p>

  @endif
</div>
@endsection
