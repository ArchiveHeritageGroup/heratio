@extends('theme::layouts.1col')

@section('title', 'RiC Sync Status')
@section('body-class', 'admin ric sync-status')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-sync-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($items->total())
          Showing {{ number_format($items->total()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">RiC Sync Status</span>
    </div>
  </div>

  {{-- Filters --}}
  <form method="GET" action="{{ route('ric.sync-status') }}" class="row g-2 mb-3">
    <div class="col-auto">
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
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        @foreach($statuses as $s)
          <option value="{{ $s }}" {{ $status === $s ? 'selected' : '' }}>
            {{ ucfirst($s) }}
          </option>
        @endforeach
      </select>
    </div>
    @if($entityType !== '' || $status !== '')
      <div class="col-auto">
        <a href="{{ route('ric.sync-status') }}" class="btn btn-sm atom-btn-white">Clear Filters</a>
      </div>
    @endif
  </form>

  @if($items->total())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>RiC URI</th>
            <th>Entity Type</th>
            <th>Entity ID</th>
            <th>Status</th>
            <th>Last Synced</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td><code>{{ $item->ric_uri }}</code></td>
              <td>{{ ucfirst(str_replace('_', ' ', $item->entity_type)) }}</td>
              <td>{{ $item->entity_id }}</td>
              <td>
                @if($item->sync_status === 'synced')
                  <span class="badge bg-success">synced</span>
                @elseif($item->sync_status === 'pending')
                  <span class="badge bg-warning text-dark">pending</span>
                @elseif($item->sync_status === 'failed')
                  <span class="badge bg-danger">failed</span>
                @elseif($item->sync_status === 'error')
                  <span class="badge bg-danger">error</span>
                @else
                  <span class="badge bg-secondary">{{ $item->sync_status }}</span>
                @endif
              </td>
              <td>{{ $item->last_synced_at ? \Carbon\Carbon::parse($item->last_synced_at)->format('Y-m-d H:i') : 'Never' }}</td>
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
