{{-- Partial: block-card for the editor canvas (migrated from ahgLandingPagePlugin) --}}
@php
$hiddenClass = $block->is_visible ? '' : 'block-hidden';
$config = is_array($block->config) ? $block->config : (is_string($block->config) ? json_decode($block->config, true) ?? [] : []);
$isColumnLayout = in_array($block->machine_name, ['row_1_col', 'row_2_col', 'row_3_col']);
@endphp

<div class="block-card card mb-3 {{ $hiddenClass }}" data-block-id="{{ $block->id }}">
  <div class="card-header d-flex align-items-center py-2 cursor-grab block-handle bg-white">
    <span class="me-2 text-muted"><i class="bi bi-grip-vertical"></i></span>
    <span class="block-label flex-grow-1 fw-medium">
      {{ $block->title ?? $block->type_label }}
    </span>
    <div class="block-actions btn-group btn-group-sm">
      <button type="button" class="btn btn-sm {{ $block->is_visible ? 'btn-outline-secondary' : 'btn-warning' }} btn-visibility"
              title="{{ $block->is_visible ? 'Hide' : 'Show' }}">
        <i class="bi {{ $block->is_visible ? 'bi-eye' : 'bi-eye-slash' }}"></i>
      </button>
      <button type="button" class="btn btn-sm btn-outline-primary btn-edit" title="Edit">
        <i class="bi bi-pencil"></i>
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary btn-duplicate" title="Duplicate">
        <i class="bi bi-clipboard"></i>
      </button>
      <button type="button" class="btn btn-sm btn-outline-danger btn-delete" title="Delete">
        <i class="bi bi-trash"></i>
      </button>
    </div>
  </div>

  <div class="card-body block-preview p-3 bg-light">
    @if ($isColumnLayout)
      @php
      $numCols = $block->machine_name === 'row_3_col' ? 3 : ($block->machine_name === 'row_2_col' ? 2 : 1);
      $childBlocks = $block->child_blocks ?? [];
      @endphp
      <div class="row g-2">
        @for ($i = 1; $i <= $numCols; $i++)
          @php
          $colSlot = 'col' . $i;
          $colBlocks = collect(is_array($childBlocks) ? $childBlocks : (method_exists($childBlocks, 'toArray') ? $childBlocks->toArray() : []))
              ->filter(fn($b) => ($b->column_slot ?? '') === $colSlot);
          @endphp
          <div class="col">
            <div class="column-drop-zone border border-2 border-dashed rounded p-2 text-center"
                 data-parent-block="{{ $block->id }}"
                 data-column="{{ $colSlot }}"
                 style="min-height: 80px; background: #fff;">
              @if ($colBlocks->isEmpty())
                <div class="empty-column text-muted py-2">
                  <small><i class="bi bi-arrow-down"></i> Col {{ $i }}</small>
                </div>
              @else
                @foreach ($colBlocks as $childBlock)
                  <div class="nested-block card mb-1" data-block-id="{{ $childBlock->id }}">
                    <div class="card-body py-1 px-2 small d-flex align-items-center">
                      <span class="drag-handle me-1" style="cursor: grab;"><i class="bi bi-grip-vertical"></i></span>
                      <span class="flex-grow-1 text-truncate">{{ $childBlock->title ?: $childBlock->type_label }}</span>
                      <button type="button" class="btn btn-link btn-sm p-0 px-1 btn-edit-nested text-primary"
                              data-block-id="{{ $childBlock->id }}" title="Edit"><i class="bi bi-pencil"></i></button>
                      <button type="button" class="btn btn-link btn-sm p-0 btn-delete-nested text-danger"
                              data-block-id="{{ $childBlock->id }}" title="Delete"><i class="bi bi-trash"></i></button>
                    </div>
                  </div>
                @endforeach
              @endif
            </div>
          </div>
        @endfor
      </div>
    @else
      <small class="text-muted d-block mb-1">{{ $block->type_label }}</small>
      @switch($block->machine_name)
        @case('hero_banner')
          <strong>{{ e($config['title'] ?? 'Hero Title') }}</strong>
          @break
        @case('search_box')
          <i class="bi bi-search"></i> "{{ e($config['placeholder'] ?? 'Search...') }}"
          @break
        @case('browse_panels')
          @php $panelCount = count($config['panels'] ?? []); @endphp
          <i class="bi bi-folder"></i> {{ $panelCount }} browse panels
          @break
        @case('recent_items')
          <i class="bi bi-clock-history"></i> {{ e($config['title'] ?? 'Recent Items') }} ({{ $config['limit'] ?? 6 }})
          @break
        @case('statistics')
          @php $statCount = count($config['stats'] ?? []); @endphp
          <i class="bi bi-bar-chart"></i> {{ e($config['title'] ?? 'Statistics') }} ({{ $statCount }})
          @break
        @case('text_content')
          <i class="bi bi-file-text"></i> {{ e(\Illuminate\Support\Str::limit(strip_tags($config['content'] ?? 'Text content'), 50)) }}
          @break
        @case('holdings_list')
          <i class="bi bi-book"></i> {{ e($config['title'] ?? 'Holdings') }} ({{ $config['limit'] ?? 10 }})
          @break
        @case('image_carousel')
          @php $imgCount = count($config['images'] ?? []); @endphp
          <i class="bi bi-images"></i> {{ $imgCount }} images
          @break
        @case('quick_links')
          @php $linkCount = count($config['links'] ?? []); @endphp
          <i class="bi bi-link-45deg"></i> {{ $linkCount }} links
          @break
        @case('header_section')
          <i class="bi bi-arrow-up"></i> Header
          @break
        @case('footer_section')
          <i class="bi bi-arrow-down"></i> Footer
          @break
        @case('row_1_col')
          <i class="bi bi-layout-wtf"></i> 1 Column Layout
          @break
        @case('divider')
          &mdash; Divider ({{ $config['style'] ?? 'line' }})
          @break
        @case('spacer')
          <i class="bi bi-arrows-expand"></i> Spacer ({{ $config['height'] ?? '50px' }})
          @break
        @default
          {{ $block->type_label }}
      @endswitch
    @endif
  </div>
</div>
