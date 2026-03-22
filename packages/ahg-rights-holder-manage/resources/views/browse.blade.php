@extends('theme::layouts.1col')

@section('title', 'Rights holders')
@section('body-class', 'browse rightsholder')

@section('content')
  <h1>Browse rights holders</h1>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search rights holders',
        'landmarkLabel' => 'Rights holder',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      {{-- Sort by --}}
      @php $activeSort = request('sort', 'alphabetic'); @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sort-button" data-bs-toggle="dropdown" aria-expanded="false">
          Sort by: {{ $sortOptions[$activeSort] ?? 'Name' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sort-button">
          @foreach($sortOptions as $key => $label)
            <li><a href="{{ request()->fullUrlWithQuery(['sort' => $key, 'page' => null]) }}" class="dropdown-item {{ $activeSort === $key ? 'active' : '' }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>

      {{-- Sort direction --}}
      @php
        $currentDir = request('sortDir', ($activeSort === 'lastUpdated' ? 'desc' : 'asc'));
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

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Updated</th>
        </tr>
      </thead>
      <tbody>
        @foreach($pager->getResults() as $doc)
          <tr>
            <td>
              <a href="{{ route('rightsholder.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                {{ $doc['name'] ?: '[Untitled]' }}
              </a>
            </td>
            <td>
              @if(!empty($doc['updated_at']))
                {{ \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y g:i A') }}
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <a class="btn atom-btn-outline-light" href="{{ route('rightsholder.create') }}" title="Add new">Add new</a>
    </section>
  @endauth
@endsection
