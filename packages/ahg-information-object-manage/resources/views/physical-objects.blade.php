<h1>{{ __('Physical storage') }}</h1>

<h1 class="label">@php echo render_title($resource); @endphp</h1>

<table class="sticky-enabled">
  <thead>
    <tr>
      <th>
        {{ __('Name') }}
      </th><th>
        {{ __('Location') }}
      </th><th>
        {{ __('Type') }}
      </th>
    </tr>
  </thead><tbody>
    @foreach($physicalObjects as $item)
      <tr class="@php echo 0 == @++$row % 2 ? 'even' : 'odd'; @endphp">
        <td>
          @php echo link_to(render_title($item), [$item, 'module' => 'physicalobject']); @endphp
        </td><td>
          @php echo render_value($item->getLocation(['cultureFallback' => true])); @endphp
        </td><td>
          @php echo render_value($item->type); @endphp
        </td>
      </tr>
    @endforeach
  <tbody>
</table>

@php echo get_partial('default/pager', ['pager' => $pager]); @endphp
