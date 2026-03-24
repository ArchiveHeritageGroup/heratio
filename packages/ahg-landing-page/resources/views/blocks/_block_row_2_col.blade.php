{{-- Block: 2 Column Row (migrated from ahgLandingPagePlugin) --}}
@php
$gap = $config['gap'] ?? '30px';
$stackMobile = $config['stack_mobile'] ?? true;
$col1Width = str_replace('%', '', $config['col1_width'] ?? '50');
$col2Width = str_replace('%', '', $config['col2_width'] ?? '50');

$childBlocks = $block->child_blocks ?? [];
if ($childBlocks instanceof \Illuminate\Support\Collection) {
    $childBlocks = $childBlocks->toArray();
}
if (!is_array($childBlocks)) {
    $childBlocks = [];
}

$col1Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col1');
$col2Blocks = array_filter($childBlocks, fn($b) => (is_object($b) ? ($b->column_slot ?? '') : ($b['column_slot'] ?? '')) === 'col2');

$isEditorMode = isset($isPreview) && $isPreview === true;
if (!$isEditorMode && empty($col1Blocks) && empty($col2Blocks)) {
    return;
}

$colMap = ['15' => '2', '20' => '2', '25' => '3', '33' => '4', '40' => '5', '50' => '6', '60' => '7', '66' => '8', '75' => '9', '80' => '10', '85' => '10'];
$col1Class = 'col-md-' . ($colMap[$col1Width] ?? '6');
$col2Class = 'col-md-' . ($colMap[$col2Width] ?? '6');
@endphp
<div class="row-2-col">
  <div class="row g-4">
    <!-- Column 1 -->
    <div class="{{ $stackMobile ? 'col-12' : '' }} {{ $col1Class }}">
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
          <small>Column 1 (empty)</small>
        </div>
      @endif
    </div>

    <!-- Column 2 -->
    <div class="{{ $stackMobile ? 'col-12' : '' }} {{ $col2Class }}">
      @if (!empty($col2Blocks))
        <div class="row g-3">
        @foreach ($col2Blocks as $childBlock)
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
          <small>Column 2 (empty)</small>
        </div>
      @endif
    </div>
  </div>
</div>
