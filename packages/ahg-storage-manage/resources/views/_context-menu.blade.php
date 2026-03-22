<section id="physical-objects">

  <h4 class="h5 mb-2">@php echo sfConfig::get('app_ui_label_physicalobject'); @endphp</h4>
  <ul class="list-unstyled">

    @foreach($physicalObjects as $item)
      <li>

        @if(isset($item->type))
          @php echo render_value_inline($item->type); @endphp:
        @endforeach

        @php echo link_to_if(QubitAcl::check($resource, 'update'), render_title($item), [$item, 'module' => 'physicalobject']); @endphp

        @if(isset($item->location) && $sf_user->isAuthenticated())
          - @php echo render_value_inline($item->getLocation(['cultureFallback' => 'true'])); @endphp
        @endforeach

      </li>
    @endforeach

  </ul>

</section>
