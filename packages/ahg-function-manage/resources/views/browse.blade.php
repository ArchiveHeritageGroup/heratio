@extends('theme::layouts.1col')

@section('title', 'Browse functions')
@section('body-class', 'functionManage browse')

@section('content')
  <h1>Browse functions</h1>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search functions',
        'landmarkLabel' => 'Function',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      {{-- Sort by dropdown --}}
      @php $activeSort = request('sort', 'alphabetic'); @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sort-button" data-bs-toggle="dropdown" aria-expanded="false">
          Sort by: {{ ['alphabetic' => 'Name', 'lastUpdated' => 'Date modified', 'identifier' => 'Identifier'][$activeSort] ?? 'Name' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sort-button">
          @foreach(['alphabetic' => 'Name', 'lastUpdated' => 'Date modified', 'identifier' => 'Identifier'] as $key => $label)
            <li><a class="dropdown-item {{ $activeSort === $key ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => 1]) }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>
      {{-- Direction dropdown --}}
      @php $activeDir = request('sortDir', 'asc'); @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sortDir-button" data-bs-toggle="dropdown" aria-expanded="false">
          Direction: {{ $activeDir === 'asc' ? 'Ascending' : 'Descending' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sortDir-button">
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
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>Name</th>
            <th>Type</th>
            <th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach($enrichedResults as $doc)
            <tr>
              <td>
                <a href="{{ route('function.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>{{ $doc['type_name'] ?? '' }}</td>
              <td>{{ !empty($doc['updated_at']) ? \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y g:i A') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <div class="actions mb-3" style="background:#495057 !important;border-radius:.375rem;padding:1rem;display:block;">
      <a href="{{ route('function.create') }}" class="btn atom-btn-outline-light">Add new</a>
    </div>
  @endauth
@endsection
