<section class="card sidebar-paginated-list mb-3"
  data-total-pages="@php echo $list['pager']->getLastPage(); @endphp"
  data-url="@php echo $list['dataUrl']; @endphp">

  <h5 class="p-3 mb-0">
    @php echo $list['label']; @endphp
    <span class="d-none spinner">
      <i class="fas fa-spinner fa-spin ms-2" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Loading ...') }}</span>
    </span>
  </h5>

  <ul class="list-group list-group-flush">
    @foreach($list['pager']->getResults() as $hit)
      @php $doc = $hit->getData(); @endphp
      @php echo link_to(render_value_inline(get_search_i18n($doc, 'authorizedFormOfName', ['allowEmpty' => false])), ['module' => 'actor', 'slug' => $doc['slug']], ['class' => 'list-group-item list-group-item-action']); @endphp
    @endforeach
  </ul>

  @php echo get_partial('default/sidebarPager', ['pager' => $list['pager']]); @endphp

  <div class="card-body p-0">
    <a class="btn atom-btn-white border-0 w-100" href="@php echo $list['moreUrl']; @endphp">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Browse %1% results', ['%1%' => $list['pager']->getNbResults()]) }}
    </a>
  </div>

</section>
