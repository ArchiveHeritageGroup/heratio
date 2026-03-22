@if($pager->getNbResults())
  @foreach($pager->getResults() as $hit)
    @php echo get_partial('search/searchResult', ['hit' => $hit, 'culture' => $selectedCulture]); @endphp
  @endforeach
@php } else { @endphp
  <section id="no-search-results">
    <i class="fa fa-search"></i>
    <p class="no-results-found">{{ __('No results found.') }}</p>
  </section>
@endforeach
