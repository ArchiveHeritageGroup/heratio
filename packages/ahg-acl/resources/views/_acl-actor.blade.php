<h1>
  {{ __('Edit %1% permissions of %2%', [
      '%1%' => lcfirst(__('Authority record')),
      '%2%' => $resource->authorized_form_of_name ?? $resource->title ?? '',
  ]) }}
</h1>

@include('ahg-acl::_acl-modal', [
    'entityType' => 'actor',
    'label' => __('Authority record'),
    'basicActions' => $basicActions,
])

@if($errors->any())
  <div class="alert alert-danger">
    @foreach($errors->all() as $error)
      <p>{{ $error }}</p>
    @endforeach
  </div>
@endif

<form id="editForm" method="POST" action="{{ route('acl.editActorAcl', ['id' => $resource->id]) }}">
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
              ['%1%' => lcfirst(__('Authority record'))]
          ) }}
        </button>
      </h2>
      <div
        id="all-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="all-heading">
        <div class="accordion-body">
          @include('ahg-acl::_acl-table', [
              'object' => $rootActor,
              'permissions' => $actors[$rootActor->id ?? 0] ?? [],
              'actions' => $basicActions,
              'module' => 'actor',
              'moduleLabel' => __('Authority record'),
          ])
        </div>
      </div>
    </div>
    <div class="accordion-item">
      <h2 class="accordion-header" id="actor-heading">
        <button
          class="accordion-button collapsed"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#actor-collapse"
          aria-expanded="false"
          aria-controls="actor-collapse">
          {{ __(
              'Permissions by %1%',
              ['%1%' => lcfirst(__('Authority record'))]
          ) }}
        </button>
      </h2>
      <div
        id="actor-collapse"
        class="accordion-collapse collapse"
        aria-labelledby="actor-heading">
        <div class="accordion-body">
          @foreach($actors as $key => $item)
            @if(($rootActor->id ?? null) != $key)
              @include('ahg-acl::_acl-table', [
                  'object' => $actorObjects[$key] ?? (object)['id' => $key, 'slug' => $key],
                  'permissions' => $item,
                  'actions' => $basicActions,
                  'module' => 'actor',
                  'moduleLabel' => __('Authority record'),
              ])
            @endif
          @endforeach

          <button
            class="btn atom-btn-white text-wrap"
            type="button"
            id="acl-add-actor"
            data-bs-toggle="modal"
            data-bs-target="#acl-modal-container-actor">
            <i class="fas fa-plus me-1" aria-hidden="true"></i>
            {{ __(
                'Add permissions by %1%',
                ['%1%' => lcfirst(__('Authority record'))]
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
