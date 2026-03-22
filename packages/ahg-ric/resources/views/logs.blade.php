@extends('theme::layouts.1col')

@section('title', 'RiC Sync Logs')
@section('body-class', 'admin ric logs')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-history me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($items->total())
          Showing {{ number_format($items->total()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">RiC Sync Logs</span>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('ric.logs') }}" class="row g-2 mb-3 align-items-end">
    <div class="col-auto">
      <label class="form-label small mb-0">Operation <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="operation" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Operations</option>
        @foreach($operations as $op)
          <option value="{{ $op }}" {{ $operation === $op ? 'selected' : '' }}>{{ $op }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-0">Status <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-0">Entity Type <span class="badge bg-secondary ms-1">Optional</span></label>
      <select name="entity_type" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Entity Types</option>
        @foreach($entityTypes as $et)
          <option value="{{ $et }}" {{ $entityType === $et ? 'selected' : '' }}>
            {{ ucfirst(str_replace('_', ' ', $et)) }}
          </option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label small mb-0">From <span class="badge bg-secondary ms-1">Optional</span></label>
      <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
      <label class="form-label small mb-0">To <span class="badge bg-secondary ms-1">Optional</span></label>
      <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}" onchange="this.form.submit()">
    </div>
    @if($operation !== '' || $status !== '' || $entityType !== '' || $dateFrom !== '' || $dateTo !== '')
      <div class="col-auto">
        <a href="{{ route('ric.logs') }}" class="btn btn-sm atom-btn-white">Clear Filters</a>
      </div>
    @endif
  </form>

  @if($items->total())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>Time</th>
            <th>Operation</th>
            <th>Entity</th>
            <th>Status</th>
            <th>Details</th>
            <th>Triggered By</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td class="text-nowrap">{{ $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('Y-m-d H:i:s') : '' }}</td>
              <td>{{ $item->operation }}</td>
              <td>{{ ucfirst(str_replace('_', ' ', $item->entity_type ?? '')) }} #{{ $item->entity_id ?? '' }}</td>
              <td>
                @if($item->status === 'completed')
                  <span class="badge bg-success">{{ $item->status }}</span>
                @elseif($item->status === 'failed')
                  <span class="badge bg-danger">{{ $item->status }}</span>
                @elseif($item->status === 'processing')
                  <span class="badge bg-primary">{{ $item->status }}</span>
                @else
                  <span class="badge bg-secondary">{{ $item->status }}</span>
                @endif
              </td>
              <td>
                @if($item->details)
                  <small title="{{ $item->details }}">{{ \Illuminate\Support\Str::limit($item->details, 60) }}</small>
                @endif
              </td>
              <td>{{ $item->triggered_by ?? '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-center">
      {{ $items->links() }}
    </div>
  @endif

  <div class="mt-3">
    <a href="{{ route('ric.index') }}" class="btn btn-sm atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
    </a>
  </div>
@endsection
