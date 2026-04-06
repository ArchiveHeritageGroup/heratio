@php
    $hasChildren = !empty($node['children']);
    $indent = $depth * 20;
@endphp
<div class="tree-node" style="margin-left: {{ $indent }}px;">
    <div class="d-flex align-items-start py-1">
        @if ($hasChildren)
            <button class="btn btn-sm btn-link p-0 me-1 tree-toggle" type="button"
                    data-bs-toggle="collapse" data-bs-target="#tree-children-{{ $node['id'] ?? 'root' }}">
                <i class="fas fa-caret-right"></i>
            </button>
        @else
            <span class="me-1" style="width: 16px; display: inline-block;"></span>
        @endif
        <div>
            <strong class="small">{{ $node['title'] ?? 'Untitled' }}</strong>
            @if (!empty($node['level']))
                <span class="badge bg-secondary ms-1" style="font-size: 0.7rem;">{{ $node['level'] }}</span>
            @endif
            @if (!empty($node['id']))
                <span class="text-muted ms-1" style="font-size: 0.75rem;">[{{ $node['id'] }}]</span>
            @endif
            @if (!empty($node['summary']))
                <p class="text-muted small mb-0">{{ $node['summary'] }}</p>
            @endif
            @if (!empty($node['keywords']))
                <div class="mt-1">
                    @foreach ($node['keywords'] as $kw)
                        <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">{{ $kw }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @if ($hasChildren)
        <div class="collapse" id="tree-children-{{ $node['id'] ?? 'root' }}">
            @foreach ($node['children'] as $child)
                @include('ahg-discovery::partials.tree-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </div>
    @endif
</div>
