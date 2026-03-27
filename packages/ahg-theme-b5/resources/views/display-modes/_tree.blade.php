{{--
  Tree/Hierarchy View Partial

  @param \Illuminate\Support\Collection|array $items   Items to display (hierarchical structure)
  @param string $module Module context (route name prefix)
--}}
@php
    $items = $items ?? [];
    $module = $module ?? 'informationobject';

    $levelIcons = [
        'Fonds' => 'bi-archive',
        'Collection' => 'bi-collection',
        'Series' => 'bi-folder2',
        'Sub-series' => 'bi-folder2-open',
        'File' => 'bi-file-earmark',
        'Item' => 'bi-file-earmark-text',
    ];
@endphp

<div class="display-tree-view" data-display-container>
    @if(empty($items))
        <p class="text-muted text-center py-4">{{ __('No items to display') }}</p>
    @else
        <ul>
            @each('theme::display-modes._tree-node', $items, 'node')
        </ul>
    @endif
</div>
