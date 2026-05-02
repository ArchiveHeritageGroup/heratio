<h1>
  {{ __('Edit :module permissions of :group', [
      'module' => lcfirst(__('Repository')),
      'group'  => $resource->authorized_form_of_name ?? $resource->name ?? $resource->title ?? '',
  ]) }}
</h1>

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

<form id="editForm" method="POST" action="{{ route('acl.editRepositoryAcl', ['id' => $resource->id]) }}">
  @csrf

  <div class="accordion mb-3">
    <div class="accordion-item">
      <h2 class="accordion-header" id="all-heading">
        <button
          class="accordion-button"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#all-collapse"
          aria-expanded="true"
          aria-controls="all-collapse">
          {{ __(
              'Permissions for all :module',
              ['module' => lcfirst(__('Repository'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse show"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @include('ahg-acl::_acl-table', [
              'object' => $rootRepository,
              'permissions' => $item ?? [],
              'actions' => $basicActions,
              'module' => 'repository',
              'moduleLabel' => __('Repository'),
          ])
        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="repo-heading">
        <button
          class="accordion-button"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#repo-collapse"
          aria-expanded="true"
          aria-controls="repo-collapse">
          {{ __(
              'Permissions by :module',
              ['module' => lcfirst(__('Repository'))]
          ) }}
        </button>
      </h2>
      <div
        id="repo-collapse"
        class="accordion-collapse collapse show"
        aria-labelledby="repo-heading">
        <div class="accordion-body">
          @foreach($repositories as $key => $item)
            @if(($rootRepository->id ?? null) != $key)
              @include('ahg-acl::_acl-table', [
                  'object' => $repositoryObjects[$key] ?? (object)['id' => $key, 'slug' => $key],
                  'permissions' => $item,
                  'actions' => $basicActions,
                  'module' => 'repository',
                  'moduleLabel' => __('Repository'),
              ])
            @endif
          @endforeach

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-repository"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-repository">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __(
                'Add permissions by :module',
                ['module' => lcfirst(__('Repository'))]
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
