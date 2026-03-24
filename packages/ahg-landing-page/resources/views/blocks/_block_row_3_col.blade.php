{{-- Block: 3 Column Row (migrated from ahgLandingPagePlugin) --}}
@php
$gap = $config['gap'] ?? '30px';
$stackMobile = $config['stack_mobile'] ?? true;

$childBlocks = $block->child_blocks ?? [];
if ($childBlocks instanceof \Illuminate\Support\Collection) {
    $childBlocks = $childBlocks->toArray();
}
if (!is_array($childBlocks)) {
    $childBlocks = [];
}

$col1Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col1');
$col2Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col2');
$col3Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col3');

$isEditorMode = isset($isPreview) && $isPreview === true;
if (!$isEditorMode && empty($col1Blocks) && empty($col2Blocks) && empty($col3Blocks)) {
    return;
}
@endphp
<div class="row-3-col">
  <div class="row g-4">
    @for ($i = 1; $i <= 3; $i++)
    @php $colBlocks = ${'col' . $i . 'Blocks'}; @endphp
    <div class="{{ $stackMobile ? 'col-12' : '' }} col-md-4">
      @if (!empty($colBlocks))
        @foreach ($colBlocks as $childBlock)
          @php
          $childConfig = is_object($childBlock) ? ($childBlock->config ?? []) : ($childBlock['config'] ?? []);
          $childMachineName = is_object($childBlock) ? ($childBlock->machine_name ?? '') : ($childBlock['machine_name'] ?? '');
          $childData = is_object($childBlock) ? ($childBlock->computed_data ?? null) : ($childBlock['computed_data'] ?? null);
          if (!is_array($childConfig)) $childConfig = json_decode($childConfig, true) ?? [];
          $templateName = '_block_' . $childMachineName;
          @endphp
          @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
            @include('ahg-landing-page::blocks.' . $templateName, ['config' => $childConfig, 'data' => $childData, 'block' => $childBlock])
          @endif
        @endforeach
      @elseif ($isEditorMode)
        <div class="empty-column-preview text-center text-muted py-4 bg-light rounded border border-dashed">
          <small>Column {{ $i }} (empty)</small>
        </div>
      @endif
    </div>
    @endfor
  </div>
</div>
