{{-- Recursive tree node partial --}}
@php
    $hasChildren = !empty($node['children']);
    $childCount = $hasChildren ? count($node['children']) : 0;
    $module = $module ?? 'informationobject';

    $levelIcons = [
        'Fonds' => 'bi-archive',
        'Collection' => 'bi-collection',
        'Series' => 'bi-folder2',
        'Sub-series' => 'bi-folder2-open',
        'File' => 'bi-file-earmark',
        'Item' => 'bi-file-earmark-text',
    ];
    $icon = $levelIcons[$node['level_of_description'] ?? ''] ?? 'bi-folder';
@endphp
<li>
    <div class="tree-item">
        @if($hasChildren)
            <span class="tree-toggle"
                  role="button"
                  aria-expanded="false"
                  onclick="this.classList.toggle('expanded'); this.closest('li').querySelector(':scope > ul')?.classList.toggle('d-none');">
                <i class="bi bi-chevron-right"></i>
            </span>
        @else
            <span class="tree-toggle" style="visibility: hidden;">
                <i class="bi bi-chevron-right"></i>
            </span>
        @endif

        <span class="tree-icon">
            <i class="bi {{ $icon }}"></i>
        </span>

        <span class="tree-label">
            <a href="{{ route($module . '.show', $node['slug']) }}">
                {{ $node['title'] ?? $node['slug'] }}
            </a>
        </span>

        @if($childCount > 0)
            <span class="tree-count">{{ $childCount }}</span>
        @endif
    </div>

    @if($hasChildren)
        <ul class="d-none">
            @each('theme::display-modes._tree-node', $node['children'], 'node')
        </ul>
    @endif
</li>
