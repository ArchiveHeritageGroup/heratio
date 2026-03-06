@foreach($nodes as $node)
  <li class="list-group-item" style="padding-left: {{ ($depth * 1.5) + 1 }}rem;">
    <div class="d-flex align-items-center">
      <div class="flex-grow-1">
        <a href="{{ route('menu.show', $node['id']) }}" class="fw-semibold text-decoration-none">
          {{ $node['label'] ?: $node['name'] ?: '[Unnamed]' }}
        </a>
        @if($node['name'])
          <span class="text-muted small ms-2">({{ $node['name'] }})</span>
        @endif
        @if($node['path'])
          <span class="text-muted small ms-2">
            <i class="fas fa-link fa-sm me-1"></i>{{ $node['path'] }}
          </span>
        @endif
      </div>
      @if(!empty($node['children']))
        <span class="badge bg-secondary">{{ count($node['children']) }}</span>
      @endif
    </div>
  </li>
  @if(!empty($node['children']))
    @include('ahg-menu-manage::partials.tree-node', ['nodes' => $node['children'], 'depth' => $depth + 1])
  @endif
@endforeach
