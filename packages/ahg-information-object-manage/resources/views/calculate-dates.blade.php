@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')

  <h1>{{ __('Calculate dates') }}</h1>

@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $e)
        <p>{{ $e }}</p>
      @endforeach
    </div>
  @endif

  <form action="{{ route('informationobject.calculateDates', $resource->slug) }}" method="post">
    @csrf

    <div class="accordion mb-3">
      @if(count($events ?? []))
        <div class="accordion-item">
          <h2 class="accordion-header" id="existing-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#existing-collapse" aria-expanded="true" aria-controls="existing-collapse">
              {{ __('Update an existing date range') }}
            </button>
          </h2>
          <div id="existing-collapse" class="accordion-collapse collapse show" aria-labelledby="existing-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <fieldset aria-describedby="calculate-dates-alert">
                  <legend class="fs-6">
                    {{ __('Select a date range to overwrite:') }}
                  </legend>
                  @foreach($events as $eventId => $eventName)
                    <div class="form-check">
                      <input
                        type="radio"
                        name="eventIdOrTypeId"
                        class="form-check-input"
                        id="eventIdOrTypeId-{{ $eventId }}"
                        value="{{ $eventId }}">
                      <label for="eventIdOrTypeId-{{ $eventId }}" class="form-check-label">
                        {{ $eventName }}
                      </label>
                    </div>
                  @endforeach
                </fieldset>
              </div>
              <div class="alert alert-warning mb-0" id="calculate-dates-alert">
                {{ __('Updating an existing date range will permanently overwrite the current dates.') }}
              </div>
            </div>
          </div>
        </div>
      @endif
      @if(count($descendantEventTypes ?? []))
        <div class="accordion-item">
          <h2 class="accordion-header" id="create-heading">
            <button class="accordion-button{{ count($events ?? []) ? ' collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#create-collapse" aria-expanded="{{ count($events ?? []) ? 'false' : 'true' }}" aria-controls="create-collapse">
              {{ __('Create a new date range') }}
            </button>
          </h2>
          <div id="create-collapse" class="accordion-collapse collapse{{ count($events ?? []) ? '' : ' show' }}" aria-labelledby="create-heading">
            <div class="accordion-body">
              <fieldset>
                <legend class="fs-6">
                  {{ __('Select the new date type:') }}
                </legend>
                @foreach($descendantEventTypes as $eventTypeId => $eventTypeName)
                  <div class="form-check">
                    <input
                      type="radio"
                      name="eventIdOrTypeId"
                      class="form-check-input"
                      id="eventIdOrTypeId-{{ $eventTypeId }}"
                      value="{{ $eventTypeId }}">
                    <label for="eventIdOrTypeId-{{ $eventTypeId }}" class="form-check-label">
                      {{ $eventTypeName }}
                    </label>
                  </div>
                @endforeach
              </fieldset>
            </div>
          </div>
        </div>
      @endif
    </div>

    <div class="alert alert-info" role="alert">
      {{ __('Note: While the date range update is running, the selected description should not be edited.') }}
      {!! __('You can check %1% page to determine the current status of the update job.',
        ['%1%' => '<a href="' . route('jobs.browse') . '" class="alert-link">' . __('Manage jobs') . '</a>']) !!}
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Continue') }}"></li>
    </ul>

  </form>

@endsection
