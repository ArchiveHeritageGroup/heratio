{{-- Block: 2 Column Row Public Display (migrated from ahgLandingPagePlugin) --}}
@php
$col1Width = $config['col1_width'] ?? '50%';
$gap = $config['gap'] ?? '30px';
$stackMobile = $config['stack_mobile'] ?? true;

$col1Pct = (int)str_replace('%', '', $col1Width);
$col1Md = round($col1Pct / 100 * 12);
$col2Md = 12 - $col1Md;

$childBlocks = $block->child_blocks ?? [];
$col1Blocks = array_filter(is_array($childBlocks) ? $childBlocks : [], fn($b) => ($b->column_slot ?? '') === 'col1');
$col2Blocks = array_filter(is_array($childBlocks) ? $childBlocks : [], fn($b) => ($b->column_slot ?? '') === 'col2');

if (empty($col1Blocks) && empty($col2Blocks)) return;
@endphp

<div class="row-2-col">
  <div class="row g-4">
    <div class="{{ $stackMobile ? 'col-12' : '' }} col-md-{{ $col1Md }}">
      @foreach ($col1Blocks as $childBlock)
        @php
        $childConfig = is_object($childBlock) ? ($childBlock->config ?? []) : ($childBlock['config'] ?? []);
        $childMachineName = is_object($childBlock) ? ($childBlock->machine_name ?? '') : ($childBlock['machine_name'] ?? '');
        if (!is_array($childConfig)) $childConfig = json_decode($childConfig, true) ?? [];
        $childData = is_object($childBlock) ? ($childBlock->computed_data ?? null) : ($childBlock['computed_data'] ?? null);
        $templateName = '_block_' . $childMachineName;
        @endphp
        @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
          @include('ahg-landing-page::blocks.' . $templateName, ['config' => $childConfig, 'data' => $childData, 'block' => $childBlock])
        @endif
      @endforeach
    </div>
    <div class="{{ $stackMobile ? 'col-12' : '' }} col-md-{{ $col2Md }}">
      @foreach ($col2Blocks as $childBlock)
        @php
        $childConfig = is_object($childBlock) ? ($childBlock->config ?? []) : ($childBlock['config'] ?? []);
        $childMachineName = is_object($childBlock) ? ($childBlock->machine_name ?? '') : ($childBlock['machine_name'] ?? '');
        if (!is_array($childConfig)) $childConfig = json_decode($childConfig, true) ?? [];
        $childData = is_object($childBlock) ? ($childBlock->computed_data ?? null) : ($childBlock['computed_data'] ?? null);
        $templateName = '_block_' . $childMachineName;
        @endphp
        @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
          @include('ahg-landing-page::blocks.' . $templateName, ['config' => $childConfig, 'data' => $childData, 'block' => $childBlock])
        @endif
      @endforeach
    </div>
  </div>
</div>
