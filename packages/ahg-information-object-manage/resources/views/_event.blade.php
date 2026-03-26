<div
  class="atom-table-modal"
  data-current-resource="{{ url('/' . ($resource->slug ?? '')) }}"
  data-required-fields="editEvents_type"
  data-delete-field-name="deleteEvents"
  data-iframe-error="{{ __('The following resources could not be created:') }}">
  <div class="alert alert-danger d-none load-error" role="alert">
    {{ __('Could not load event data.') }}
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead class="table-light">
	<tr>
          <th class="w-30">
            {{ __('Name') }}
          </th>
          <th class="w-20">
            {{ __('Role/event') }}
          </th>
          <th class="w-25">
            {{ __('Place') }}
          </th>
          <th class="w-25">
            {{ __('Date(s)') }}
          </th>
          <th>
            <span class="visually-hidden">{{ __('Actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="row-template d-none">
          <td data-field-id="editEvents_actor"></td>
          <td data-field-id="editEvents_type"></td>
          <td data-field-id="editEvents_place"></td>
          <td data-field-id="editEvents_date"></td>
          <td class="text-nowrap">
            @if(!request()->has('source'))
              <button type="button" class="btn atom-btn-white me-1 edit-row">
                <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('Edit row') }}</span>
              </button>
            @endif
            <button type="button" class="btn atom-btn-white delete-row">
              <i class="fas fa-fw fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
        @foreach($resource->eventsRelatedByobjectId ?? [] as $item)
          <tr id="{{ url('/event/' . ($item->slug ?? $item->id)) }}">
            <td data-field-id="editEvents_actor">
              @if(isset($item->actor))
                {{ $item->actor->authorized_form_of_name ?? $item->actor->title ?? '' }}
              @endif
            </td>
            <td data-field-id="editEvents_type">
              {{ $item->type ?? '' }}
            </td>
            <td data-field-id="editEvents_place">
              @if(isset($item->place))
                {{ $item->place ?? '' }}
              @endif
            </td>
            <td data-field-id="editEvents_date">
              {{ $item->date ?? '' }}
              @if(!empty($item->startDate) || !empty($item->endDate))
                ({{ $item->startDate ?? '' }} - {{ $item->endDate ?? '' }})
              @endif
            </td>
            <td class="text-nowrap">
              @if(!request()->has('source'))
                <button type="button" class="btn atom-btn-white me-1 edit-row">
                  <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                  <span class="visually-hidden">{{ __('Edit row') }}</span>
                </button>
              @endif
              <button type="button" class="btn atom-btn-white delete-row">
                <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('Delete row') }}</span>
              </button>
            </td>
          </tr>
        @endforeach
      </tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button type="button" class="btn atom-btn-white add-row">
              <i class="fas fa-plus me-1" aria-hidden="true"></i>
              {{ __('Add new') }}
            </button>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <div
    class="modal fade"
    data-bs-backdrop="static"
    tabindex="-1"
    aria-labelledby="related-events-heading"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-events-heading">
            {{ __('Event') }}
          </h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal">
            <span class="visually-hidden">{{ __('Close') }}</span>
          </button>
        </div>

        <div class="modal-body pb-2">
          @csrf

          <div class="mb-3">
            <label for="editEvents_actor" class="form-label">{{ __('Actor name') }}</label>
            <input type="text" class="form-control form-autocomplete" id="editEvents_actor" name="actor" value="{{ old('actor') }}"
              data-autocomplete-url="{{ route('actor.autocomplete') ?? '' }}">
            <input class="list" type="hidden" value="{{ route('actor.autocomplete') ?? '' }}">
          </div>

          <div class="mb-3">
            <label for="editEvents_type" class="form-label">{{ __('Event type') }}</label>
            <select class="form-select" id="editEvents_type" name="type">
              <option value="">{{ __('- Select event type -') }}</option>
              @foreach($eventTypes ?? [] as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
              @endforeach
            </select>
          </div>

          <div class="mb-3">
            <label for="editEvents_place" class="form-label">{{ __('Place') }}</label>
            <input type="text" class="form-control form-autocomplete" id="editEvents_place" name="place" value="{{ old('place') }}">
          </div>

          <div class="date">
            <div class="mb-3">
              <label for="editEvents_date" class="form-label">{{ __('Date') }}</label>
              <input type="text" class="form-control" id="editEvents_date" name="date" value="{{ old('date') }}">
            </div>
            <div class="mb-3">
              <label for="editEvents_startDate" class="form-label">{{ __('Start date') }}</label>
              <input type="text" class="form-control" id="editEvents_startDate" name="startDate" value="{{ old('startDate') }}">
            </div>
            <div class="mb-3">
              <label for="editEvents_endDate" class="form-label">{{ __('End date') }}</label>
              <input type="text" class="form-control" id="editEvents_endDate" name="endDate" value="{{ old('endDate') }}">
            </div>
          </div>

          <div class="mb-3">
            <label for="editEvents_description" class="form-label">{{ __('Event note') }}</label>
            <textarea class="form-control" id="editEvents_description" name="description" rows="3">{{ old('description') }}</textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="button" class="btn atom-btn-white modal-submit">
            {{ __('Submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
