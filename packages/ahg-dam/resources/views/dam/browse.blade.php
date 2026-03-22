@extends('theme::layouts.1col')

@section('title', 'DAM assets')
@section('body-class', 'browse dam')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-images me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">DAM assets</span>
    </div>
  </div>

  {{-- Search + Sort row --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search DAM assets',
        'landmarkLabel' => 'DAM asset',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      {{-- Asset type filter --}}
      @php
        $currentAssetType = request('asset_type', '');
        $assetTypeQuery = request()->except(['asset_type', 'page']);
        $assetTypes = [
            '' => 'All types',
            'photo' => 'Photo / Image',
            'artwork' => 'Artwork / Painting',
            'scan' => 'Scan / Digitized',
            'documentary' => 'Documentary',
            'feature' => 'Feature Film',
            'short' => 'Short Film',
            'news' => 'News / Footage',
            'interview' => 'Interview',
            'home_movie' => 'Home Movie',
            'oral_history' => 'Oral History',
            'music' => 'Music Recording',
            'podcast' => 'Podcast / Radio',
            'speech' => 'Speech / Lecture',
            'document' => 'Document / PDF',
            'manuscript' => 'Manuscript',
        ];
      @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="assetType-button" data-bs-toggle="dropdown" aria-expanded="false">
          Type: {{ $assetTypes[$currentAssetType] ?? 'All types' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="assetType-button">
          @foreach($assetTypes as $val => $label)
            <li>
              <a href="{{ request()->url() }}?{{ http_build_query(array_merge($assetTypeQuery, ['asset_type' => $val])) }}" class="dropdown-item {{ $currentAssetType === $val ? 'active' : '' }}">{{ $label }}</a>
            </li>
          @endforeach
        </ul>
      </div>

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

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th style="width:60px;">Thumbnail</th>
            <th>Title</th>
            <th>Asset Type</th>
            <th>Creator</th>
            <th>Date</th>
            @if(request('sort') === 'lastUpdated')
              <th>Updated</th>
            @endif
            <th class="text-center" style="width:50px;"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            <tr>
              <td class="text-center">
                <i class="fas fa-image text-muted" aria-hidden="true"></i>
              </td>
              <td>
                <a href="{{ route('dam.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
                @if($doc['identifier'])
                  <br><small class="text-muted">{{ $doc['identifier'] }}</small>
                @endif
              </td>
              <td>
                @if($doc['asset_type'])
                  <span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $doc['asset_type'])) }}</span>
                @endif
              </td>
              <td>{{ $doc['creator'] ?? '' }}</td>
              <td>{{ $doc['date_created'] ? \Carbon\Carbon::parse($doc['date_created'])->format('Y-m-d') : '' }}</td>
              @if(request('sort') === 'lastUpdated')
                <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
              @endif
              <td class="text-center">
                <button class="btn btn-sm atom-btn-white clipboard"
                        data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="dam"
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
      <a href="{{ route('dam.create') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-plus me-1"></i>Add new
      </a>
    </div>
  @endauth
@endsection
