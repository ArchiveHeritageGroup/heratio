{{-- Partial: nested-block display within columns (editor view) (migrated from ahgLandingPagePlugin) --}}
@php
$config = is_array($block->config) ? $block->config : (is_string($block->config) ? json_decode($block->config, true) ?? [] : []);
@endphp
<div class="nested-block card mb-2" data-block-id="{{ $block->id }}">
  <div class="card-header py-1 px-2 d-flex align-items-center bg-white">
    <span class="drag-handle me-2" style="cursor: grab;"><i class="bi bi-grip-vertical"></i></span>
    <span class="small flex-grow-1">{{ $block->type_label }}</span>
    <div class="btn-group btn-group-sm">
      <button type="button" class="btn btn-link btn-sm p-0 px-1 btn-edit-nested text-primary"
              data-block-id="{{ $block->id }}" title="Edit"><i class="bi bi-pencil"></i></button>
      <button type="button" class="btn btn-link btn-sm p-0 btn-delete-nested text-danger"
              data-block-id="{{ $block->id }}" title="Delete"><i class="bi bi-trash"></i></button>
    </div>
  </div>
  <div class="card-body py-2 px-2 bg-light small">
    @switch($block->machine_name)
      @case('text_content')
        {{ e(\Illuminate\Support\Str::limit(strip_tags($config['content'] ?? ''), 40)) }}...
        @break
      @case('search_box')
        <i class="bi bi-search"></i> Search Box
        @break
      @case('statistics')
        <i class="bi bi-bar-chart"></i> {{ count($config['stats'] ?? []) }} stats
        @break
      @case('recent_items')
        <i class="bi bi-clock-history"></i> Recent Items
        @break
      @case('quick_links')
        <i class="bi bi-link-45deg"></i> {{ count($config['links'] ?? []) }} links
        @break
      @default
        {{ $block->type_label }}
    @endswitch
  </div>
</div>
