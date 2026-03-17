@extends('theme::layouts.1col')

@section('title', 'Accession records')
@section('body-class', 'browse accession')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-archive me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Accession records</span>
    </div>
  </div>

  {{-- Search + Sort row --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search accessions',
        'landmarkLabel' => 'Accession record',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'lastUpdated',
      ])

      {{-- Sort direction --}}
      @php
        $currentSort = request('sort', 'lastUpdated');
        $currentDir = request('sortDir', ($currentSort === 'lastUpdated' ? 'desc' : 'asc'));
        $dirQuery = request()->except(['sortDir', 'page']);
      @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sortDir-button" data-bs-toggle="dropdown" aria-expanded="false">
          Direction: {{ $currentDir === 'desc' ? 'Descending' : 'Ascending' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sortDir-button">
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'asc'])) }}" class="dropdown-item {{ $currentDir === 'asc' ? 'active' : '' }}">Ascending</a></li>
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'desc'])) }}" class="dropdown-item {{ $currentDir === 'desc' ? 'active' : '' }}">Descending</a></li>
        </ul>
      </div>
    </div>
  </div>

  {{-- Action buttons --}}
  @auth
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="{{ route('accession.export-csv') }}" class="btn btn-sm atom-btn-white">
        <i class="fas fa-upload me-1"></i>Export CSV
      </a>
    </div>
  @endauth

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>Accession number</th>
            <th>Title</th>
            <th>Acquisition date</th>
            <th>Status</th>
            <th>Priority</th>
            @if(request('sort') === 'lastUpdated')
              <th>Updated</th>
            @endif
            <th class="text-center" style="width:50px;"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('accession.show', $doc['slug']) }}">
                  {{ $doc['identifier'] ?: '—' }}
                </a>
              </td>
              <td>
                <a href="{{ route('accession.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>{{ $doc['accession_date'] ? \Carbon\Carbon::parse($doc['accession_date'])->format('Y-m-d') : '' }}</td>
              <td>
                @if(!empty($doc['processing_status_id']) && isset($termNames[$doc['processing_status_id']]))
                  @php $statusName = strtolower($termNames[$doc['processing_status_id']]); @endphp
                  <span class="badge bg-{{ str_contains($statusName, 'complete') || str_contains($statusName, 'accept') ? 'success' : (str_contains($statusName, 'reject') || str_contains($statusName, 'return') ? 'danger' : (str_contains($statusName, 'review') || str_contains($statusName, 'submit') ? 'info' : 'secondary')) }}">
                    {{ $termNames[$doc['processing_status_id']] }}
                  </span>
                @endif
              </td>
              <td>
                @if(!empty($doc['processing_priority_id']) && isset($termNames[$doc['processing_priority_id']]))
                  @php $prioName = strtolower($termNames[$doc['processing_priority_id']]); @endphp
                  <span class="badge bg-{{ str_contains($prioName, 'urgent') ? 'danger' : (str_contains($prioName, 'high') ? 'warning' : (str_contains($prioName, 'low') ? 'secondary' : 'info')) }}">
                    {{ $termNames[$doc['processing_priority_id']] }}
                  </span>
                @endif
              </td>
              @if(request('sort') === 'lastUpdated')
                <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
              @endif
              <td class="text-center">
                <button class="btn btn-sm atom-btn-white clipboard"
                        data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="accession"
                        title="Add to clipboard">
                  <i class="fas fa-paperclip" aria-hidden="true"></i>
                  <span class="visually-hidden">Add to clipboard</span>
                </button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  {{-- Add new button --}}
  @auth
    <div class="actions mb-3" style="background:#495057;border-radius:.375rem;padding:1rem;">
      <a href="{{ route('accession.create') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-plus me-1"></i>Add new
      </a>
    </div>
  @endauth
@endsection
