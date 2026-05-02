<div
  class="modal fade"
  id="acl-modal-container-{{ $entityType }}"
  data-trigger-button="acl-add-{{ $entityType }}"
  data-bs-backdrop="static"
  tabindex="-1"
  aria-labelledby="acl-modal-heading-{{ $entityType }}"
  aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="acl-modal-heading-{{ $entityType }}">
          {{ __('Add :label', ['label' => lcfirst($label)]) }}
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal">
          <span class="visually-hidden">{{ __('Close') }}</span>
        </button>
      </div>
      <div class="modal-body">
        <form>
          <div>
            <label for="acl-autocomplete-{{ $entityType }}" class="form-label">
              {{ __(':label name', ['label' => $label]) }}
             <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            <select
              name="acl-autocomplete-{{ $entityType }}"
              id="acl-autocomplete-{{ $entityType }}"
              class="form-control form-autocomplete mb-1">
            </select>
            <input class="list" type="hidden" value="{{ \Illuminate\Support\Facades\Route::has($entityType . '.autocomplete') ? route($entityType . '.autocomplete', ['showOnlyActors' => ('actor' == $entityType)]) : '' }}"/>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">
          {{ __('Cancel') }}
        </button>
        <button type="button" class="btn atom-btn-white">
          {{ __('Submit') }}
        </button>
      </div>
    </div>
  </div>
</div>

<div class="table-responsive mb-3 acl-table-container d-none">
  <table class="table table-bordered mb-0 caption-top">
    <caption class="pt-0">
      <span class="d-inline-block"></span>
    </caption>
    <thead class="table-light">
      <tr>
        <th scope="col">{{ __('Action') }}</th>
        <th scope="col">{{ __('Permission') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($basicActions as $key => $item)
        <tr>
          <td>{{ __($item) }}</td>
          <td>
            <div class="form-check form-check-inline">
              <input
                class="form-check-input"
                type="radio"
                name="acl[{{ $key }}_{objectId}]"
                id="acl_grant_[{{ $key }}_{objectId}]"
                value="{{ \AhgCore\Services\AclService::GRANT }}">
              <label class="form-check-label" for="acl_grant_[{{ $key }}_{objectId}]">
                {{ __('Grant') }}
               <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
            <div class="form-check form-check-inline">
              <input
                class="form-check-input"
                type="radio"
                name="acl[{{ $key }}_{objectId}]"
                id="acl_deny_[{{ $key }}_{objectId}]"
                value="{{ \AhgCore\Services\AclService::DENY }}">
              <label class="form-check-label" for="acl_deny_[{{ $key }}_{objectId}]">
                {{ __('Deny') }}
               <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
            <div class="form-check form-check-inline">
              <input
                class="form-check-input"
                type="radio"
                checked
                name="acl[{{ $key }}_{objectId}]"
                id="acl_inherit_[{{ $key }}_{objectId}]"
                value="{{ \AhgCore\Services\AclService::INHERIT }}">
              <label class="form-check-label" for="acl_inherit_[{{ $key }}_{objectId}]">
                {{ __('Inherit') }}
               <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
