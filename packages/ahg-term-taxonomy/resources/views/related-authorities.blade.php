@extends('theme::layout_3col')

@section('sidebar')

  @include('ahg-term-taxonomy::_sidebar', [
      'resource' => $resource,
      'showTreeview' => true,
      'search' => $search,
      'aggs' => $aggs,
      'listPager' => $listPager, ])

@endsection

@section('title')

  <h1>{{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}</h1>

  @include('ahg-term-taxonomy::_navigate-related', ['resource' => $resource])

  @include('ahg-term-taxonomy::_errors', ['errorSchema' => $errorSchema])

  @if(\AhgCore\Models\Term::ROOT_ID != $resource->parentId)
    @include('ahg-core::_breadcrumb',
                 ['resource' => $resource, 'objects' => $resource->getAncestors()->push($resource)->sortBy('lft')])
  @endif

@endsection

@section('before-content')
  @include('ahg-core::_translation-links', ['resource' => $resource])
@endsection

@section('context-menu')

  <nav>

    @include('ahg-term-taxonomy::_format', ['resource' => $resource])

    @include('ahg-term-taxonomy::_right-context-menu', ['resource' => $resource, 'results' => $pager->getNbResults()])

  </nav>

@endsection

@section('content')

  <div id="content">
    @include('ahg-term-taxonomy::_fields', ['resource' => $resource])
  </div>

  @include('ahg-term-taxonomy::_actions', ['resource' => $resource])

  <h1>
    {{ __('%1% %2% results for %3%', [
        '%1%' => $pager->getNbResults(),
        '%2%' => config('atom.ui_label_actor', __('Authority record')),
        '%3%' => $resource->authorized_form_of_name ?? $resource->title ?? '', ]) }}
  </h1>

  <div class="d-flex flex-wrap gap-2">
    @if(request()->has('onlyDirect'))
      @php $params = request()->query();
        unset($params['onlyDirect']);
        unset($params['page']); @endphp
      <a
        href="{{ route('term.relatedAuthorities', array_merge(['slug' => $resource->slug], $params)) }}"
        class="btn btn-sm atom-btn-white align-self-start mw-100 filter-tag d-flex">
        <span class="visually-hidden">
          {{ __('Remove filter:') }}
        </span>
        <span class="text-truncate d-inline-block">
          {{ __('Only results directly related') }}
        </span>
        <i aria-hidden="true" class="fas fa-times ms-2 align-self-center"></i>
      </a>
    @endif

    <div class="d-flex flex-wrap gap-2 ms-auto mb-3">
      @include('ahg-core::_sort-pickers', ['options' => [
          'lastUpdated' => __('Date modified'),
          'alphabetic' => __('Name'),
          'identifier' => __('Identifier'),
      ]])
    </div>
  </div>

  <div id="content">

    @include('ahg-term-taxonomy::_direct-terms', [
        'resource' => $resource,
        'aggs' => $aggs,
    ])

    @if($pager->getNbResults())

      @foreach($pager->getResults() as $hit)
        @php $doc = $hit->getData(); @endphp
        @include('ahg-actor-manage::_search-result', ['doc' => $doc, 'pager' => $pager, 'culture' => $selectedCulture, 'clipboardType' => 'actor'])
      @endforeach

    @else

      <div class="p-3">
        {{ __('We couldn\'t find any results matching your search.') }}
      </div>

    @endif

  </div>

@endsection

@section('after-content')
  @include('ahg-core::_pager', ['pager' => $pager])
@endsection
