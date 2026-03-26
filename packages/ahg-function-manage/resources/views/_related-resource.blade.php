<h3 class="fs-6 mb-2">
  {{ __('Related resources') }}
</h3>

<div
  class="atom-table-modal"
  data-current-resource="{{ route('function.show', ['slug' => $resource->slug]) }}"
  data-required-fields="{{ $form->resource->renderId() }}"
  data-delete-field-name="deleteRelations"
  data-iframe-error="{{ __('The following resources could not be created:') }}">
  <div class="alert alert-danger d-none load-error" role="alert">
    {{ __('Could not load relation data.') }}
  </div>

  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead class="table-light">
	<tr>
          <th class="w-30">
            {{ __('Identifier/title') }}
          </th>
          <th class="w-40">
            {{ __('Nature of relationship') }}
          </th>
          <th class="w-30">
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
        @foreach($isdf->relatedResource as $item)
          <tr id="{{ route('relation.show', ['slug' => $item->slug]) }}">
            <td data-field-id="{{ $form->resource->renderId() }}">
              {{ $item->object->authorized_form_of_name ?? $item->object->title ?? '' }}
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
          <td colspan="4">
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
    aria-labelledby="related-resource-heading"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-resource-heading">
            {{ __('Related resource') }}
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
                  .route('informationobject.autocomplete')
                  .'">';
              echo render_field(
                  $form->resource
                      ->label(__('Title'))
                      ->help(__(
                        'Select the title from the drop-down menu; enter the identifier or the first few letters to narrow the choices. (ISDF 6.1)'
                      )),
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          @php echo render_field($form->description->label(__('Nature of relationship'))); @endphp

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
