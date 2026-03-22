@if($pager->getNbResults())

  @foreach($pager->getResults() as $hit)
    @php echo get_partial('search/searchResult', ['hit' => $hit, 'culture' => $culture]); @endphp
  @endforeach

@php } else { @endphp

  <div class="p-3">
    {{ __('We couldn\'t find any results matching your search.') }}
  </div>

@endforeach
