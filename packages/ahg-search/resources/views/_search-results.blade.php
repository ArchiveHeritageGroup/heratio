@if($pager->getNbResults())

  @foreach($pager->getResults() as $hit)
    @include('ahg-search::_search-result', ['hit' => $hit, 'culture' => $culture])
  @endforeach

@else

  <div class="p-3">
    {{ __("We couldn't find any results matching your search.") }}
  </div>

@endif
