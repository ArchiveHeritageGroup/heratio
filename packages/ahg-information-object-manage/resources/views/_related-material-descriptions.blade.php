<div class="field @php echo render_b5_show_field_css_classes(); @endphp">

  @if('rad' == $template)
    @php echo render_b5_show_label(__('Related materials')); @endphp
  @php } else { @endphp
    @php echo render_b5_show_label(__('Related descriptions')); @endphp
  @endforeach

  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    <ul class="@php echo render_b5_show_list_css_classes(); @endphp">
      @foreach($resource->relationsRelatedBysubjectId as $item)
        @if(isset($item->type) && QubitTerm::RELATED_MATERIAL_DESCRIPTIONS_ID == $item->type->id)
          <li>@php echo link_to(render_title($item->object), [$item->object, 'module' => 'informationobject']); @endphp</li>
        @endforeach
      @endforeach
      @foreach($resource->relationsRelatedByobjectId as $item)
        @if(isset($item->type) && QubitTerm::RELATED_MATERIAL_DESCRIPTIONS_ID == $item->type->id)
          <li>@php echo link_to(render_title($item->subject), [$item->subject, 'module' => 'informationobject']); @endphp</li>
        @endforeach
      @endforeach
    </ul>
  </div>

</div>
