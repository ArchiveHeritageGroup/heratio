<h1>
  {{ __('Edit %1% permissions of %2%', [
      '%1%' => lcfirst(sfConfig::get('app_ui_label_repository')),
      '%2%' => render_title($resource), ]
  ) }}
</h1>

@php echo get_partial('aclGroup/aclModal', [
    'entityType' => 'repository',
    'label' => sfConfig::get('app_ui_label_repository'),
    'basicActions' => $basicActions,
]); @endphp

@php echo $form->renderGlobalErrors(); @endphp

@php echo $form->renderFormTag(url_for(
    [$resource, 'module' => $sf_context->getModuleName(), 'action' => 'editRepositoryAcl']),
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
              ['%1%' => lcfirst(sfConfig::get('app_ui_label_repository'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @php echo get_component('aclGroup', 'aclTable', [
              'object' => QubitRepository::getById(QubitRepository::ROOT_ID),
              'permissions' => $item,
              'actions' => $basicActions,
          ]); @endphp
        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="repo-heading">
        <button
          class="accordion-button collapsed"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#repo-collapse"
          aria-expanded="false"
          aria-controls="repo-collapse">
          {{ __(
              'Permissions by %1%',
              ['%1%' => lcfirst(sfConfig::get('app_ui_label_repository'))]
          ) }}
        </button>
      </h2>
      <div
        id="repo-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="repo-heading">
        <div class="accordion-body">
          @foreach($repositories as $key => $item)
            @if(QubitRepository::ROOT_ID != $key)
              @php echo get_component('aclGroup', 'aclTable', [
                  'object' => QubitRepository::getById($key),
                  'permissions' => $item,
                  'actions' => $basicActions,
              ]); @endphp
            @endforeach
          @endforeach

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-repository"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-repository">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __(
                'Add permissions by %1%',
                ['%1%' => lcfirst(sfConfig::get('app_ui_label_repository'))]
            ) }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li>
      @php echo link_to(
          __('Cancel'),
          [$resource, 'module' => $sf_context->getModuleName(), 'action' => 'indexRepositoryAcl'],
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
