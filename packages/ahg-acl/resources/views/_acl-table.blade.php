<div class="table-responsive mb-3 acl-table-container">
  <table class="table table-bordered mb-0 caption-top" id="@php echo 'acl_'.$object->slug; @endphp">
    <caption class="pt-0">
      <span class="d-inline-block">
        @if($object->id != constant(get_class($sf_data->getRaw('object')).'::ROOT_ID'))
          @php echo render_title($object); @endphp
        @php } else { @endphp
          <em>
            {{ __(
                'All %1%',
                ['%1%' => lcfirst(sfConfig::get('app_ui_label_'.$module))]
            ) }}
          </em>
        @endforeach
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
          <td id="@php echo $module.'_'.$object->id.'_'.$key; @endphp">
            @if(isset($permissions[$key]))
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[@php echo $permissions[$key]->id; @endphp]"
                  id="acl_grant_[@php echo $permissions[$key]->id; @endphp]"
                  @php echo (1 == $permissions[$key]->grantDeny) ? 'checked' : ''; @endphp
                  value="@php echo \AtomExtensions\Services\AclService::GRANT; @endphp">
                <label class="form-check-label" for="acl_grant_[@php echo $permissions[$key]->id; @endphp]">
                  {{ __('Grant') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[@php echo $permissions[$key]->id; @endphp]"
                  id="acl_deny_[@php echo $permissions[$key]->id; @endphp]"
                  @php echo (0 == $permissions[$key]->grantDeny) ? 'checked' : ''; @endphp
                  value="@php echo \AtomExtensions\Services\AclService::DENY; @endphp">
                <label class="form-check-label" for="acl_deny_[@php echo $permissions[$key]->id; @endphp]">
                  {{ __('Deny') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[@php echo $permissions[$key]->id; @endphp]"
                  id="acl_inherit_[@php echo $permissions[$key]->id; @endphp]"
                  value="@php echo \AtomExtensions\Services\AclService::INHERIT; @endphp">
                <label class="form-check-label" for="acl_inherit_[@php echo $permissions[$key]->id; @endphp]">
                  {{ __('Inherit') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
            @php } else { @endphp
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  id="acl_grant_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  value="@php echo \AtomExtensions\Services\AclService::GRANT; @endphp">
                <label
                  class="form-check-label"
                  for="acl_grant_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]">
                  {{ __('Grant') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="acl[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  id="acl_deny_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  value="@php echo \AtomExtensions\Services\AclService::DENY; @endphp">
                <label
                  class="form-check-label"
                  for="acl_deny_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]">
                  {{ __('Deny') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  checked
                  name="acl[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  id="acl_inherit_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]"
                  value="@php echo \AtomExtensions\Services\AclService::INHERIT; @endphp">
                <label
                  class="form-check-label"
                  for="acl_inherit_[@php echo $key.'_'.url_for([$object, 'module' => $module]); @endphp]">
                  {{ __('Inherit') }} <span class="badge bg-secondary ms-1">Required</span>
                </label>
              </div>
            @endforeach
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
