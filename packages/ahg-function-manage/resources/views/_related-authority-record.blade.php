<h3 class="fs-6 mb-2">
  {{ __('Related authority records') }}
</h3>

<div
  class="atom-table-modal"
  data-current-resource="@php echo url_for([$resource]); @endphp"
  data-required-fields="@php echo $form->resource->renderId(); @endphp"
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
            {{ __('Identifier/name') }}
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
          <td data-field-id="@php echo $form->resource->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->description->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->date->renderId(); @endphp"></td>
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
        @foreach($isdf->relatedAuthorityRecord as $item)
          <tr id="@php echo url_for([$item, 'module' => 'relation']); @endphp">
            <td data-field-id="@php echo $form->resource->renderId(); @endphp">
              @php echo render_title($item->object); @endphp
            </td>
            <td data-field-id="@php echo $form->description->renderId(); @endphp">
              @php echo render_value_inline($item->description); @endphp
            </td>
            <td data-field-id="@php echo $form->date->renderId(); @endphp">
              @php echo render_value_inline(Qubit::renderDateStartEnd(
                  $item->date,
                  $item->startDate,
                  $item->endDate
              )); @endphp
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
    aria-labelledby="related-authority-record-heading"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-authority-record-heading">
            {{ __('Related authority record') }}
          </h4>
          <button type="button" class="btn-close" data-bs-dismiss="modal">
            <span class="visually-hidden">{{ __('Close') }}</span>
          </button>
        </div>

        <div class="modal-body pb-2">
          <div class="alert alert-danger d-none validation-error" role="alert">
            {{ __('Please complete all required fields.') }}
          </div>

          @php echo $form->renderHiddenFields(); @endphp

          @php $extraInputs = '<input class="list" type="hidden" value="'
                  .url_for(['module' => 'actor', 'action' => 'autocomplete', 'showOnlyActors' => 'true'])
                  .'">';
              echo render_field(
                  $form->resource
                      ->label(__('Authorized form of name'))
                      ->help(__(
                        'Select the name from the drop-down menu; enter the identifier or the first few letters to narrow the choices. (ISDF 6.1)'
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
          <button type="button" class="btn btn-success modal-submit">
            {{ __('Submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
