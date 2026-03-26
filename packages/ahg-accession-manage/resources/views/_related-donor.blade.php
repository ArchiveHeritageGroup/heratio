<h3 class="fs-6 mb-2">
  {{ __('Related donors') }}
</h3>

<div
  class="atom-table-modal"
  data-current-resource="{{ isset($resource->id) ? route('accession.show', ['slug' => $resource->slug]) : '' }}"
  data-required-fields="@php echo $form->resource->renderId(); @endphp"
  data-delete-field-name="deleteRelations"
  data-iframe-error="{{ __('The following resources could not be created:') }}">
  <div class="alert alert-danger d-none load-error" role="alert">
    {{ __('Could not load relation data.') }}
  </div>

  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead class="table-light">
	<tr>
          <th class="w-100">
            {{ __('Name') }}
          </th>
          <th>
            <span class="visually-hidden">{{ __('Actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="row-template d-none">
          <td data-field-id="@php echo $form->resource->renderId(); @endphp"></td>
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
        @foreach($relatedDonorRecord as $item)
          <tr id="{{ route('accession.relatedDonor', ['slug' => $item->slug]) }}">
            <td data-field-id="@php echo $form->resource->renderId(); @endphp">
              {{ $item->object->authorized_form_of_name ?? $item->object->title ?? '' }}
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
          <td colspan="2">
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
    aria-labelledby="related-donor-heading"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="h5 modal-title" id="related-donor-heading">
            {{ __('Related donor record') }}
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
                  .route('donor.autocomplete')
                  .'"><input class="add" type="hidden" data-link-existing="true" value="'
                  .route('donor.add')
                  .' #authorizedFormOfName">';
              echo render_field(
                  $form->resource
                      ->label(__('Name'))
                      ->help(__(
                          'This is the legal entity field and provides the contact information for the person(s) or the institution that donated or transferred the materials. It has the option of multiple instances and provides the option of creating more than one contact record using the same form.'
                      )),
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          <h5>
            {{ __('Primary contact information') }}
          </h5>

          <ul class="nav nav-pills mb-3 d-flex gap-2" role="tablist">
            <li class="nav-item" role="presentation">
              <button
                class="btn atom-btn-white active-primary text-wrap active"
                id="pills-main-tab"
                data-bs-toggle="pill"
                data-bs-target="#pills-main"
                type="button"
                role="tab"
                aria-controls="pills-main"
                aria-selected="true">
                {{ __('Main') }}
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="btn atom-btn-white active-primary text-wrap"
                id="pills-phys-tab"
                data-bs-toggle="pill"
                data-bs-target="#pills-phys"
                type="button"
                role="tab"
                aria-controls="pills-phys"
                aria-selected="false">
                {{ __('Physical location') }}
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button
                class="btn atom-btn-white active-primary text-wrap"
                id="pills-other-tab"
                data-bs-toggle="pill"
                data-bs-target="#pills-other"
                type="button"
                role="tab"
                aria-controls="pills-other"
                aria-selected="false">
                {{ __('Other details') }}
              </button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="pills-main" role="tabpanel" aria-labelledby="pills-main-tab">
              @php echo render_field($form->contactPerson); @endphp
              @php echo render_field($form->telephone); @endphp
              @php echo render_field($form->fax); @endphp
              @php echo render_field($form->email); @endphp
              @php echo render_field($form->website->label(__('URL'))); @endphp
            </div>
            <div class="tab-pane fade" id="pills-phys" role="tabpanel" aria-labelledby="pills-phys-tab">
              @php echo render_field($form->streetAddress); @endphp
              @php echo render_field($form->region->label(__('Region/province'))); @endphp
              @php echo render_field($form->countryCode->label(__('Country'))); @endphp
              @php echo render_field($form->postalCode); @endphp
              @php echo render_field($form->city); @endphp
              @php echo render_field($form->latitude); @endphp
              @php echo render_field($form->longitude); @endphp
            </div>
            <div class="tab-pane fade" id="pills-other" role="tabpanel" aria-labelledby="pills-other-tab">
              @php echo render_field($form->contactType); @endphp
              @php echo render_field($form->note); @endphp
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn atom-btn-outline-light" data-bs-dismiss="modal">
            {{ __('Cancel') }}
          </button>
          <button type="button" class="btn atom-btn-outline-success modal-submit">
            {{ __('Submit') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
