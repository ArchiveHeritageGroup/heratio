@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      @php echo render_title($resource); @endphp
    </h1>
    <span class="small" id="heading-label">
      {{ __(
          'Link %1%',
          ['%1%' => sfConfig::get('app_ui_label_physicalobject')]
      ) }}
    </span>
  </div>
@php end_slot(); @endphp

@php slot('content'); @endphp
  @php echo $form->renderGlobalErrors(); @endphp
  @php echo $form->renderFormTag(url_for([
      $resource,
      'module' => $sf_context->getModuleName(),
      'action' => 'editPhysicalObjects',
  ])); @endphp
    @php echo $form->renderHiddenFields(); @endphp

    @if(0 < count($relations))
      <div class="table-responsive mb-3">
        <table class="table table-bordered mb-0">
          <thead>
	    <tr>
              <th class="w-100">
                {{ __('Containers') }}
              </th>
              <th>
                <span class="visually-hidden">
                  {{ __('Actions') }}
                </span>
              </th>
            </tr>
          </thead>
          <tbody>
            @foreach($relations as $item)
              <tr>
                <td>
                  @php echo $item->subject->getLabel(); @endphp
                </td>
                <td class="text-nowrap">
                  <a class="btn atom-btn-white me-1" href="@php echo url_for(
                      [$item->subject, 'module' => 'physicalobject', 'action' => 'edit']
                  ); @endphp">
                    <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Edit row') }}</span>
                  </a>
                  <button
                    type="button"
                    class="btn atom-btn-white delete-physical-storage"
                    id="@php echo url_for([$item, 'module' => 'relation']); @endphp">
                    <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                    <span class="visually-hidden">{{ __('Delete row') }}</span>
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endforeach

    <div class="accordion mb-3">
      <div class="accordion-item@php echo count($relations) ? ' rounded-0' : ''; @endphp">
        <h2 class="accordion-header" id="add-heading">
          <button
            class="accordion-button@php echo count($relations) ? ' rounded-0' : ''; @endphp"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#add-collapse"
            aria-expanded="true"
            aria-controls="add-collapse">
            {{ __('Add container links (duplicate links will be ignored)') }}
          </button>
        </h2>
        <div
          id="add-collapse"
          class="accordion-collapse collapse show"
          aria-labelledby="add-heading">
          <div class="accordion-body">
            @php $extraInputs = '<input class="add" type="hidden" data-link-existing="false" value="'
                    .url_for([
                        $resource,
                        'module' => $sf_context->getModuleName(),
                        'action' => 'editPhysicalObjects',
                    ])
                    .' #name"><input class="list" type="hidden" value="'
                    .url_for([
                        'module' => 'physicalobject',
                        'action' => 'autocomplete',
                    ])
                    .'">';
                echo render_field(
                    $form->containers,
                    null,
                    ['class' => 'form-autocomplete', 'extraInputs' => $extraInputs]
                ); @endphp
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="create-heading">
          <button
            class="accordion-button collapsed"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#create-collapse"
            aria-expanded="false"
            aria-controls="create-collapse">
            {{ __('Or, create a new container') }}
          </button>
        </h2>
        <div
          id="create-collapse"
          class="accordion-collapse collapse"
          aria-labelledby="create-heading">
          <div class="accordion-body">
            <div class="form-item">
              @php echo render_field($form->name); @endphp
              @php echo render_field($form->location); @endphp
              @php echo render_field($form->type); @endphp
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li>
        @php echo link_to(
            __('Cancel'),
            [$resource, 'module' => $sf_context->getModuleName()],
            ['class' => 'btn atom-btn-outline-light', 'role' => 'button']
        ); @endphp
      </li>
      <li>
        <input
          class="btn atom-btn-outline-success"
          type="submit"
          value="{{ __('Save') }}">
      </li>
    </ul>
  </form>
@php end_slot(); @endphp
