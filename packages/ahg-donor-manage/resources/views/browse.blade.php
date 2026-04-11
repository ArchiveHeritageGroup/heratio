@extends('theme::layouts.1col')

@section('title', 'Donors')
@section('body-class', 'browse donor')

@section('title-block')
  <h1>Browse donors</h1>
@endsection

@section('before-content')
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search donors',
        'landmarkLabel' => 'Donor',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])

      @php
        $activeSort = request('sort', 'alphabetic');
        $currentDir = request('sortDir', ($activeSort === 'lastUpdated' ? 'desc' : 'asc'));
        $dirQuery = request()->except(['sortDir', 'page']);
      @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle text-wrap" type="button" id="sortDir-button" data-bs-toggle="dropdown" aria-expanded="false">
          Direction: {{ $currentDir === 'desc' ? 'Descending' : 'Ascending' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sortDir-button">
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'asc'])) }}" class="dropdown-item {{ $currentDir === 'asc' ? 'active' : '' }}">Ascending</a></li>
          <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'desc'])) }}" class="dropdown-item {{ $currentDir === 'desc' ? 'active' : '' }}">Descending</a></li>
        </ul>
      </div>
    </div>
  </div>
@endsection

@section('content')
  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Name</th>
          @if(request('sort', 'alphabetic') !== 'alphabetic')
            <th>Updated</th>
          @endif
        </tr>
      </thead>
      <tbody>
        @foreach($pager->getResults() as $doc)
          <tr>
            <td>
              <a href="{{ route('donor.show', $doc['slug']) }}" title="{{ $doc['name'] ?: '[Untitled]' }}">
                {{ $doc['name'] ?: '[Untitled]' }}
              </a>
            </td>
            @if(request('sort', 'alphabetic') !== 'alphabetic')
              <td>
                @if(!empty($doc['updated_at']))
                  {{ \Carbon\Carbon::parse($doc['updated_at'])->format('F j, Y g:i A') }}
                @endif
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])

  @auth
    <section class="actions mb-3">
      <a class="btn btn-outline-secondary" href="{{ route('donor.create') }}" title="Add new">Add new</a>
    </section>
  @endauth
@endsection
