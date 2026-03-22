<h1>
  {{ __('Edit %1% permissions of %2%', [
      '%1%' => lcfirst(sfConfig::get('app_ui_label_term')),
      '%2%' => render_title($resource),
  ]) }}
</h1>

@php echo get_partial('aclGroup/aclModal', [
    'entityType' => 'taxonomy',
    'label' => 'Taxonomy',
    'basicActions' => $termActions,
]); @endphp

@php echo $form->renderGlobalErrors(); @endphp

@php echo $form->renderFormTag(
    url_for([
        $resource,
        'module' => $sf_context->getModuleName(),
        'action' => 'editTermAcl',
    ]),
    ['id' => 'editForm']
); @endphp

  @php echo $form->renderHiddenFields(); @endphp

  <div class="accordion mb-3">
    <div class="accordion-item">
      <h2 class="accordion-header" id="all-heading">
        <button
          class="accordion-button collapsed"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#all-collapse"
          aria-expanded="false"
          aria-controls="all-collapse">
          {{ __(
              'Permissions for all %1%',
              ['%1%' => lcfirst(sfConfig::get('app_ui_label_term'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @php echo get_component('aclGroup', 'aclTable', [
              'object' => QubitTerm::getById(QubitTerm::ROOT_ID),
              'permissions' => $rootPermissions,
              'actions' => $termActions,
          ]); @endphp
        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="taxonomy-heading">
        <button
          class="accordion-button collapsed"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#taxonomy-collapse"
          aria-expanded="false"
          aria-controls="taxonomy-collapse">
          {{ __('Permissions by taxonomy') }}
        </button>
      </h2>
      <div
        id="taxonomy-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="taxonomy-heading">
        <div class="accordion-body">
          @foreach($taxonomyPermissions as $key => $item)
            @php echo get_component('aclGroup', 'aclTable', [
                'object' => QubitTaxonomy::getBySlug($key),
                'permissions' => $item,
                'actions' => $termActions,
            ]); @endphp
          @endforeach

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-taxonomy"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-taxonomy">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __('Add permissions by taxonomy') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li>
      @php echo link_to(
          __('Cancel'),
          [$resource, 'module' => $sf_context->getModuleName(), 'action' => 'indexTermAcl'],
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
