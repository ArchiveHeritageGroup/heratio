<div class="field @php echo render_b5_show_field_css_classes(); @endphp">
  @php echo render_b5_show_label(__('File UUID'), ['isSubField' => true]); @endphp
  <div class="aip-download @php echo render_b5_show_value_css_classes(['isSubField' => true]); @endphp">
    @php echo render_value_inline($resource->object->objectUUID); @endphp
    @if($sf_user->checkModuleActionAccess('arStorageService', 'extractFile'))
      <a href="@php echo url_for([$resource, 'module' => 'arStorageService', 'action' => 'extractFile']); @endphp" target="_blank">
        <i class="fa fa-download me-1" aria-hidden="true"></i>{{ __('Download file') }}
      </a>
    @endif
  </div>
</div>

<div class="field @php echo render_b5_show_field_css_classes(); @endphp">
  @php echo render_b5_show_label(__('AIP UUID'), ['isSubField' => true]); @endphp
  <div class="aip-download @php echo render_b5_show_value_css_classes(['isSubField' => true]); @endphp">
    @php echo render_value_inline($resource->object->aipUUID); @endphp
    @if($sf_user->checkModuleActionAccess('arStorageService', 'download'))
      <a href="@php echo url_for([$resource, 'module' => 'arStorageService', 'action' => 'download']); @endphp" target="_blank">
        <i class="fa fa-download me-1" aria-hidden="true"></i>{{ __('Download AIP') }}
      </a>
    @endif
  </div>
</div>
