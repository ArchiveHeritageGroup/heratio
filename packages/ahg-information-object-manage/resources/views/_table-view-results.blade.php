@if($pager->total() > 0)
  @foreach($pager->items() as $hit)
    @include('ahg-core::partials._search-result', ['hit' => $hit, 'culture' => $selectedCulture ?? app()->getLocale()])
  @endforeach
@else
  <section id="no-search-results">
    <i class="fa fa-search"></i>
    <p class="no-results-found">{{ __('No results found.') }}</p>
  </section>
@endif
