@extends('theme::layouts.1col')

@section('title', 'RiC Orphaned Triples')
@section('body-class', 'admin ric orphans')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-unlink me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($items->total())
          Showing {{ number_format($items->total()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">RiC Orphaned Triples</span>
    </div>
  </div>

  {{-- Status Tabs --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('ric.orphans', ['status' => 'all']) }}"
       class="btn btn-sm {{ $tab === 'all' ? 'atom-btn-white' : 'atom-btn-white' }}">
      All <span class="badge bg-light text-dark ms-1">{{ $counts['all'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.orphans', ['status' => 'detected']) }}"
       class="btn btn-sm {{ $tab === 'detected' ? 'atom-btn-outline-danger' : 'atom-btn-outline-danger' }}">
      Detected <span class="badge bg-light text-dark ms-1">{{ $counts['detected'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.orphans', ['status' => 'reviewed']) }}"
       class="btn btn-sm {{ $tab === 'reviewed' ? 'atom-btn-white' : 'atom-btn-white' }}">
      Reviewed <span class="badge bg-light text-dark ms-1">{{ $counts['reviewed'] ?? 0 }}</span>
    </a>
    <a href="{{ route('ric.orphans', ['status' => 'cleaned']) }}"
       class="btn btn-sm {{ $tab === 'cleaned' ? 'atom-btn-outline-success' : 'atom-btn-outline-success' }}">
      Cleaned <span class="badge bg-light text-dark ms-1">{{ $counts['cleaned'] ?? 0 }}</span>
    </a>
  </div>

  @if($items->total())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>RiC URI</th>
            <th>Expected Entity</th>
            <th>Status</th>
            <th>Detected</th>
            <th>Resolved</th>
          </tr>
        </thead>
        <tbody>
          @foreach($items as $item)
            <tr>
              <td><code>{{ $item->ric_uri }}</code></td>
              <td>{{ ucfirst(str_replace('_', ' ', $item->expected_entity_type ?? '')) }}</td>
              <td>
                @if($item->status === 'detected')
                  <span class="badge bg-danger">detected</span>
                @elseif($item->status === 'reviewed')
                  <span class="badge bg-warning text-dark">reviewed</span>
                @elseif($item->status === 'cleaned')
                  <span class="badge bg-success">cleaned</span>
                @else
                  <span class="badge bg-secondary">{{ $item->status }}</span>
                @endif
              </td>
              <td>{{ $item->detected_at ? \Carbon\Carbon::parse($item->detected_at)->format('Y-m-d H:i') : '' }}</td>
              <td>{{ $item->resolved_at ? \Carbon\Carbon::parse($item->resolved_at)->format('Y-m-d H:i') : '' }}</td>
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
