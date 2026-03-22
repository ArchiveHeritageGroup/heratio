<th class="sortable" width="@php echo $size; @endphp">

  @php // Set a default if it has been defined
    if (empty($sf_request->sort) && !empty($default)) {
        $sf_request->sort = $name.ucfirst($default);
    }

    $up = "{$name}Up";
    $down = "{$name}Down";
    $next = $sf_request->sort !== $up ? $up : $down; @endphp

  @php echo link_to($label,
    ['sort' => $next] + $sf_data->getRaw('sf_request')->getParameterHolder()->getAll(),
    ['title' => __('Sort'), 'class' => 'sortable']); @endphp

  @if($up === $sf_request->sort)
    @php echo image_tag('up.gif', ['alt' => __('Sort ascending')]); @endphp
  @php } elseif ($down === $sf_request->sort) { @endphp
    @php echo image_tag('down.gif', ['alt' => __('Sort descending')]); @endphp
  @endforeach

</th>
