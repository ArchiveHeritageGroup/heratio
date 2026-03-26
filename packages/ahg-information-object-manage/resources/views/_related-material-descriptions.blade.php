<div class="field @php echo render_b5_show_field_css_classes(); @endphp">

  @if('rad' == $template)
    @php echo render_b5_show_label(__('Related materials')); @endphp
  @else
    @php echo render_b5_show_label(__('Related descriptions')); @endphp
  @endif

  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    <ul class="@php echo render_b5_show_list_css_classes(); @endphp">
      @foreach($resource->relationsRelatedBysubjectId as $item)
        @if(isset($item->type) && QubitTerm::RELATED_MATERIAL_DESCRIPTIONS_ID == $item->type->id)
          <li>@php echo link_to(render_title($item->object), [$item->object, 'module' => 'informationobject']); @endphp</li>
        @endif
      @endforeach
      @foreach($resource->relationsRelatedByobjectId as $item)
        @if(isset($item->type) && QubitTerm::RELATED_MATERIAL_DESCRIPTIONS_ID == $item->type->id)
          <li>@php echo link_to(render_title($item->subject), [$item->subject, 'module' => 'informationobject']); @endphp</li>
        @endif
      @endforeach
    </ul>
  </div>

</div>
