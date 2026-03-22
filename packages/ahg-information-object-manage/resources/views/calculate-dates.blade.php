@php decorate_with('layout_2col'); @endphp

@php slot('sidebar'); @endphp
  @php include_component('informationobject', 'contextMenu'); @endphp
@php end_slot(); @endphp

@php slot('title'); @endphp

  <h1>{{ __('Calculate dates') }}</h1>

@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @php echo $form->renderFormTag(url_for([$resource, 'module' => 'informationobject', 'action' => 'calculateDates'])); @endphp

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      @if(count($events))
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
                        id="eventIdOrTypeId-@php echo $eventId; @endphp"
                        value="@php echo $eventId; @endphp">
                      <label for="eventIdOrTypeId-@php echo $eventId; @endphp" class="form-check-label">
                        @php echo $eventName; @endphp
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
      @endforeach
      @if(count($descendantEventTypes))
        <div class="accordion-item">
          <h2 class="accordion-header" id="create-heading">
            <button class="accordion-button@php echo count($events) ? ' collapsed' : ''; @endphp" type="button" data-bs-toggle="collapse" data-bs-target="#create-collapse" aria-expanded="@php echo count($events) ? 'false' : 'true'; @endphp" aria-controls="create-collapse">
              {{ __('Create a new date range') }}
            </button>
          </h2>
          <div id="create-collapse" class="accordion-collapse collapse@php echo count($events) ? '' : ' show'; @endphp" aria-labelledby="create-heading">
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
                      id="eventIdOrTypeId-@php echo $eventTypeId; @endphp"
                      value="@php echo $eventTypeId; @endphp">
                    <label for="eventIdOrTypeId-@php echo $eventTypeId; @endphp" class="form-check-label">
                      @php echo $eventTypeName; @endphp
                    </label>
                  </div>
                @endforeach
              </fieldset>
            </div>
          </div>
        </div>
      @endforeach
    </div>
    
    <div class="alert alert-info" role="alert">
      {{ __('Note: While the date range update is running, the selected description should not be edited.') }}
      {{ __('You can check %1% page to determine the current status of the update job.',
        ['%1%' => link_to(__('Manage jobs'), ['module' => 'jobs', 'action' => 'browse'], ['class' => 'alert-link'])]) }}
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>@php echo link_to(__('Cancel'), [$resource, 'module' => 'informationobject'], ['class' => 'btn atom-btn-outline-light', 'role' => 'button']); @endphp</li>
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Continue') }}"></li>
    </ul>

  </form>

@php end_slot(); @endphp
