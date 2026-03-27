@php
  $showAdvanced = request()->has('showAdvanced') && request('showAdvanced') === '1';
  $currentQuery = request('query', request('subquery', ''));

  // Rebuild existing criteria rows from request parameters
  $criteriaRows = [];
  for ($ri = 0; $ri < 10; $ri++) {
      $sq = request("sq{$ri}");
      if ($sq !== null && trim($sq) !== '') {
          $criteriaRows[] = [
              'operator' => request("so{$ri}", 'and'),
              'query'    => $sq,
              'field'    => request("sf{$ri}", ''),
          ];
      }
  }
  // Always show at least one row
  if (empty($criteriaRows)) {
      $criteriaRows[] = [
          'operator' => 'and',
          'query'    => $currentQuery,
          'field'    => '',
      ];
  }

  // Resolve the selected collection name for the autocomplete display value
  $selectedCollectionId   = request('collection', '');
  $selectedCollectionName = '';
  if ($selectedCollectionId) {
      $selectedCollectionName = \Illuminate\Support\Facades\DB::table('information_object_i18n')
          ->where('id', $selectedCollectionId)
          ->where('culture', app()->getLocale())
          ->value('title') ?? '';
  }
@endphp

<div class="accordion mb-3 adv-search" role="search">
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading-adv-search">
      <button class="accordion-button{{ $showAdvanced ? '' : ' collapsed' }}" type="button"
              data-bs-toggle="collapse" data-bs-target="#collapse-adv-search"
              aria-expanded="{{ $showAdvanced ? 'true' : 'false' }}" aria-controls="collapse-adv-search">
        {{ __('Advanced search options') }}
      </button>
    </h2>
    <div id="collapse-adv-search" class="accordion-collapse collapse{{ $showAdvanced ? ' show' : '' }}"
         aria-labelledby="heading-adv-search">
      <div class="accordion-body">
        <form name="advanced-search-form" method="get" action="{{ route('informationobject.browse') }}">
          <input type="hidden" name="showAdvanced" value="1">

          <h5>{{ __('Find results with:') }}</h5>

          <div class="criteria mb-4" id="adv-search-criteria">
            {{-- Render existing criteria rows --}}
            @foreach($criteriaRows as $idx => $cr)
            <div class="criterion row align-items-center" data-index="{{ $idx }}">
              <div class="col-xl-auto mb-3 adv-search-boolean">
                <select class="form-select" name="so{{ $idx }}">
                  <option value="and"{{ $cr['operator'] === 'and' ? ' selected' : '' }}>{{ __('and') }}</option>
                  <option value="or"{{ $cr['operator'] === 'or' ? ' selected' : '' }}>{{ __('or') }}</option>
                  <option value="not"{{ $cr['operator'] === 'not' ? ' selected' : '' }}>{{ __('not') }}</option>
                </select>
              </div>

              <div class="col-xl-auto flex-grow-1 mb-3">
                <input class="form-control" type="text"
                       aria-label="{{ __('Search') }}" placeholder="{{ __('Search') }}"
                       name="sq{{ $idx }}" value="{{ $cr['query'] }}">
              </div>

              <div class="col-xl-auto mb-3 text-center">
                <span class="form-text">{{ __('in') }}</span>
              </div>

              <div class="col-xl-auto mb-3">
                <select class="form-select" name="sf{{ $idx }}">
                  <option value="">{{ __('Any field') }}</option>
                  <option value="title"{{ $cr['field'] === 'title' ? ' selected' : '' }}>{{ __('Title') }}</option>
                  <option value="identifier"{{ $cr['field'] === 'identifier' ? ' selected' : '' }}>{{ __('Identifier') }}</option>
                  <option value="referenceCode"{{ $cr['field'] === 'referenceCode' ? ' selected' : '' }}>{{ __('Reference code') }}</option>
                  <option value="scopeAndContent"{{ $cr['field'] === 'scopeAndContent' ? ' selected' : '' }}>{{ __('Scope and content') }}</option>
                  <option value="extentAndMedium"{{ $cr['field'] === 'extentAndMedium' ? ' selected' : '' }}>{{ __('Extent and medium') }}</option>
                  <option value="archivalHistory"{{ $cr['field'] === 'archivalHistory' ? ' selected' : '' }}>{{ __('Archival history') }}</option>
                  <option value="acquisition"{{ $cr['field'] === 'acquisition' ? ' selected' : '' }}>{{ __('Acquisition') }}</option>
                  <option value="creatorSearch"{{ $cr['field'] === 'creatorSearch' ? ' selected' : '' }}>{{ __('Creator') }}</option>
                  <option value="subjectSearch"{{ $cr['field'] === 'subjectSearch' ? ' selected' : '' }}>{{ __('Subject') }}</option>
                  <option value="placeSearch"{{ $cr['field'] === 'placeSearch' ? ' selected' : '' }}>{{ __('Place') }}</option>
                  <option value="genreSearch"{{ $cr['field'] === 'genreSearch' ? ' selected' : '' }}>{{ __('Genre') }}</option>
                  <option value="noteContent"{{ $cr['field'] === 'noteContent' ? ' selected' : '' }}>{{ __('Notes') }}</option>
                  <option value="arrangement"{{ $cr['field'] === 'arrangement' ? ' selected' : '' }}>{{ __('Arrangement') }}</option>
                  <option value="accessConditions"{{ $cr['field'] === 'accessConditions' ? ' selected' : '' }}>{{ __('Access conditions') }}</option>
                  <option value="reproductionConditions"{{ $cr['field'] === 'reproductionConditions' ? ' selected' : '' }}>{{ __('Reproduction conditions') }}</option>
                  <option value="physicalCharacteristics"{{ $cr['field'] === 'physicalCharacteristics' ? ' selected' : '' }}>{{ __('Physical characteristics') }}</option>
                  <option value="findingAids"{{ $cr['field'] === 'findingAids' ? ' selected' : '' }}>{{ __('Finding aids') }}</option>
                  <option value="locationOfOriginals"{{ $cr['field'] === 'locationOfOriginals' ? ' selected' : '' }}>{{ __('Location of originals') }}</option>
                  <option value="locationOfCopies"{{ $cr['field'] === 'locationOfCopies' ? ' selected' : '' }}>{{ __('Location of copies') }}</option>
                  <option value="relatedUnits"{{ $cr['field'] === 'relatedUnits' ? ' selected' : '' }}>{{ __('Related units of description') }}</option>
                  <option value="rules"{{ $cr['field'] === 'rules' ? ' selected' : '' }}>{{ __('Rules') }}</option>
                  <option value="sources"{{ $cr['field'] === 'sources' ? ' selected' : '' }}>{{ __('Sources') }}</option>
                  <option value="appraisal"{{ $cr['field'] === 'appraisal' ? ' selected' : '' }}>{{ __('Appraisal') }}</option>
                  <option value="accruals"{{ $cr['field'] === 'accruals' ? ' selected' : '' }}>{{ __('Accruals') }}</option>
                  <option value="alternateTitle"{{ $cr['field'] === 'alternateTitle' ? ' selected' : '' }}>{{ __('Alternate title') }}</option>
                  <option value="edition"{{ $cr['field'] === 'edition' ? ' selected' : '' }}>{{ __('Edition') }}</option>
                </select>
              </div>

              <div class="col-xl-auto mb-3">
                <a href="#" class="delete-criterion" aria-label="{{ __('Delete criterion') }}"
                   onclick="event.preventDefault(); var row = this.closest('.criterion'); var container = row.parentNode; if (container.querySelectorAll('.criterion').length > 1) { row.remove(); } else { row.querySelector('input[type=text]').value = ''; }">
                  <i aria-hidden="true" class="fas fa-times text-muted"></i>
                </a>
              </div>
            </div>
            @endforeach

            <div class="add-new-criteria mb-3">
              <button type="button" class="btn atom-btn-white" id="add-field-search-btn">
                {{ __('Add criterion') }}
              </button>
            </div>
          </div>

          <h5>{{ __('Limit results to:') }}</h5>

          <div class="criteria mb-4">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="repo-select" class="form-label">{{ __('Repository') }}</label>
                  <select class="form-select" name="repo" id="repo-select">
                    <option value="">{{ __('— Any —') }}</option>
                    @if(isset($repositories))
                      @foreach($repositories as $repo)
                        <option value="{{ $repo->id }}"{{ request('repo') == $repo->id ? ' selected' : '' }}>
                          {{ $repo->name }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </div>
              </div>

              <div class="col-md-6">
                @include('ahg-core::components.autocomplete', [
                    'name'         => 'collection',
                    'label'        => __('Top-level description'),
                    'route'        => 'informationobject.autocomplete',
                    'value'        => $selectedCollectionId,
                    'displayValue' => $selectedCollectionName,
                    'placeholder'  => __('Type to search for a collection...'),
                    'queryParam'   => 'query',
                    'idField'      => 'id',
                    'nameField'    => 'name',
                ])
              </div>
            </div>
          </div>

          <h5>{{ __('Filter results by:') }}</h5>

          <div class="criteria mb-4">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="level-filter-select" class="form-label">{{ __('Level of description') }}</label>
                  <select class="form-select" name="level" id="level-filter-select">
                    <option value="">{{ __('— Any —') }}</option>
                    @if(isset($levelsOfDescription))
                      @foreach($levelsOfDescription as $lod)
                        <option value="{{ $lod->id }}"{{ request('level') == $lod->id ? ' selected' : '' }}>
                          {{ $lod->name }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="mb-3">
                  <label for="has-digital-select" class="form-label">{{ __('Digital objects') }}</label>
                  <select class="form-select" name="hasDigital" id="has-digital-select">
                    <option value="">{{ __('— Any —') }}</option>
                    <option value="1"{{ request('hasDigital') === '1' ? ' selected' : '' }}>{{ __('Yes') }}</option>
                    <option value="0"{{ request('hasDigital') === '0' ? ' selected' : '' }}>{{ __('No') }}</option>
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="mb-3">
                  <label for="sector-filter-select" class="form-label">{{ __('Sector') }}</label>
                  <select class="form-select" name="type" id="sector-filter-select">
                    <option value="">{{ __('— Any —') }}</option>
                    <option value="archive"{{ request('type') === 'archive' ? ' selected' : '' }}>{{ __('Archive') }}</option>
                    <option value="library"{{ request('type') === 'library' ? ' selected' : '' }}>{{ __('Library') }}</option>
                    <option value="museum"{{ request('type') === 'museum' ? ' selected' : '' }}>{{ __('Museum') }}</option>
                    <option value="gallery"{{ request('type') === 'gallery' ? ' selected' : '' }}>{{ __('Gallery') }}</option>
                    <option value="photos"{{ request('type') === 'photos' ? ' selected' : '' }}>{{ __('Photos') }}</option>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="copyright-status-select" class="form-label">{{ __('Copyright status') }}</label>
                  <select class="form-select" name="copyrightStatus" id="copyright-status-select">
                    <option value="">{{ __('— Any —') }}</option>
                    @if(isset($copyrightStatuses))
                      @foreach($copyrightStatuses as $cs)
                        <option value="{{ $cs->id }}"{{ request('copyrightStatus') == $cs->id ? ' selected' : '' }}>
                          {{ $cs->name }}
                        </option>
                      @endforeach
                    @endif
                  </select>
                </div>
              </div>

              <div class="col-md-4">
                <div class="mb-3">
                  <label for="finding-aid-select" class="form-label">{{ __('Finding aid') }}</label>
                  <select class="form-select" name="findingAidStatus" id="finding-aid-select">
                    <option value="">{{ __('— Any —') }}</option>
                    <option value="yes"{{ request('findingAidStatus') === 'yes' ? ' selected' : '' }}>{{ __('Yes') }}</option>
                    <option value="no"{{ request('findingAidStatus') === 'no' ? ' selected' : '' }}>{{ __('No') }}</option>
                  </select>
                </div>
              </div>
            </div>

            <fieldset class="col-12">
              <legend class="visually-hidden">{{ __('Top-level description filter') }}</legend>
              <div class="d-grid d-sm-block">
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="topLevel" id="topLevel-top" value="1"
                    {{ !request()->has('topLevel') || request('topLevel') === '1' ? 'checked' : '' }}>
                  <label class="form-check-label" for="topLevel-top">{{ __('Top-level descriptions') }}</label>
                </div>
                <div class="form-check d-inline-block">
                  <input class="form-check-input" type="radio" name="topLevel" id="topLevel-all" value="0"
                    {{ request('topLevel') === '0' ? 'checked' : '' }}>
                  <label class="form-check-label" for="topLevel-all">{{ __('All descriptions') }}</label>
                </div>
              </div>
            </fieldset>
          </div>

          <h5>{{ __('Filter by date range:') }}</h5>

          <div class="criteria row mb-2">
            <div class="col-md-4 start-date">
              <div class="mb-3">
                <label for="adv-start-date" class="form-label">{{ __('Start') }}</label>
                <input type="date" class="form-control" id="adv-start-date" name="startDate"
                       value="{{ request('startDate') }}" placeholder="YYYY-MM-DD" max="9999-12-31">
              </div>
            </div>

            <div class="col-md-4 end-date">
              <div class="mb-3">
                <label for="adv-end-date" class="form-label">{{ __('End') }}</label>
                <input type="date" class="form-control" id="adv-end-date" name="endDate"
                       value="{{ request('endDate') }}" placeholder="YYYY-MM-DD" max="9999-12-31">
              </div>
            </div>

            <fieldset class="col-md-4 date-type">
              <legend class="fs-6">
                <span>{{ __('Results') }}</span>
                <button type="button" class="btn btn-link mb-1" data-bs-toggle="tooltip" data-bs-placement="auto"
                        title="{{ __('Use these options to specify how the date range returns results. &quot;Exact&quot; means that the start and end dates of descriptions returned must fall entirely within the date range entered. &quot;Overlapping&quot; means that any description whose start or end dates touch or overlap the target date range will be returned.') }}">
                  <i aria-hidden="true" class="fas fa-question-circle text-muted"></i>
                </button>
              </legend>
              <div class="d-grid d-sm-block">
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="rangeType" id="adv-search-date-range-inclusive"
                         value="inclusive" {{ request('rangeType', 'inclusive') === 'inclusive' ? 'checked' : '' }}>
                  <label class="form-check-label" for="adv-search-date-range-inclusive">{{ __('Overlapping') }}</label>
                </div>
                <div class="form-check d-inline-block">
                  <input class="form-check-input" type="radio" name="rangeType" id="adv-search-date-range-exact"
                         value="exact" {{ request('rangeType') === 'exact' ? 'checked' : '' }}>
                  <label class="form-check-label" for="adv-search-date-range-exact">{{ __('Exact') }}</label>
                </div>
              </div>
            </fieldset>
          </div>

          <ul class="actions mb-1 nav gap-2 justify-content-center">
            <li><input type="button" class="btn atom-btn-outline-light reset" value="{{ __('Reset') }}"
                       onclick="this.closest('form').reset()"></li>
            <li><input type="submit" class="btn atom-btn-outline-light" value="{{ __('Search') }}"></li>
          </ul>

        </form>
      </div>
    </div>
  </div>
</div>

{{-- Add criterion JS: clones the last row and increments field name indices --}}
@push('js')
<script>
(function () {
    'use strict';
    var btn = document.getElementById('add-field-search-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var container = document.getElementById('adv-search-criteria');
        var rows = container.querySelectorAll('.criterion');
        var lastRow = rows[rows.length - 1];
        var lastIndex = parseInt(lastRow.getAttribute('data-index') || '0', 10);
        var newIndex = lastIndex + 1;

        var clone = lastRow.cloneNode(true);
        clone.setAttribute('data-index', newIndex);

        // Update name attributes: so{old} -> so{new}, sq{old} -> sq{new}, sf{old} -> sf{new}
        clone.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/\d+$/, newIndex);
        });

        // Clear the text input value
        var textInput = clone.querySelector('input[type="text"]');
        if (textInput) textInput.value = '';

        // Reset the field select to "Any field"
        var fieldSelect = clone.querySelector('select[name^="sf"]');
        if (fieldSelect) fieldSelect.selectedIndex = 0;

        // Reset the operator select to "and"
        var opSelect = clone.querySelector('select[name^="so"]');
        if (opSelect) opSelect.selectedIndex = 0;

        // Insert before the add-new-criteria div
        var addDiv = container.querySelector('.add-new-criteria');
        container.insertBefore(clone, addDiv);
    });
})();
</script>
@endpush
