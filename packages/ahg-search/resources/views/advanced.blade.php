@extends('theme::layouts.1col')

@section('title', __('Advanced search'))

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ __('Advanced search') }}</h1>
      <span class="small">{{ __('Archival description') }}</span>
    </div>
  </div>

  <div class="accordion mb-3 adv-search" role="search">
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading-adv-search">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-adv-search" aria-expanded="true" aria-controls="collapse-adv-search">
          {{ __('Advanced search options') }}
        </button>
      </h2>
      <div id="collapse-adv-search" class="accordion-collapse collapse show" aria-labelledby="heading-adv-search">
        <div class="accordion-body">
          <form action="{{ route('search') }}" method="get" name="advanced-search-form">

            <h5>{{ __('Find results with:') }}</h5>

            <div class="criteria mb-4">
              <div class="criterion row align-items-center">
                <div class="col-xl-auto flex-grow-1 mb-3">
                  <input class="form-control" type="text" aria-label="{{ __('Search') }}" placeholder="{{ __('Search') }}" name="q" value="{{ $query }}">
                </div>

                <div class="col-xl-auto mb-3 text-center">
                  <span class="form-text">{{ __('in') }}</span>
                </div>

                <div class="col-xl-auto mb-3">
                  <select class="form-select" name="searchField">
                    <option value="">{{ __('Any field') }}</option>
                    <option value="title">{{ __('Title') }}</option>
                    <option value="scopeAndContent">{{ __('Scope and content') }}</option>
                    <option value="identifier">{{ __('Identifier') }}</option>
                    <option value="referenceCode">{{ __('Reference code') }}</option>
                    <option value="creator">{{ __('Creator') }}</option>
                  </select>
                </div>
              </div>
            </div>

            <h5>{{ __('Limit results to:') }}</h5>

            <div class="criteria mb-4">
              <div class="mb-3">
                <label for="adv-repository" class="form-label">{{ __('Repository') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select id="adv-repository" name="repository" class="form-select">
                  <option value="">{{ __('-- Any repository --') }}</option>
                  @foreach($repositories as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <h5>{{ __('Filter results by:') }}</h5>

            <div class="criteria mb-4">
              <div class="row">
                <div class="col-md-4">
                  <label for="adv-level" class="form-label">{{ __('Level of description') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select id="adv-level" name="level" class="form-select">
                    <option value="">{{ __('-- Any level --') }}</option>
                    @foreach($levels as $id => $name)
                      <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                  </select>
                </div>

                <div class="col-md-4">
                  <label for="adv-mediaType" class="form-label">{{ __('Digital object available') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select id="adv-mediaType" name="hasDigitalObject" class="form-select">
                    <option value="">{{ __('No') }}</option>
                    <option value="1">{{ __('Yes') }}</option>
                  </select>
                </div>
              </div>

              <fieldset class="mt-3">
                <legend class="visually-hidden">{{ __('Top-level description filter') }} <span class="badge bg-secondary ms-1">Optional</span></legend>
                <div class="d-grid d-sm-block">
                  <div class="form-check d-inline-block me-2">
                    <input class="form-check-input" type="radio" name="topLod" id="adv-search-top-lod-1" value="1">
                    <label class="form-check-label" for="adv-search-top-lod-1">{{ __('Top-level descriptions') }}</label>
                  </div>
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="radio" name="topLod" id="adv-search-top-lod-0" value="0" checked>
                    <label class="form-check-label" for="adv-search-top-lod-0">{{ __('All descriptions') }}</label>
                  </div>
                </div>
              </fieldset>
            </div>

            <h5>{{ __('Filter by date range:') }}</h5>

            <div class="criteria row mb-2">
              <div class="col-md-4 start-date">
                <label for="adv-dateFrom" class="form-label">{{ __('Start') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" id="adv-dateFrom" name="dateFrom" class="form-control">
              </div>

              <div class="col-md-4 end-date">
                <label for="adv-dateTo" class="form-label">{{ __('End') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" id="adv-dateTo" name="dateTo" class="form-control">
              </div>

              <fieldset class="col-md-4 date-type">
                <legend class="fs-6">{{ __('Results') }} <span class="badge bg-secondary ms-1">Optional</span></legend>
                <div class="d-grid d-sm-block">
                  <div class="form-check d-inline-block me-2">
                    <input class="form-check-input" type="radio" name="rangeType" id="adv-search-date-range-inclusive" value="inclusive" checked>
                    <label class="form-check-label" for="adv-search-date-range-inclusive">{{ __('Overlapping') }}</label>
                  </div>
                  <div class="form-check d-inline-block">
                    <input class="form-check-input" type="radio" name="rangeType" id="adv-search-date-range-exact" value="exact">
                    <label class="form-check-label" for="adv-search-date-range-exact">{{ __('Exact') }}</label>
                  </div>
                </div>
              </fieldset>
            </div>

            <ul class="actions mb-1 nav gap-2 justify-content-center">
              <li><input type="button" class="btn atom-btn-outline-light reset" value="{{ __('Reset') }}"></li>
              <li><input type="submit" class="btn atom-btn-outline-light" value="{{ __('Search') }}"></li>
            </ul>

          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
