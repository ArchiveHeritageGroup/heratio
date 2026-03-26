@foreach($filterTags as $name => $options)
  @include('ahg-search::_filter-tag', ['name' => $name, 'options' => $options])
@endforeach
