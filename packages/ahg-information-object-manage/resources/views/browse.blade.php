@extends('theme::layouts.1col')

@section('title', 'Archival descriptions')
@section('body-class', 'browse informationobject')

@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small" id="heading-label">Archival description</span>
    </div>
  </div>
@endsection

@section('before-content')
  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search archival descriptions',
        'landmarkLabel' => 'Archival description',
    ])
  </div>
@endsection

@section('content')
  @if($pager->getNbResults())
    <div class="d-flex flex-wrap gap-2 mb-3">
      @if($repositories->isNotEmpty())
        <div class="dropdown">
          <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
            {{ $selectedRepository ? ($repositoryNames[$selectedRepository] ?? 'Repository') : 'Repository' }}
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item {{ !$selectedRepository ? 'active' : '' }}"
                 href="{{ route('informationobject.browse', array_merge(request()->except('repository', 'page'), [])) }}">
                All repositories
              </a>
            </li>
            @foreach($repositories as $repo)
              <li>
                <a class="dropdown-item {{ $selectedRepository == $repo->id ? 'active' : '' }}"
                   href="{{ route('informationobject.browse', array_merge(request()->except('page'), ['repository' => $repo->id])) }}">
                  {{ $repo->name }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="d-flex flex-wrap gap-2 ms-auto">
        @include('ahg-core::components.sort-pickers', [
            'options' => $sortOptions,
            'default' => 'alphabetic',
        ])

        @php
          $currentSort = request('sort', 'alphabetic');
          $currentDir = request('sortDir', ($currentSort === 'lastUpdated' ? 'desc' : 'asc'));
          $dirQuery = request()->except(['sortDir', 'page']);
        @endphp
        <div class="dropdown d-inline-block">
          <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="sortDir-button" data-bs-toggle="dropdown" aria-expanded="false">
            Direction: {{ $currentDir === 'desc' ? 'Descending' : 'Ascending' }}
          </button>
          <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="sortDir-button">
            <li>
              <a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'asc'])) }}"
                 class="dropdown-item {{ $currentDir === 'asc' ? 'active' : '' }}">Ascending</a>
            </li>
            <li>
              <a href="{{ request()->url() }}?{{ http_build_query(array_merge($dirQuery, ['sortDir' => 'desc'])) }}"
                 class="dropdown-item {{ $currentDir === 'desc' ? 'active' : '' }}">Descending</a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>Title</th>
            <th>Level of description</th>
            <th>Repository</th>
            <th>Identifier</th>
            @if(request('sort') === 'lastUpdated')
              <th>Updated</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td>
                <a href="{{ route('informationobject.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>
                @if(!empty($doc['level_of_description_id']) && isset($levelNames[$doc['level_of_description_id']]))
                  {{ $levelNames[$doc['level_of_description_id']] }}
                @endif
              </td>
              <td>
                @if(!empty($doc['repository_id']) && isset($repositoryNames[$doc['repository_id']]))
                  {{ $repositoryNames[$doc['repository_id']] }}
                @endif
              </td>
              <td>{{ $doc['identifier'] ?? '' }}</td>
              @if(request('sort') === 'lastUpdated')
                <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection

@section('after-content')
  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
