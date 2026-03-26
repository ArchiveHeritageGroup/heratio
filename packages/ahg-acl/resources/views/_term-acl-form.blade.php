<h1>
  {{ __('Edit %1% permissions of %2%', [
      '%1%' => lcfirst(__('Term')),
      '%2%' => $resource->authorized_form_of_name ?? $resource->title ?? $resource->name ?? '',
  ]) }}
</h1>

@include('ahg-acl::_acl-modal', [
    'entityType' => 'taxonomy',
    'label' => __('Taxonomy'),
    'basicActions' => $termActions,
])

@if($errors->any())
  <div class="alert alert-danger">
    @foreach($errors->all() as $error)
      <p>{{ $error }}</p>
    @endforeach
  </div>
@endif

<form id="editForm" method="POST" action="{{ route('acl.editTermAcl', ['id' => $resource->id]) }}">
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
              ['%1%' => lcfirst(__('Term'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @include('ahg-acl::_acl-table', [
              'object' => $rootTerm,
              'permissions' => $rootPermissions ?? [],
              'actions' => $termActions,
              'module' => 'term',
              'moduleLabel' => __('Term'),
          ])
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
            @include('ahg-acl::_acl-table', [
                'object' => $taxonomyObjects[$key] ?? (object)['id' => $key, 'slug' => $key],
                'permissions' => $item,
                'actions' => $termActions,
                'module' => 'taxonomy',
                'moduleLabel' => __('Taxonomy'),
            ])
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
