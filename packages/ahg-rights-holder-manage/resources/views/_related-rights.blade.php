@if('QubitInformationObject' === $className)

  @foreach($resource->getRights() as $right)
    @include('ahg-rights-holder-manage::_right',
      [
          'resource' => $right->object,
          'inherit' => $item != $resource ? $item : null,
          'relatedObject' => $resource, ])
  @endforeach

@elseif('QubitAccession' === $className)

  @foreach($ancestor->getRights() as $item)
    @include('ahg-rights-holder-manage::_right',
      [
          'resource' => $item->object,
          'inherit' => 0 == count($resource->getRights()) ? $resource : null,
          'relatedObject' => $resource, ])
  @endforeach

@endif
