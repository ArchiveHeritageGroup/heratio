<div class="field @php echo render_b5_show_field_css_classes(); @endphp">

  @php echo render_b5_show_label(__('Alternative identifier(s)')); @endphp

  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    @foreach($resource->getAlternativeIdentifiers() as $item)
      @php echo render_show(render_value_inline($item->getType(['cultureFallback' => true])), $item->getName(['cultureFallback' => true]), ['isSubField' => true]); @endphp
      @if(!empty($note = $item->getNote(['cultureFallback' => true])))
        @php echo render_value($note); @endphp
      @endforeach
    @endforeach
  </div>

</div>
