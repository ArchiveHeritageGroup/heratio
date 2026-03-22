@php decorate_with('layout_3col'); @endphp
@php use_helper('Date'); @endphp

@php slot('sidebar'); @endphp

  @php echo get_partial('term/sidebar', [
      'resource' => $resource,
      'showTreeview' => true,
      'search' => $search,
      'aggs' => $aggs,
      'listPager' => $listPager, ]); @endphp

@php end_slot(); @endphp

@php slot('title'); @endphp

  <h1>@php echo render_title($resource); @endphp</h1>

  @php echo get_component('term', 'navigateRelated', ['resource' => $resource]); @endphp

  @php echo get_partial('term/errors', ['errorSchema' => $errorSchema]); @endphp

  @if(QubitTerm::ROOT_ID != $resource->parentId)
    @php echo include_partial('default/breadcrumb',
                 ['resource' => $resource, 'objects' => $resource->getAncestors()->andSelf()->orderBy('lft')]); @endphp
  @endforeach

@php end_slot(); @endphp

@php slot('before-content'); @endphp
  @php echo get_component('default', 'translationLinks', ['resource' => $resource]); @endphp
@php end_slot(); @endphp

@php slot('context-menu'); @endphp

  <nav>

    @php echo get_partial('term/format', ['resource' => $resource]); @endphp

    @php echo get_partial('term/rightContextMenu', ['resource' => $resource, 'results' => $pager->getNbResults()]); @endphp

  </nav>

@php end_slot(); @endphp

@php slot('content'); @endphp

  <div id="content">
    @php echo get_partial('term/fields', ['resource' => $resource]); @endphp
  </div>

  @php echo get_partial('term/actions', ['resource' => $resource]); @endphp

  <h1>
    {{ __('%1% %2% results for %3%', [
        '%1%' => $pager->getNbResults(),
        '%2%' => sfConfig::get('app_ui_label_actor'),
        '%3%' => render_title($resource), ]) }}
  </h1>

  <div class="d-flex flex-wrap gap-2">
    @if(isset($sf_request->onlyDirect))
      @php $params = $sf_data->getRaw('sf_request')->getGetParameters(); @endphp
      @php unset($params['onlyDirect']); @endphp
      @php unset($params['page']); @endphp
      <a
        href="@php echo url_for(
            [$resource, 'module' => 'term', 'action' => 'relatedAuthorities']
            + $params
        ); @endphp"
        class="btn btn-sm atom-btn-white align-self-start mw-100 filter-tag d-flex">
        <span class="visually-hidden">
          {{ __('Remove filter:') }}
        </span>
        <span class="text-truncate d-inline-block">
          {{ __('Only results directly related') }}
        </span>
        <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
      </a>
    @endforeach

    <div class="d-flex flex-wrap gap-2 ms-auto mb-3">
      @php echo get_partial('default/sortPickers', ['options' => [
          'lastUpdated' => __('Date modified'),
          'alphabetic' => __('Name'),
          'identifier' => __('Identifier'),
      ]]); @endphp
    </div>
  </div>

  <div id="content">

    @php echo get_partial('term/directTerms', [
        'resource' => $resource,
        'aggs' => $aggs,
    ]); @endphp

    @if($pager->getNbResults())

      @foreach($pager->getResults() as $hit)
        @php $doc = $hit->getData(); @endphp
        @php echo include_partial('actor/searchResult', ['doc' => $doc, 'pager' => $pager, 'culture' => $selectedCulture, 'clipboardType' => 'actor']); @endphp
      @endforeach

    @php } else { @endphp

      <div class="p-3">
        {{ __('We couldn\'t find any results matching your search.') }}
      </div>

    @endforeach

  </div>

@php end_slot(); @endphp

@php slot('after-content'); @endphp
  @php echo get_partial('default/pager', ['pager' => $pager]); @endphp
@php end_slot(); @endphp
