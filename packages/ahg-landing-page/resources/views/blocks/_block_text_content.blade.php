{{-- Block: Text Content (migrated from ahgLandingPagePlugin) --}}
@php
$title = $config['title'] ?? '';
$content = $config['content'] ?? '';
$image = $config['image'] ?? '';
$imagePosition = $config['image_position'] ?? 'none';
$imageWidth = $config['image_width'] ?? '33%';

$hasImage = !empty($image) && $imagePosition !== 'none';
$colWidth = str_replace('%', '', $imageWidth);
$contentColWidth = 12 - (int)(12 * $colWidth / 100);
$imageColWidth = 12 - $contentColWidth;
@endphp

<div class="text-content-block">
  @if (!empty($title))
    <h2 class="h4 mb-3">{{ e($title) }}</h2>
  @endif

  @if ($hasImage && in_array($imagePosition, ['left', 'right']))
    <div class="row align-items-center">
      @if ($imagePosition === 'left')
        <div class="col-md-{{ $imageColWidth }}">
          <img src="{{ e($image) }}" class="img-fluid rounded" alt="">
        </div>
      @endif

      <div class="col-md-{{ $contentColWidth }}">
        <div class="content-text">
          {!! $content !!}
        </div>
      </div>

      @if ($imagePosition === 'right')
        <div class="col-md-{{ $imageColWidth }}">
          <img src="{{ e($image) }}" class="img-fluid rounded" alt="">
        </div>
      @endif
    </div>
  @elseif ($hasImage && $imagePosition === 'top')
    <img src="{{ e($image) }}" class="img-fluid rounded mb-3" alt="">
    <div class="content-text">
      {!! $content !!}
    </div>
  @elseif ($hasImage && $imagePosition === 'bottom')
    <div class="content-text mb-3">
      {!! $content !!}
    </div>
    <img src="{{ e($image) }}" class="img-fluid rounded" alt="">
  @else
    <div class="content-text">
      {!! $content !!}
    </div>
  @endif
</div>
