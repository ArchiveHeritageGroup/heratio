{{-- Block: 1 Column Row (migrated from ahgLandingPagePlugin) --}}
@php
$childBlocks = $block->child_blocks ?? [];
if ($childBlocks instanceof \Illuminate\Support\Collection) {
    $childBlocks = $childBlocks->toArray();
}
if (!is_array($childBlocks)) {
    $childBlocks = [];
}

$col1Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col1');

$isEditorMode = isset($isPreview) && $isPreview === true;
if (!$isEditorMode && empty($col1Blocks)) {
    return;
}
@endphp
<div class="row-1-col">
  @if (!empty($col1Blocks))
    <div class="row g-3">
    @foreach ($col1Blocks as $childBlock)
      @php
      $childConfig = is_object($childBlock) ? ($childBlock->config ?? []) : ($childBlock['config'] ?? []);
      $childMachineName = is_object($childBlock) ? ($childBlock->machine_name ?? '') : ($childBlock['machine_name'] ?? '');
      $childColSpan = is_object($childBlock) ? ($childBlock->col_span ?? 12) : ($childBlock['col_span'] ?? 12);
      $childColSpan = max(1, min(12, (int)$childColSpan ?: 12));

      if (!is_array($childConfig)) $childConfig = json_decode($childConfig, true) ?? [];
      $childData = is_object($childBlock) ? ($childBlock->computed_data ?? null) : ($childBlock['computed_data'] ?? null);
      $templateName = '_block_' . $childMachineName;
      @endphp
      @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
        <div class="col-md-{{ $childColSpan }}">
          @include('ahg-landing-page::blocks.' . $templateName, ['config' => $childConfig, 'data' => $childData, 'block' => $childBlock])
        </div>
      @endif
    @endforeach
    </div>
  @elseif ($isEditorMode)
    <div class="empty-column-preview text-center text-muted py-4 bg-light rounded border border-dashed">
      <small>{{ __('Add content to this row') }}</small>
    </div>
  @endif
</div>
