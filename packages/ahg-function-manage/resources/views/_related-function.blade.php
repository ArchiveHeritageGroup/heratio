<h3 class="fs-6 mb-2">
  {{ __('Related functions') }}
</h3>

<div
  class="atom-table-modal"
  data-current-resource="{{ route('function.show', ['slug' => $resource->slug]) }}"
  data-required-fields="{{ $form->resource->renderId() . ',' . $form->type->renderId() }}"
  data-delete-field-name="deleteRelations"
  data-iframe-error="{{ __('The following resources could not be created:') }}">
  <div class="alert alert-danger d-none load-error" role="alert">
    {{ __('Could not load relation data.') }}
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead class="table-light">
	<tr>
          <th class="w-30">
            {{ __('Name') }}
          </th>
          <th class="w-20">
            {{ __('Category') }}
          </th>
          <th class="w-30">
            {{ __('Description') }}
          </th>
          <th class="w-20">
            {{ __('Dates') }}
          </th>
          <th>
            <span class="visually-hidden">{{ __('Actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="row-template d-none">
          <td data-field-id="{{ $form->resource->renderId() }}"></td>
          <td data-field-id="{{ $form->type->renderId() }}"></td>
          <td data-field-id="{{ $form->description->renderId() }}"></td>
          <td data-field-id="{{ $form->date->renderId() }}"></td>
          <td class="text-nowrap">
            <button type="button" class="btn atom-btn-white me-1 edit-row">
              <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Edit row') }}</span>
            </button>
            <button type="button" class="btn atom-btn-white delete-row">
              <i class="fas fa-fw fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
        @foreach($isdf->relatedFunction as $item)
          <tr id="{{ route('relation.show', ['slug' => $item->slug]) }}">
            <td data-field-id="{{ $form->resource->renderId() }}">
              @if($resource->id == $item->objectId)
                {{ $item->subject->authorized_form_of_name ?? $item->subject->title ?? '' }}
              @else
                {{ $item->object->authorized_form_of_name ?? $item->object->title ?? '' }}
              @endif
            </td>
            <td data-field-id="{{ $form->type->renderId() }}">
              {{ $item->type }}
            </td>
            <td data-field-id="{{ $form->description->renderId() }}">
              {{ $item->description }}
            </td>
            <td data-field-id="{{ $form->date->renderId() }}">
              {{ \AhgCore\Helpers\DateHelper::renderDateStartEnd($item->date, $item->startDate, $item->endDate) }}
            </td>
            <td class="text-nowrap">
              <button type="button" class="btn atom-btn-white me-1 edit-row">
                <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('Edit row') }}</span>
              </button>
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
    aria-labelledby="related-function-heading"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-function-heading">
            {{ __('Related function') }}
          </h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal">
            <span class="visually-hidden">{{ __('Close') }}</span>
          </button>
        </div>

        <div class="modal-body pb-2">
          <div class="alert alert-danger d-none validation-error" role="alert">
            {{ __('Please complete all required fields.') }}
          </div>

          {!! $form->renderHiddenFields() !!}

          @php $extraInputs = '<input class="list" type="hidden" value="'
                  .route('function.autocomplete')
                  .'">';
              echo render_field(
                  $form->resource
                      ->label(__('Authorized form of name'))
                      ->help(__(
                          '"Record the authorised form of name and any unique identifier of the related function." (ISDF 5.3.1)'
                      )),
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          @php echo render_field($form->type->label(__('Category'))->help(__(
              '"Record a general category into which the relationship being described falls." (ISDF 5.3.2) Select a category from the drop-down menu: hierarchical, temporal or associative.'
          ))); @endphp

          @php echo render_field($form->description->help(__(
              '"Record a precise description of the nature of the relationship between the function being described and the related function." (ISDF 5.3.3) Note that the text entered in this field will also appear in the related function.'
          ))); @endphp

          <div class="date">
            @php echo render_field($form->date); @endphp
            @php echo render_field($form->startDate); @endphp
            @php echo render_field($form->endDate); @endphp
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
