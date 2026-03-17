@extends('theme::layouts.1col')

@section('title', 'Function')
@section('body-class', 'browse function')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-tools me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Function</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search functions',
        'landmarkLabel' => 'Function',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto align-items-center">
      {{-- Sort by dropdown --}}
      @php $activeSort = request('sort', 'alphabetic'); @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" data-bs-toggle="dropdown">
          Sort by: {{ ['lastUpdated' => 'Date modified', 'alphabetic' => 'Name', 'identifier' => 'Identifier'][$activeSort] ?? 'Name' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2">
          @foreach(['lastUpdated' => 'Date modified', 'alphabetic' => 'Name', 'identifier' => 'Identifier'] as $key => $label)
            <li><a class="dropdown-item {{ $activeSort === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => 1]) }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>
      {{-- Direction dropdown --}}
      @php $activeDir = request('sortDir', 'asc'); @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" data-bs-toggle="dropdown">
          Direction: {{ $activeDir === 'asc' ? 'Ascending' : 'Descending' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2">
          <li><a class="dropdown-item {{ $activeDir === 'asc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sortDir' => 'asc', 'page' => 1]) }}">Ascending</a></li>
          <li><a class="dropdown-item {{ $activeDir === 'desc' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sortDir' => 'desc', 'page' => 1]) }}">Descending</a></li>
        </ul>
      </div>
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>Name</th>
            @if($activeSort === 'alphabetic' || $activeSort === 'identifier')
              <th>Type</th>
            @else
              <th>Updated</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($enrichedResults as $doc)
            <tr>
              <td>
                <a href="{{ route('function.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              @if($activeSort === 'alphabetic' || $activeSort === 'identifier')
                <td>{{ $doc['type_name'] ?? '' }}</td>
              @else
                <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3" style="background-color:#495057;border-radius:.375rem;padding:1rem;">
      <a href="{{ route('function.create') }}" class="btn atom-btn-outline-light">Add new</a>
    </section>
  @endauth
@endsection
