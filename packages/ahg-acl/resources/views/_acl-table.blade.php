<div class="table-responsive mb-3 acl-table-container">
  <table class="table table-bordered mb-0 caption-top" id="acl_{{ $object->slug }}">
    <caption class="pt-0">
      <span class="d-inline-block">
        @if(isset($object->is_root) && $object->is_root)
          <em>
            {{ __(
                'All %1%',
                ['%1%' => lcfirst($moduleLabel ?? __($module))]
            ) }}
          </em>
        @else
          {{ $object->authorized_form_of_name ?? $object->title ?? '' }}
        @endif
      </span>
    </caption>
    <thead class="table-light">
      <tr>
        <th scope="col">{{ __('Action') }}</th>
        <th scope="col">{{ __('Permission') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach($actions as $key => $item)
        <tr>
          <td>{{ __($item) }}</td>
          <td id="{{ $module }}_{{ $object->id }}_{{ $key }}">
            @if(isset($permissions[$key]))
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[{{ $permissions[$key]->id }}]"
                  id="acl_grant_[{{ $permissions[$key]->id }}]"
                  {{ (1 == $permissions[$key]->grantDeny) ? 'checked' : '' }}
                  value="{{ \AhgCore\Services\AclService::GRANT }}">
                <label class="form-check-label" for="acl_grant_[{{ $permissions[$key]->id }}]">
                  {{ __('Grant') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[{{ $permissions[$key]->id }}]"
                  id="acl_deny_[{{ $permissions[$key]->id }}]"
                  {{ (0 == $permissions[$key]->grantDeny) ? 'checked' : '' }}
                  value="{{ \AhgCore\Services\AclService::DENY }}">
                <label class="form-check-label" for="acl_deny_[{{ $permissions[$key]->id }}]">
                  {{ __('Deny') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[{{ $permissions[$key]->id }}]"
                  id="acl_inherit_[{{ $permissions[$key]->id }}]"
                  value="{{ \AhgCore\Services\AclService::INHERIT }}">
                <label class="form-check-label" for="acl_inherit_[{{ $permissions[$key]->id }}]">
                  {{ __('Inherit') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
            @else
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[{{ $key }}_{{ $object->slug }}]"
                  id="acl_grant_[{{ $key }}_{{ $object->slug }}]"
                  value="{{ \AhgCore\Services\AclService::GRANT }}">
                <label
                  class="form-check-label"
                  for="acl_grant_[{{ $key }}_{{ $object->slug }}]">
                  {{ __('Grant') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[{{ $key }}_{{ $object->slug }}]"
                  id="acl_deny_[{{ $key }}_{{ $object->slug }}]"
                  value="{{ \AhgCore\Services\AclService::DENY }}">
                <label
                  class="form-check-label"
                  for="acl_deny_[{{ $key }}_{{ $object->slug }}]">
                  {{ __('Deny') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  checked
                  name="acl[{{ $key }}_{{ $object->slug }}]"
                  id="acl_inherit_[{{ $key }}_{{ $object->slug }}]"
                  value="{{ \AhgCore\Services\AclService::INHERIT }}">
                <label
                  class="form-check-label"
                  for="acl_inherit_[{{ $key }}_{{ $object->slug }}]">
                  {{ __('Inherit') }} <span class="badge bg-secondary ms-1">{{ __('Required') }}</span>
                </label>
              </div>
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
