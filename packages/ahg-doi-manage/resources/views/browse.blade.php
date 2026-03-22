@extends('theme::layouts.1col')

@section('title', 'Browse DOIs')
@section('body-class', 'browse doi')

@section('content')
  @if(!($tablesExist ?? false))
    <div class="alert alert-warning">
      <i class="fas fa-exclamation-triangle me-2"></i>
      The DOI management tables have not been created yet. Please run the database migration to set up DOI management.
    </div>
  @else
    <div class="multiline-header d-flex align-items-center mb-3">
      <i class="fas fa-3x fa-fingerprint me-3" aria-hidden="true"></i>
      <div class="d-flex flex-column flex-grow-1">
        <h1 class="mb-0">
          @if($pager->getNbResults())
            Showing {{ number_format($pager->getNbResults()) }} results
          @else
            No results found
          @endif
        </h1>
        <span class="small text-muted">DOIs</span>
      </div>
      <div class="d-flex gap-2">
        <a href="{{ route('doi.index') }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-arrow-left me-1"></i> Dashboard
        </a>
        <a href="{{ route('doi.report') }}?format=csv&status={{ $currentStatus }}" class="btn btn-sm atom-btn-white">
          <i class="fas fa-download me-1"></i> Export CSV
        </a>
      </div>
    </div>

    {{-- Status filter buttons --}}
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="{{ route('doi.browse') }}"
         class="btn btn-sm {{ $currentStatus === '' ? 'atom-btn-white' : 'atom-btn-white' }}">
        All
      </a>
      <a href="{{ route('doi.browse', ['status' => 'findable']) }}"
         class="btn btn-sm {{ $currentStatus === 'findable' ? 'atom-btn-outline-success' : 'atom-btn-outline-success' }}">
        Findable
      </a>
      <a href="{{ route('doi.browse', ['status' => 'registered']) }}"
         class="btn btn-sm {{ $currentStatus === 'registered' ? 'atom-btn-white' : 'atom-btn-white' }}">
        Registered
      </a>
      <a href="{{ route('doi.browse', ['status' => 'draft']) }}"
         class="btn btn-sm {{ $currentStatus === 'draft' ? 'atom-btn-white' : 'atom-btn-white' }}">
        Draft
      </a>
      <a href="{{ route('doi.browse', ['status' => 'deleted']) }}"
         class="btn btn-sm {{ $currentStatus === 'deleted' ? 'atom-btn-outline-danger' : 'atom-btn-outline-danger' }}">
        Deleted
      </a>
    </div>

    @if($pager->getNbResults())
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th>DOI</th>
              <th>Record</th>
              <th>Status</th>
              <th>Minted</th>
              <th>Last Sync</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pager->getResults() as $doi)
              <tr>
                <td>
                  <a href="https://doi.org/{{ $doi['doi'] }}" target="_blank" class="text-monospace text-decoration-none">
                    <code>{{ $doi['doi'] }}</code>
                    <i class="fas fa-external-link-alt fa-xs ms-1"></i>
                  </a>
                </td>
                <td>
                  @if($doi['information_object_id'])
                    <a href="{{ route('informationobject.show', $doi['information_object_id']) }}">
                      {{ $doi['record_title'] ?: '[Untitled]' }}
                    </a>
                  @else
                    {{ $doi['record_title'] ?: '[Untitled]' }}
                  @endif
                </td>
                <td>
                  @if($doi['status'] === 'findable')
                    <span class="badge bg-success">Findable</span>
                  @elseif($doi['status'] === 'registered')
                    <span class="badge bg-info">Registered</span>
                  @elseif($doi['status'] === 'deleted')
                    <span class="badge bg-danger">Deleted</span>
                  @else
                    <span class="badge bg-secondary">Draft</span>
                  @endif
                </td>
                <td>{{ $doi['minted_at'] ? \Carbon\Carbon::parse($doi['minted_at'])->format('Y-m-d') : '-' }}</td>
                <td>{{ !empty($doi['last_sync_at']) ? \Carbon\Carbon::parse($doi['last_sync_at'])->format('Y-m-d') : '-' }}</td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="{{ route('doi.view', $doi['id']) }}" class="btn atom-btn-white" title="View">
                      <i class="fas fa-eye"></i>
                    </a>
                    <a href="{{ route('doi.view', $doi['id']) }}?sync=1" class="btn atom-btn-white" title="Sync">
                      <i class="fas fa-sync"></i>
                    </a>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @include('ahg-core::components.pager', ['pager' => $pager])
    @else
      <div class="text-center text-muted py-4">
        <i class="fas fa-link fa-3x mb-3"></i>
        <p>No DOIs found matching the criteria.</p>
        <a href="{{ route('doi.queue') }}" class="btn atom-btn-white">Mint DOIs</a>
      </div>
    @endif
  @endif
@endsection
