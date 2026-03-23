@extends('theme::layouts.1col')

@section('title', 'Library items')
@section('body-class', 'browse library')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-book me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Library items</span>
    </div>
  </div>

  {{-- Search + Sort row --}}
  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search library items',
        'landmarkLabel' => 'Library item',
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

      {{-- Material type filter --}}
      @php
        $currentMaterialType = request('material_type', '');
        $materialTypeQuery = request()->except(['material_type', 'page']);
        $materialTypeOptions = [
            '' => 'All types',
            'monograph' => 'Monograph',
            'ebook' => 'E-book',
            'journal' => 'Journal',
            'periodical' => 'Periodical',
            'manuscript' => 'Manuscript',
            'map' => 'Map',
            'audiovisual' => 'Audiovisual',
            'microform' => 'Microform',
            'electronic' => 'Electronic resource',
            'kit' => 'Kit',
            'other' => 'Other',
        ];
      @endphp
      <div class="dropdown d-inline-block">
        <button class="btn btn-sm atom-btn-white dropdown-toggle text-wrap" type="button" id="materialType-button" data-bs-toggle="dropdown" aria-expanded="false">
          Type: {{ $materialTypeOptions[$currentMaterialType] ?? 'All types' }}
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2" aria-labelledby="materialType-button">
          @foreach($materialTypeOptions as $value => $label)
            <li><a href="{{ request()->url() }}?{{ http_build_query(array_merge($materialTypeQuery, ['material_type' => $value])) }}" class="dropdown-item {{ $currentMaterialType === $value ? 'active' : '' }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </div>
    </div>
  </div>

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0 sticky-enabled">
        <thead>
          <tr>
            <th style="width:60px;">Cover</th>
            <th>Title</th>
            <th>Author</th>
            <th>Material type</th>
            <th>Call number</th>
            <th>ISBN</th>
            <th>Publisher</th>
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
                @if(!empty($doc['cover_url']))
                  <a href="{{ route('library.show', $doc['slug']) }}">
                    <img src="{{ $doc['cover_url'] }}" alt="" style="max-width:40px;max-height:50px;" class="img-fluid">
                  </a>
                @else
                  <i class="fas fa-book text-muted" aria-hidden="true"></i>
                @endif
              </td>
              <td>
                <a href="{{ route('library.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
              </td>
              <td>{{ $doc['responsibility_statement'] ?? '' }}</td>
              <td>
                @if(!empty($doc['material_type']))
                  <span class="badge bg-secondary">{{ ucfirst($doc['material_type']) }}</span>
                @endif
              </td>
              <td>
                @if(!empty($doc['call_number']))
                  <code>{{ $doc['call_number'] }}</code>
                @endif
              </td>
              <td>{{ $doc['isbn'] ?? '' }}</td>
              <td>{{ $doc['publisher'] ?? '' }}</td>
              @if(request('sort') === 'lastUpdated')
                <td>{{ $doc['updated_at'] ? \Carbon\Carbon::parse($doc['updated_at'])->format('Y-m-d') : '' }}</td>
              @endif
              <td class="text-center">
                <button class="btn btn-sm atom-btn-white clipboard"
                        data-clipboard-slug="{{ $doc['slug'] }}" data-clipboard-type="library"
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

  {{-- Action bar --}}
  @auth
    <section class="actions mb-3 nav gap-2">
      <a href="{{ route('library.create') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-plus me-1"></i>Add new
      </a>
      <a href="{{ route('library.reports') }}" class="btn atom-btn-outline-light ms-2">
        <i class="fas fa-chart-bar me-1"></i>Library Reports
      </a>
    </section>
  @endauth
@endsection
