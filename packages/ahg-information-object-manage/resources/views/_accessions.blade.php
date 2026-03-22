<div class="field @php echo render_b5_show_field_css_classes(); @endphp">
  @php echo render_b5_show_label(__('Accession number(s)')); @endphp
  <div class="@php echo render_b5_show_value_css_classes(); @endphp">
    <ul class="@php echo render_b5_show_list_css_classes(); @endphp">
      @foreach($accessions as $item)
        <li>@php echo link_to(render_title($item->object), [$item->object, 'module' => 'accession']); @endphp</li>
      @endforeach
    </ul>
  </div>
</div>
