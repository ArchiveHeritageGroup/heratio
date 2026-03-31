<nav id="settings-menu" class="list-group mb-3 sticky-top" style="top: 1rem;">
  @foreach ($menu ?? [] as $node)
    <a href="{{ route('settings.' . $node['action']) }}"
       class="list-group-item list-group-item-action d-flex align-items-center{{ $node['active'] ? ' active' : '' }}">
      <i class="fas {{ $node['icon'] ?? 'fa-cog' }} me-2" style="width:18px;text-align:center;"></i>
      {{ $node['label'] }}
    </a>
  @endforeach
</nav>
