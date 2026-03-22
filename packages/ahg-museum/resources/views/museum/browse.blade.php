@extends('theme::layouts.1col')

@section('title', 'Museum objects')
@section('body-class', 'browse museum')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-university me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">
        @if($pager->getNbResults())
          Showing {{ number_format($pager->getNbResults()) }} results
        @else
          No results found
        @endif
      </h1>
      <span class="small text-muted">Museum objects</span>
    </div>
  </div>

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search museum objects',
        'landmarkLabel' => 'Museum object',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @if(!empty($workTypes))
        <div class="dropdown">
          <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
            {{ $selectedWorkType ?: 'Work type' }}
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item {{ !$selectedWorkType ? 'active' : '' }}"
                 href="{{ route('museum.browse', array_merge(request()->except('work_type', 'page'), [])) }}">
                All work types
              </a>
            </li>
            @foreach($workTypes as $wt)
              <li>
                <a class="dropdown-item {{ $selectedWorkType === $wt ? 'active' : '' }}"
                   href="{{ route('museum.browse', array_merge(request()->except('page'), ['work_type' => $wt])) }}">
                  {{ $wt }}
                </a>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      @if(!empty($classifications))
        <div class="dropdown">
          <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
            {{ $selectedClassification ?: 'Classification' }}
          </button>
          <ul class="dropdown-menu">
            <li>
              <a class="dropdown-item {{ !$selectedClassification ? 'active' : '' }}"
                 href="{{ route('museum.browse', array_merge(request()->except('classification', 'page'), [])) }}">
                All classifications
              </a>
            </li>
            @foreach($classifications as $cls)
              <li>
                <a class="dropdown-item {{ $selectedClassification === $cls ? 'active' : '' }}"
                   href="{{ route('museum.browse', array_merge(request()->except('page'), ['classification' => $cls])) }}">
                  {{ $cls }}
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

  @auth
    <div class="mb-3">
      <a href="{{ route('museum.create') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-plus me-1"></i> Add new
      </a>
    </div>
  @endauth

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th style="width: 60px;">Thumbnail</th>
            <th>Title / Object</th>
            <th>Work type</th>
            <th>Creator</th>
            <th>Date</th>
            <th>Location</th>
            @if(request('sort') === 'lastUpdated')
              <th>Updated</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($pager->getResults() as $doc)
            @php
              $thumb = null;
              try {
                  $doSet = \AhgCore\Services\DigitalObjectService::getForObject($doc['id']);
                  if ($doSet && $doSet['thumbnail']) {
                      $thumb = \AhgCore\Services\DigitalObjectService::getUrl($doSet['thumbnail']);
                  }
              } catch (\Exception $e) {}
            @endphp
            <tr>
              <td class="text-center">
                @if($thumb)
                  <img src="{{ $thumb }}" alt="" class="img-thumbnail" style="max-width:50px;max-height:50px;">
                @else
                  <i class="fas fa-university text-muted"></i>
                @endif
              </td>
              <td>
                <a href="{{ route('museum.show', $doc['slug']) }}">
                  {{ $doc['name'] ?: '[Untitled]' }}
                </a>
                @if(!empty($doc['identifier']))
                  <br><small class="text-muted">{{ $doc['identifier'] }}</small>
                @endif
              </td>
              <td>{{ $doc['work_type'] ?? '' }}</td>
              <td>{{ $doc['creator_identity'] ?? '' }}</td>
              <td>{{ $doc['creation_date_display'] ?? '' }}</td>
              <td>{{ $doc['current_location'] ?? '' }}</td>
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
