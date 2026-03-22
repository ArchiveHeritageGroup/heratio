<nav aria-label="breadcrumb" id="breadcrumb">
  <ol class="breadcrumb">
    @foreach($objects as $object)
      @if(isset($object->parent))
        @if(isset($resource) && $object == $resource)
          <li class="breadcrumb-item active" aria-current="page">
            @php echo render_title($object); @endphp
          </li>
        @php } else { @endphp
          <li class="breadcrumb-item">
            @php echo link_to(render_title($object), [$object, 'module' => 'informationobject']); @endphp
          </li>
        @endforeach
      @endforeach
    @endforeach
  </ol>
</nav>
