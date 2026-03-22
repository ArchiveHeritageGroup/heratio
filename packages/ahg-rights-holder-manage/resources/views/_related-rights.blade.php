@if('QubitInformationObject' === $className)

  @foreach($resource->getRights() as $right)
    @php echo get_partial('right/right',
      [
          'resource' => $right->object,
          'inherit' => $item != $resource ? $item : null,
          'relatedObject' => $resource, ]); @endphp
  @endforeach

@php } elseif ('QubitAccession' === $className) { @endphp

  @foreach($ancestor->getRights() as $item)
    @php echo get_partial('right/right',
      [
          'resource' => $item->object,
          'inherit' => 0 == count($resource->getRights()) ? $resource : null,
          'relatedObject' => $resource, ]); @endphp
  @endforeach

@endforeach
