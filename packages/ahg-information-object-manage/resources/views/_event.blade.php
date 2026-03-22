<div
  class="atom-table-modal"
  data-current-resource="@php echo url_for([$resource]); @endphp"
  data-required-fields="@php echo $form->type->renderId(); @endphp"
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
          <td data-field-id="@php echo $form->actor->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->type->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->place->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->date->renderId(); @endphp"></td>
          <td class="text-nowrap">
            @if(!isset($sf_request->source))
              <button type="button" class="btn atom-btn-white me-1 edit-row">
                <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                <span class="visually-hidden">{{ __('Edit row') }}</span>
              </button>
            @endforeach
            <button type="button" class="btn atom-btn-white delete-row">
              <i class="fas fa-fw fa-times" aria-hidden="true"></i>
              <span class="visually-hidden">{{ __('Delete row') }}</span>
            </button>
          </td>
        </tr>
        @foreach($resource->eventsRelatedByobjectId as $item)
          <tr id="@php echo url_for([$item, 'module' => 'event']); @endphp">
            <td data-field-id="@php echo $form->actor->renderId(); @endphp">
              @if(isset($item->actor))
                @php echo render_title($item->actor); @endphp
              @endforeach
            </td>
            <td data-field-id="@php echo $form->type->renderId(); @endphp">
              @php echo render_value_inline($item->type); @endphp
            </td>
            <td data-field-id="@php echo $form->place->renderId(); @endphp">
              @if(null !== $relation = QubitObjectTermRelation::getOneByObjectId($item->id))
                @php echo render_value_inline($relation->term); @endphp
              @endforeach
            </td>
            <td data-field-id="@php echo $form->date->renderId(); @endphp">
              @php echo render_value_inline(Qubit::renderDateStartEnd(
                  $item->getDate(['cultureFallback' => true]),
                  $item->startDate,
                  $item->endDate
              )); @endphp
            </td>
            <td class="text-nowrap">
              @if(!isset($sf_request->source))
                <button type="button" class="btn atom-btn-white me-1 edit-row">
                  <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                  <span class="visually-hidden">{{ __('Edit row') }}</span>
                </button>
              @endforeach
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
          @php echo $form->renderHiddenFields(); @endphp

          @php $extraInputs = '<input class="list" type="hidden" value="'
                  .route('actor.autocomplete')
                  .'">';
              if (QubitAcl::check(QubitActor::getRoot(), 'create')) {
                  $extraInputs .= '<input class="add" type="hidden"'
                      .' data-link-existing="true" value="'
                      .route('actor.add')
                      .' #authorizedFormOfName">';
              }
              echo render_field(
                  $form->actor->label(__('Actor name')),
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          @php echo render_field($form->type->label(__('Event type'))); @endphp

          @php $extraInputs = '<input class="list" type="hidden" value="'
                  .url_for([
                      'module' => 'term',
                      'action' => 'autocomplete',
                      'taxonomy' => url_for([
                          QubitTaxonomy::getById(QubitTaxonomy::PLACE_ID),
                          'module' => 'taxonomy',
                      ]),
                  ])
                  .'">';
              if (QubitAcl::check(QubitTaxonomy::getById(QubitTaxonomy::PLACE_ID), 'createTerm')) {
                  $extraInputs .= '<input class="add" type="hidden" data-link-existing="true" value="'
                      .url_for([
                          'module' => 'term',
                          'action' => 'add',
                          'taxonomy' => url_for([
                              QubitTaxonomy::getById(QubitTaxonomy::PLACE_ID),
                              'module' => 'taxonomy',
                          ]),
                      ])
                      .' #name">';
              }
              echo render_field(
                  $form->place,
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          <div class="date">
            @php echo render_field($form->date); @endphp
            @php echo render_field($form->startDate); @endphp
            @php echo render_field($form->endDate); @endphp
          </div>

          @php echo render_field($form->description->label(__('Event note'))); @endphp
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
