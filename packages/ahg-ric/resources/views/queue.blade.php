@extends('theme::layouts.1col')

@section('title', 'RiC Sync Queue')
@section('body-class', 'admin ric queue')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-list-ol me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($items->total())
          Showing {{ number_format($items->total()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">RiC Sync Queue</span>
    </div>
  </div>

  {{-- Status Tabs --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('ric.queue', ['status' => 'all']) }}"
       class="btn btn-sm {{ $tab === 'all' ? 'atom-btn-white' : 'atom-btn-white' }}">
      All <span class="badge bg-light text-dark ms-1">{{ $counts['all'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.queue', ['status' => 'queued']) }}"
       class="btn btn-sm {{ $tab === 'queued' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Queued <span class="badge bg-light text-dark ms-1">{{ $counts['queued'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.queue', ['status' => 'processing']) }}"
       class="btn btn-sm {{ $tab === 'processing' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Processing <span class="badge bg-light text-dark ms-1">{{ $counts['processing'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.queue', ['status' => 'completed']) }}"
       class="btn btn-sm {{ $tab === 'completed' ? 'atom-btn-outline-success' : 'atom-btn-outline-success' }}">
      Completed <span class="badge bg-light text-dark ms-1">{{ $counts['completed'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.queue', ['status' => 'failed']) }}"
       class="btn btn-sm {{ $tab === 'failed' ? 'atom-btn-outline-danger' : 'atom-btn-outline-danger' }}">
      Failed <span class="badge bg-light text-dark ms-1">{{ $counts['failed'] ?? 0 }}</span>
    </a>
  </div>

  @if($items->total())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>Entity</th>
            <th>Operation</th>
            <th>Priority</th>
            <th>Scheduled</th>
            <th>Status</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td>{{ ucfirst(str_replace('_', ' ', $item->entity_type ?? '')) }} #{{ $item->entity_id }}</td>
              <td>{{ $item->operation }}</td>
              <td>{{ $item->priority }}</td>
              <td>{{ $item->scheduled_at ? \Carbon\Carbon::parse($item->scheduled_at)->format('Y-m-d H:i') : '' }}</td>
              <td>
                @if($item->status === 'queued')
                  <span class="badge bg-primary">queued</span>
                @elseif($item->status === 'processing')
                  <span class="badge bg-info">processing</span>
                @elseif($item->status === 'completed')
                  <span class="badge bg-success">completed</span>
                @elseif($item->status === 'failed')
                  <span class="badge bg-danger">failed</span>
                @else
                  <span class="badge bg-secondary">{{ $item->status }}</span>
                @endif
              </td>
              <td>
                @if($item->error_message)
                  <small class="text-danger" title="{{ $item->error_message }}">
                    {{ \Illuminate\Support\Str::limit($item->error_message, 50) }}
                  </small>
                @endif
              </td>
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
