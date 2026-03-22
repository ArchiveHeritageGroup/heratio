@extends('theme::layouts.1col')

@section('title', 'Archival descriptions')
@section('body-class', 'browse informationobject')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Archival descriptions</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search archival descriptions',
        'landmarkLabel' => 'Archival description',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
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

      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
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

  @include('ahg-core::components.pager', ['pager' => $pager])
@endsection
