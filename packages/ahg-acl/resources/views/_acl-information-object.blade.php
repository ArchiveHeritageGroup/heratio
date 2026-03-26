<h1>
  {{ __('Edit %1% permissions of %2%', [
      '%1%' => lcfirst(__('Archival description')),
      '%2%' => $resource->authorized_form_of_name ?? $resource->title ?? '',
  ]) }}
</h1>

@include('ahg-acl::_acl-modal', [
    'entityType' => 'informationobject',
    'label' => __('Archival description'),
    'basicActions' => $basicActions,
])

@include('ahg-acl::_acl-modal', [
    'entityType' => 'repository',
    'label' => __('Repository'),
    'basicActions' => $basicActions,
])

@if($errors->any())
  <div class="alert alert-danger">
    @foreach($errors->all() as $error)
      <p>{{ $error }}</p>
    @endforeach
  </div>
@endif

<form id="editForm" method="POST" action="{{ route('acl.editInformationObjectAcl', ['id' => $resource->id]) }}">
  @csrf

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
              ['%1%' => lcfirst(__('Archival description'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @include('ahg-acl::_acl-table', [
              'object' => $rootInformationObject,
              'permissions' => $root ?? [],
              'actions' => $basicActions,
              'module' => 'informationobject',
              'moduleLabel' => __('Archival description'),
          ])
        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="io-heading">
        <button
          class="accordion-button collapsed"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#io-collapse"
          aria-expanded="false"
          aria-controls="io-collapse">
          {{ __(
              'Permissions by %1%',
              ['%1%' => lcfirst(__('Archival description'))]
          ) }}
        </button>
      </h2>
      <div id="io-collapse" class="accordion-collapse collapse" aria-labelledby="io-heading">
        <div class="accordion-body">
          @if(count($informationObjects) > 0)
            @foreach($informationObjects as $informationObjectId => $permissions)
              @include('ahg-acl::_acl-table', [
                  'object' => $informationObjectEntities[$informationObjectId] ?? (object)['id' => $informationObjectId, 'slug' => $informationObjectId],
                  'permissions' => $permissions,
                  'actions' => $basicActions,
                  'module' => 'informationobject',
                  'moduleLabel' => __('Archival description'),
              ])
            @endforeach
          @endif

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-informationobject"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-informationobject">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __(
                'Add permissions by %1%',
                ['%1%' => lcfirst(__('Archival description'))]
            ) }}
          </button>
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
              ['%1%' => lcfirst(__('Repository'))]
          ) }}
        </button>
      </h2>
      <div
        id="repo-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="repo-heading">
        <div class="accordion-body">
          @if(count($repositories) > 0)
            @foreach($repositories as $repository => $permissions)
              @include('ahg-acl::_acl-table', [
                  'object' => $repositoryObjects[$repository] ?? (object)['id' => $repository, 'slug' => $repository],
                  'permissions' => $permissions,
                  'actions' => $basicActions,
                  'module' => 'repository',
                  'moduleLabel' => __('Repository'),
              ])
            @endforeach
          @endif

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-repository"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-repository">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __(
                'Add permissions by %1%',
                ['%1%' => lcfirst(__('Repository'))]
            ) }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <ul class="actions mb-3 nav gap-2">
    <li>
      <a href="{{ route('acl.groups') }}" class="btn atom-btn-outline-light" role="button">
        {{ __('Cancel') }}
      </a>
    </li>
    <li>
      <input
        class="btn atom-btn-outline-success"
        type="submit"
        value="{{ __('Save') }}">
    </li>
  </ul>

</form>
