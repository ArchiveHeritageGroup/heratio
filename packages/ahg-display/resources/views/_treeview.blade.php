{{-- Treeview component --}}
@php
$treeviewType = $treeviewType ?? 'sidebar';
$rootId = 1; // QubitInformationObject::ROOT_ID
@endphp
<ul class="nav nav-tabs border-0" id="treeview-menu" role="tablist">

  @if($treeviewType === 'sidebar')
    <li class="nav-item" role="presentation">
      <button
        class="nav-link active"
        id="treeview-tab"
        data-bs-toggle="tab"
        data-bs-target="#treeview"
        type="button"
        role="tab"
        aria-controls="treeview"
        aria-selected="true">
        {{ __('Holdings') }}
      </button>
    </li>
  @endif

  <li class="nav-item" role="presentation">
    <button
        class="nav-link{{ $treeviewType !== 'sidebar' ? ' active' : '' }}"
        id="treeview-search-tab"
        data-bs-toggle="tab"
        data-bs-target="#treeview-search"
        type="button"
        role="tab"
        aria-controls="treeview-search"
        aria-selected="{{ $treeviewType !== 'sidebar' ? 'true' : 'false' }}">
        {{ __('Quick search') }}
      </button>
  </li>

</ul>

<div class="tab-content mb-3" id="treeview-content">

  @if($treeviewType === 'sidebar')
    <div class="tab-pane fade show active" id="treeview" role="tabpanel" aria-labelledby="treeview-tab" data-current-id="{{ $resource->id }}" data-sortable="{{ empty($sortable) ? 'false' : 'true' }}">

      <ul class="list-group rounded-0">

        @foreach($ancestors ?? [] as $ancestor)
          @if($rootId == $ancestor->id)
            @continue
          @endif

          @include('ahg-display::_treeview-node', [
            'node' => $ancestor,
            'options' => ['ancestor' => true, 'root' => $rootId == ($ancestor->parent_id ?? $ancestor->parentId ?? null)],
            'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $ancestor->slug])]
          ])
        @endforeach

        @if(!isset($children))

          @if($hasPrevSiblings ?? false)
            @include('ahg-display::_treeview-node', [
              'node' => null,
              'options' => ['more' => true],
              'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $prevSiblings[0]->slug]), 'numSiblingsLeft' => $siblingCountPrev ?? 0]
            ])
          @endif

          @if(isset($prevSiblings))
            @foreach($prevSiblings as $prev)
              @include('ahg-display::_treeview-node', [
                'node' => $prev,
                'options' => ['expand' => 1 < ($prev->rgt - $prev->lft)],
                'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $prev->slug])]
              ])
            @endforeach
          @endif

        @endif

        @include('ahg-display::_treeview-node', [
          'node' => $resource,
          'options' => ['ancestor' => $resource->hasChildren ?? false, 'active' => true, 'root' => $rootId == ($resource->parent_id ?? $resource->parentId ?? null)],
          'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $resource->slug])]
        ])

        @if(isset($children))

          @foreach($children as $child)
            @include('ahg-display::_treeview-node', [
              'node' => $child,
              'options' => ['expand' => $child->hasChildren ?? false],
              'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $child->slug])]
            ])
          @endforeach

          @if($hasNextSiblings ?? false)
            @include('ahg-display::_treeview-node', [
              'node' => null,
              'options' => ['more' => true],
              'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $child->slug ?? '']), 'numSiblingsLeft' => $siblingCountNext ?? 0]
            ])
          @endif

        @elseif(isset($nextSiblings))

          @foreach($nextSiblings as $next)
            @include('ahg-display::_treeview-node', [
              'node' => $next,
              'options' => ['expand' => 1 < ($next->rgt - $next->lft)],
              'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $next->slug])]
            ])
          @endforeach

          @if($hasNextSiblings ?? false)
            @php $last = isset($next) ? $next : $resource; @endphp
            @include('ahg-display::_treeview-node', [
              'node' => null,
              'options' => ['more' => true],
              'attrs' => ['xhr-location' => route('informationobject.treeView', ['slug' => $last->slug]), 'numSiblingsLeft' => $siblingCountNext ?? 0]
            ])
          @endif

        @endif

      </ul>

    </div>
  @else
    <div id="fullwidth-treeview-active" data-treeview-alert-close="{{ __('Close') }}" hidden>
      <input type="button" id="fullwidth-treeview-more-button" class="btn btn-sm atom-btn-white" data-label="{{ __('%1% more') }}" value="" />
      <input type="button" id="fullwidth-treeview-reset-button" class="btn btn-sm atom-btn-white" value="{{ __('Reset') }}" />
      <span
        id="fullwidth-treeview-configuration"
        data-collection-url="{{ route('informationobject.show', ['slug' => $resource->collectionRoot->slug ?? $resource->slug]) }}"
        data-collapse-enabled="{{ $collapsible ?? 'false' }}"
        data-opened-text="{{ config('app.ui_label_fullTreeviewCollapseOpenedButtonText', 'Collapse') }}"
        data-closed-text="{{ config('app.ui_label_fullTreeviewCollapseClosedButtonText', 'Expand') }}"
        data-items-per-page="{{ $itemsPerPage ?? 25 }}"
        data-enable-dnd="{{ auth()->check() ? 'yes' : 'no' }}">
      </span>
    </div>
  @endif

  <div class="tab-pane fade{{ $treeviewType !== 'sidebar' ? ' show active' : '' }}" id="treeview-search" role="tabpanel" aria-labelledby="treeview-search-tab">

    <form method="get" role="search" class="p-2 bg-white border" action="{{ route('search.index', ['collection' => $resource->collectionRoot->id ?? $resource->id]) }}" data-not-found="{{ __('No results found.') }}">
      <div class="input-group">
        <input type="text" name="query" class="form-control" aria-label="{{ __('Search hierarchy') }}" placeholder="{{ __('Search hierarchy') }}" aria-describedby="treeview-search-submit-button" required>
        <button class="btn atom-btn-white" type="submit" id="treeview-search-submit-button">
          <i aria-hidden="true" class="fas fa-search"></i>
          <span class="visually-hidden">{{ __('Search') }}</span>
        </button>
      </div>
    </form>

  </div>

</div>
