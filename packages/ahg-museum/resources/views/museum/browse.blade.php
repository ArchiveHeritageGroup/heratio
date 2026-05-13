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
      <span class="small text-muted">{{ __('Museum objects') }}</span>
    </div>
  </div>

  @include('ahg-display::partials._active-type-bar', ['type' => 'museum'])

  <div class="d-flex flex-wrap gap-2 mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => 'Search museum objects',
        'landmarkLabel' => 'Museum object',
    ])

    <div class="d-flex flex-wrap gap-2 ms-auto">
      @php
        // One reusable dropdown body — keeps the markup tight for 10 facets.
        // $label    : button label when nothing is selected
        // $key      : query-string key (matches MuseumController::browse keys)
        // $selected : currently-applied value (null if "all")
        // $options  : list of strings for the menu
        $facets = [
            ['label' => 'Work type',       'key' => 'work_type',        'selected' => $selectedWorkType,        'options' => $workTypes],
            ['label' => 'Classification',  'key' => 'classification',   'selected' => $selectedClassification,  'options' => $classifications],
            ['label' => 'Materials',       'key' => 'materials',        'selected' => $selectedMaterials,       'options' => $materialsList],
            ['label' => 'Techniques',      'key' => 'techniques',       'selected' => $selectedTechniques,      'options' => $techniquesList],
            ['label' => 'Period',          'key' => 'period',           'selected' => $selectedPeriod,          'options' => $periods],
            ['label' => 'Style',           'key' => 'style',            'selected' => $selectedStyle,           'options' => $styles],
            ['label' => 'School',          'key' => 'school',           'selected' => $selectedSchool,          'options' => $schools],
            ['label' => 'Dynasty',         'key' => 'dynasty',          'selected' => $selectedDynasty,         'options' => $dynasties],
            ['label' => 'Cultural ctx',    'key' => 'cultural_context', 'selected' => $selectedCulturalContext, 'options' => $culturalContexts],
            ['label' => 'Creator',         'key' => 'creator_identity', 'selected' => $selectedCreator,         'options' => $creators],
        ];
      @endphp

      @foreach($facets as $f)
        @if(!empty($f['options']))
          <div class="dropdown">
            <button class="btn btn-sm atom-btn-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
              {{ $f['selected'] ?: $f['label'] }}
            </button>
            <ul class="dropdown-menu" style="max-height: 320px; overflow-y: auto;">
              <li>
                <a class="dropdown-item {{ !$f['selected'] ? 'active' : '' }}"
                   href="{{ route('museum.browse', request()->except([$f['key'], 'page'])) }}">
                  {{ 'All ' . strtolower($f['label']) . 's' }}
                </a>
              </li>
              @foreach($f['options'] as $opt)
                <li>
                  <a class="dropdown-item {{ $f['selected'] === $opt ? 'active' : '' }}"
                     href="{{ route('museum.browse', array_merge(request()->except('page'), [$f['key'] => $opt])) }}">
                    {{ $opt }}
                  </a>
                </li>
              @endforeach
            </ul>
          </div>
        @endif
      @endforeach

      @include('ahg-core::components.sort-pickers', [
          'options' => $sortOptions,
          'default' => 'alphabetic',
      ])
    </div>
  </div>

  {{-- Date range + identifier search row. GET form posts back to the same
       route so the dropdown facets above continue to work alongside it. --}}
  <form method="get" action="{{ route('museum.browse') }}"
        class="d-flex flex-wrap gap-2 align-items-end mb-3 p-2 border rounded bg-light">
    {{-- Carry across any existing facet selections / sort / subquery so
         submitting this form doesn't drop them. --}}
    @foreach(request()->except(['date_from', 'date_to', 'id_search', 'page']) as $k => $v)
      <input type="hidden" name="{{ $k }}" value="{{ is_array($v) ? '' : $v }}">
    @endforeach

    <div>
      <label class="form-label form-label-sm mb-0 small text-muted">{{ __('Created from') }}</label>
      <input type="date" name="date_from" value="{{ $selectedDateFrom }}"
             class="form-control form-control-sm" style="width: 160px;">
    </div>
    <div>
      <label class="form-label form-label-sm mb-0 small text-muted">{{ __('Created to') }}</label>
      <input type="date" name="date_to" value="{{ $selectedDateTo }}"
             class="form-control form-control-sm" style="width: 160px;">
    </div>
    <div class="flex-grow-1" style="min-width: 220px;">
      <label class="form-label form-label-sm mb-0 small text-muted">
        {{ __('Identifier / accession / barcode / object number') }}
      </label>
      <input type="text" name="id_search" value="{{ $selectedIdSearch }}"
             class="form-control form-control-sm"
             placeholder="{{ __('e.g. ACC-2024-013 or scan a barcode') }}">
    </div>
    <div class="d-flex gap-1">
      <button type="submit" class="btn btn-sm atom-btn-white">
        <i class="fas fa-filter me-1"></i>{{ __('Apply') }}
      </button>
      @if($selectedDateFrom || $selectedDateTo || $selectedIdSearch)
        <a href="{{ route('museum.browse', request()->except(['date_from', 'date_to', 'id_search', 'page'])) }}"
           class="btn btn-sm atom-btn-outline-secondary">
          <i class="fas fa-times me-1"></i>{{ __('Clear') }}
        </a>
      @endif
    </div>
  </form>

  @auth
    <div class="mb-3">
      <a href="{{ route('museum.create') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-plus me-1"></i> {{ __('Add new') }}
      </a>
    </div>
  @endauth

  @if($pager->getNbResults())
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th style="width: 60px;">{{ __('Thumbnail') }}</th>
            <th>{{ __('Title / Object') }}</th>
            <th>{{ __('Work type') }}</th>
            <th>{{ __('Creator') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Location') }}</th>
            @if(request('sort') === 'lastUpdated')
              <th>{{ __('Updated') }}</th>
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
