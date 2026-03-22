<div class="field @php echo render_b5_show_field_css_classes(); @endphp">

  @php echo render_b5_show_label(__('Alternative identifier(s)')); @endphp

  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    @foreach($resource->getProperties(null, 'alternativeIdentifiers') as $item)
      @php echo render_show(render_value_inline($item->name), $item->getValue(['cultureFallback' => true]), ['isSubField' => true]); @endphp
    @endforeach
  </div>

</div>
