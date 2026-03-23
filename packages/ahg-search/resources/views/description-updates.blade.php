@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-newspaper me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0" aria-describedby="heading-label">
        @if(isset($pager) && $pager->getNbResults())
          {{ __('Showing %1% results', ['%1%' => $pager->getNbResults()]) }}
        @else
          {{ __('No results found') }}
        @endif
      </h1>
      <span class="small" id="heading-label">
        {{ __('Newest additions') }}
      </span>
    </div>
  </div>
@endsection

@section('content')
  {{-- Filter form (accordion) --}}
  <div class="accordion mb-3 adv-search" role="search">
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading-adv-search">
        <button class="accordion-button{{ $showForm ? '' : ' collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-adv-search" aria-expanded="{{ $showForm ? 'true' : 'false' }}" aria-controls="collapse-adv-search">
          {{ __('Filter options') }}
        </button>
      </h2>
      <div id="collapse-adv-search" class="accordion-collapse collapse{{ $showForm ? ' show' : '' }}" aria-labelledby="heading-adv-search">
        <div class="accordion-body">
          <form action="{{ route('search.descriptionUpdates') }}" method="get" name="advanced-search-form">

            <input type="hidden" name="showForm" value="1"/>

            <h5>{{ __('Filter results by:') }}</h5>

            <div class="criteria row mb-2">
              <div class="col-md-6">
                <label for="className" class="form-label">{{ __('Type') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="className" id="className" class="form-select">
                  @foreach($entityTypes as $value => $label)
                    <option value="{{ $value }}" {{ $className === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <fieldset class="col-md-6">
                <legend class="fs-6">{{ __('Date of') }} <span class="badge bg-secondary ms-1">Optional</span></legend>
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="dateOf" id="dateOf-created" value="CREATED_AT" {{ $dateOf === 'CREATED_AT' ? 'checked' : '' }}>
                  <label class="form-check-label" for="dateOf-created">{{ __('Creation') }}</label>
                </div>
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="dateOf" id="dateOf-updated" value="UPDATED_AT" {{ $dateOf !== 'CREATED_AT' ? 'checked' : '' }}>
                  <label class="form-check-label" for="dateOf-updated">{{ __('Revision') }}</label>
                </div>
              </fieldset>

              <fieldset class="col-md-6 mt-2">
                <legend class="fs-6">{{ __('Publication status') }} <span class="badge bg-secondary ms-1">Optional</span></legend>
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="publicationStatus" id="pubAll" value="" {{ $publicationStatus === '' ? 'checked' : '' }}>
                  <label class="form-check-label" for="pubAll">{{ __('All') }}</label>
                </div>
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="publicationStatus" id="pubDraft" value="draft" {{ $publicationStatus === 'draft' ? 'checked' : '' }}>
                  <label class="form-check-label" for="pubDraft">{{ __('Draft') }}</label>
                </div>
                <div class="form-check d-inline-block me-2">
                  <input class="form-check-input" type="radio" name="publicationStatus" id="pubPublished" value="published" {{ $publicationStatus === 'published' ? 'checked' : '' }}>
                  <label class="form-check-label" for="pubPublished">{{ __('Published') }}</label>
                </div>
              </fieldset>
            </div>

            <h5>{{ __('Filter by date range:') }}</h5>

            <div class="criteria row mb-2">
              <div class="col-md-6 start-date">
                <label for="dateStart" class="form-label">{{ __('Start') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" id="dateStart" name="dateStart" class="form-control" value="{{ $dateStart }}">
              </div>

              <div class="col-md-6 end-date">
                <label for="dateEnd" class="form-label">{{ __('End') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="date" id="dateEnd" name="dateEnd" class="form-control" value="{{ $dateEnd }}">
              </div>
            </div>

            <ul class="actions mb-1 nav gap-2 justify-content-center">
              <li><input type="submit" class="btn atom-btn-outline-light" value="{{ __('Search') }}"></li>
              <li><input type="button" class="btn atom-btn-outline-light reset" value="{{ __('Reset') }}"></li>
            </ul>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Results table --}}
  <div class="table-responsive mb-3">
    @if(isset($results) && $results->count() > 0)
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th class="w-40">{{ __('Title') }}</th>
            <th class="w-40">{{ __('Repository') }}</th>
            @if($dateOf === 'CREATED_AT')
              <th class="w-20">{{ __('Created') }}</th>
            @else
              <th class="w-20">{{ __('Updated') }}</th>
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach($results as $row)
            <tr>
              <td>
                @if($row->slug)
                  <a href="/{{ $row->slug }}">{{ $row->title }}</a>
                @else
                  {{ $row->title }}
                @endif
              </td>
              <td>{{ $row->repository ?? '' }}</td>
              <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('Y-m-d H:i') : '' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @elseif(isset($results))
      <div class="p-3">
        {{ __("We couldn't find any results matching your search.") }}
      </div>
    @endif
  </div>

  @if(isset($pager) && $pager->getNbResults())
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif
@endsection
