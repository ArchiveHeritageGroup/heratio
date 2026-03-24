{{-- Block: Quick Links (migrated from ahgLandingPagePlugin) --}}
@php
$links = $config['links'] ?? [];
$title = $config['title'] ?? '';
$layout = $config['layout'] ?? 'inline';
$style = $config['style'] ?? 'buttons';
@endphp

@if (!empty($title))
  <h2 class="h5 mb-3">{{ e($title) }}</h2>
@endif

@if ($layout === 'grid')
  <div class="row g-3">
    @foreach ($links as $link)
      <div class="col-6 col-md-4 col-lg-3">
        <a href="{{ e($link['url'] ?? '#') }}"
           class="{{ $style === 'cards' ? 'card text-decoration-none h-100' : 'btn btn-outline-primary w-100' }}"
           {!! !empty($link['new_window']) ? 'target="_blank"' : '' !!}>
          @if ($style === 'cards')
            <div class="card-body text-center">
              @if (!empty($link['icon']))
                <i class="bi {{ $link['icon'] }} display-6 mb-2"></i>
              @endif
              <div>{{ e($link['label'] ?? '') }}</div>
            </div>
          @else
            @if (!empty($link['icon']))
              <i class="bi {{ $link['icon'] }} me-1"></i>
            @endif
            {{ e($link['label'] ?? '') }}
          @endif
        </a>
      </div>
    @endforeach
  </div>
@elseif ($layout === 'list')
  <ul class="list-group">
    @foreach ($links as $link)
      <li class="list-group-item">
        <a href="{{ e($link['url'] ?? '#') }}"
           class="text-decoration-none"
           {!! !empty($link['new_window']) ? 'target="_blank"' : '' !!}>
          @if (!empty($link['icon']))
            <i class="bi {{ $link['icon'] }} me-2"></i>
          @endif
          {{ e($link['label'] ?? '') }}
        </a>
      </li>
    @endforeach
  </ul>
@else
  <div class="d-flex flex-wrap gap-2">
    @foreach ($links as $link)
      @if ($style === 'buttons')
        <a href="{{ e($link['url'] ?? '#') }}"
           class="btn btn-outline-primary"
           {!! !empty($link['new_window']) ? 'target="_blank"' : '' !!}>
          @if (!empty($link['icon']))
            <i class="bi {{ $link['icon'] }} me-1"></i>
          @endif
          {{ e($link['label'] ?? '') }}
        </a>
      @else
        <a href="{{ e($link['url'] ?? '#') }}"
           class="text-decoration-none me-3"
           {!! !empty($link['new_window']) ? 'target="_blank"' : '' !!}>
          @if (!empty($link['icon']))
            <i class="bi {{ $link['icon'] }} me-1"></i>
          @endif
          {{ e($link['label'] ?? '') }}
        </a>
      @endif
    @endforeach
  </div>
@endif
