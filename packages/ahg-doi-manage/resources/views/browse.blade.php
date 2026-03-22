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
      <div class="d-flex flex-column">
        <h1 class="mb-0">
          @if($pager->getNbResults())
            Showing {{ number_format($pager->getNbResults()) }} results
          @else
            No results found
          @endif
        </h1>
        <span class="small text-muted">DOIs</span>
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
    </div>

    @if($pager->getNbResults())
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th>DOI</th>
              <th>Record</th>
              <th>Status</th>
              <th>Minted</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pager->getResults() as $doi)
              <tr>
                <td><code>{{ $doi['doi'] }}</code></td>
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
                  @else
                    <span class="badge bg-secondary">Draft</span>
                  @endif
                </td>
                <td>{{ $doi['minted_at'] ? \Carbon\Carbon::parse($doi['minted_at'])->format('Y-m-d H:i') : '' }}</td>
                <td class="text-end">
                  <a href="{{ route('doi.view', $doi['id']) }}" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-eye"></i> View
                  </a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @include('ahg-core::components.pager', ['pager' => $pager])
    @endif
  @endif
@endsection
