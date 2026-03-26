<h3 class="fs-6 mb-2">
  {{ __('Related corporate bodies, persons or families') }}
</h3>

<div
  class="atom-table-modal"
  data-current-resource="@php echo url_for([$resource]); @endphp"
  data-current-resource-text="@php echo render_title($resource); @endphp"
  data-required-fields="@php echo $form->resource->renderId().','.$form->type->renderId(); @endphp"
  data-delete-field-name="deleteRelations"
  data-iframe-error="{{ __('The following resources could not be created:') }}">
  <div class="alert alert-danger d-none load-error" role="alert">
    {{ __('Could not load relation data.') }}
  </div>

  <div class="table-responsive mb-3">
    <table class="table table-bordered mb-0">
      <thead class="table-light">
	<tr>
          <th class="w-25">
            {{ __('Name') }}
          </th>
          <th class="w-15">
            {{ __('Category') }}
          </th>
          <th class="w-15">
            {{ __('Type') }}
          </th>
          <th class="w-15">
            {{ __('Dates') }}
          </th>
          <th class="w-30">
            {{ __('Description') }}
          </th>
          <th>
            <span class="visually-hidden">{{ __('Actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr class="row-template d-none">
          <td data-field-id="@php echo $form->resource->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->type->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->subType->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->date->renderId(); @endphp"></td>
          <td data-field-id="@php echo $form->description->renderId(); @endphp"></td>
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
        @foreach($resource->getActorRelations() as $item)
          <tr id="@php echo url_for([$item, 'module' => 'relation']); @endphp">
            <td data-field-id="@php echo $form->resource->renderId(); @endphp">
              @if($resource->id == $item->objectId)
                @php echo render_title($item->subject); @endphp
              @else
                @php echo render_title($item->object); @endphp
              @endif
            </td>
            <td data-field-id="@php echo $form->type->renderId(); @endphp">
              @if(QubitTerm::ROOT_ID == $item->type->parentId)
                @php echo render_value_inline($item->type); @endphp
              @else
                @php echo render_title($item->type->parent); @endphp
              @endif
            </td>
            <td data-field-id="@php echo $form->subType->renderId(); @endphp">
              @if(QubitTerm::ROOT_ID != $item->type->parentId)
                @if($resource->id != $item->objectId)
                  @php echo render_title($item->type).' '.render_title($resource); @endphp
                @elseif(
                    0 < count($converseTerms = QubitRelation::getBySubjectOrObjectId(
                        $item->type->id,
                        ['typeId' => QubitTerm::CONVERSE_TERM_ID]
                    ))
                )
                  @php echo render_title($converseTerms[0]->getOpposedObject($item->type))
                    .' '.
                    render_title($resource); @endphp
                @endif
              @endif
            </td>
            <td data-field-id="@php echo $form->date->renderId(); @endphp">
              @php echo render_value_inline(Qubit::renderDateStartEnd(
                  $item->date,
                  $item->startDate,
                  $item->endDate
              )); @endphp
            </td>
            <td data-field-id="@php echo $form->description->renderId(); @endphp">
              @php echo render_value_inline($item->description); @endphp
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
          <td colspan="6">
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
            {{ __('Related corporate body, person or family') }}
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
                  .url_for([
                      'module' => 'actor',
                      'action' => 'autocomplete',
                      'showOnlyActors' => 'true',
                  ])
                  .'">';
              echo render_field(
                  $form->resource
                      ->label(__('Authorized form of name'))
                      ->help(__(
                        '"Record the authorized form of name and any relevant unique identifiers, including the authority record identifier, for the related entity." (ISAAR 5.3.1) Select the name from the drop-down menu; enter the first few letters to narrow the choices.'
                      )),
                  null,
                  ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
              ); @endphp

          @php echo render_field($form->type->label(__('Category of relationship'))->help(__(
              '"Purpose: To identify the general category of relationship between the entity being described and another corporate body, person or family." (ISAAR 5.3.2). Select a category from the drop-down menu: hierarchical, temporal, family or associative.'
          ))); @endphp

          @php $extraInputs = '<input class="list" type="hidden" value="'
                  .url_for([
                      'module' => 'term',
                      'action' => 'autocomplete',
                      'taxonomy' => url_for([
                          QubitTaxonomy::getById(QubitTaxonomy::ACTOR_RELATION_TYPE_ID),
                          'module' => 'taxonomy',
                      ]),
                      'addWords' => isset($resource->id)
                          ? sfOutputEscaper::unescape(render_title($resource))
                          : '',
                  ])
                  .'">';
              echo render_field(
                  $form->subType
                      ->label(__('Relationship type'))
                      ->help(__(
                        '"Select a descriptive term from the drop-down menu to clarify the type of relationship between these two actors."'
                      )),
                  null,
                  ['class' => 'form-autocomplete', 'disabled' => 'disabled', 'extraInputs' => $extraInputs]
              ); @endphp

          @php echo render_field($form->description->label(__('Description of relationship'))->help(__(
              '"Record a precise description of the nature of the relationship between the entity described in this authority record and the other related entity....Record in the Rules and/or conventions element (5.4.3) any classification scheme used as a source of controlled vocabulary terms to describe the relationship. A narrative description of the history and/or nature of the relationship may also be provided here." (ISAAR 5.3.3). Note that the text entered in this field will also appear in the related authority record.'
          ))); @endphp

          <div class="date">
            @php echo render_field($form->date->help(__(
                '"Record when relevant the commencement date of the relationship or succession date and, when relevant, the cessation date of the relationship." (ISAAR 5.3.4) Enter the date as you would like it to appear in the show page for the authority record, using qualifiers and/or typographical symbols to express uncertainty if desired.'
            ))); @endphp
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
