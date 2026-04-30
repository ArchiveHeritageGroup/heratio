{{-- Landing Page Public Display - migrated from ahgLandingPagePlugin/templates/indexSuccess.php --}}
@php
$isPreview = $isPreview ?? false;
@endphp

@if ($isPreview)
<div class="alert alert-warning alert-dismissible fade show m-3" role="alert">
  <i class="bi bi-eye"></i> <strong>{{ __('Preview Mode') }}</strong> - This is how the page will appear to visitors.
  Hidden blocks are shown with reduced opacity.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="landing-page" data-page-id="{{ $page->id }}">
  @php
  // Group consecutive blocks that have col_span < 12 into rows
  $blockGroups = [];
  $currentGroup = [];
  $currentColTotal = 0;

  foreach ($blocks as $block) {
      $colSpan = (int)($block->col_span ?? 12) ?: 12;

      // Full-width blocks or row containers get their own group
      if ($colSpan >= 12 || in_array($block->machine_name, ['hero_banner', 'row_2_col', 'row_3_col'])) {
          if (!empty($currentGroup)) {
              $blockGroups[] = ['type' => 'row', 'blocks' => $currentGroup];
              $currentGroup = [];
              $currentColTotal = 0;
          }
          $blockGroups[] = ['type' => 'single', 'blocks' => [$block]];
      } else {
          if ($currentColTotal + $colSpan > 12) {
              if (!empty($currentGroup)) {
                  $blockGroups[] = ['type' => 'row', 'blocks' => $currentGroup];
              }
              $currentGroup = [$block];
              $currentColTotal = $colSpan;
          } else {
              $currentGroup[] = $block;
              $currentColTotal += $colSpan;
          }
      }
  }

  if (!empty($currentGroup)) {
      $blockGroups[] = ['type' => 'row', 'blocks' => $currentGroup];
  }
  @endphp

  @foreach ($blockGroups as $group)
    @if ($group['type'] === 'row' && count($group['blocks']) > 1)
      <div class="container">
        <div class="row g-4">
          @foreach ($group['blocks'] as $rawBlock)
            @php
            $colSpan = (int)($rawBlock->col_span ?? 12) ?: 12;
            $config = is_array($rawBlock->config) ? $rawBlock->config : (is_string($rawBlock->config) ? json_decode($rawBlock->config, true) ?? [] : []);
            $computedData = $rawBlock->computed_data ?? null;

            $blockClasses = ['landing-block', 'landing-block-' . $rawBlock->machine_name];
            if (!empty($rawBlock->css_classes)) {
                $blockClasses[] = $rawBlock->css_classes;
            }
            if (!$rawBlock->is_visible && $isPreview) {
                $blockClasses[] = 'opacity-50';
            }
            $blockClasses[] = 'py-' . ($rawBlock->padding_top ?? '3');

            $blockStyles = [];
            if (!empty($rawBlock->background_color) && $rawBlock->background_color !== '#ffffff') {
                $blockStyles[] = 'background-color: ' . $rawBlock->background_color;
            }
            if (!empty($rawBlock->text_color) && $rawBlock->text_color !== '#212529') {
                $blockStyles[] = 'color: ' . $rawBlock->text_color;
            }
            @endphp
            <div class="col-md-{{ $colSpan }}">
              <div class="{{ implode(' ', $blockClasses) }}"
                   style="{{ implode('; ', $blockStyles) }}"
                   data-block-id="{{ $rawBlock->id }}">
                @php
                $templateName = '_block_' . $rawBlock->machine_name;
                $data = $computedData;
                $block = $rawBlock;
                @endphp
                @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
                  @include('ahg-landing-page::blocks.' . $templateName, ['block' => $block, 'config' => $config, 'data' => $data])
                @else
                  <div class="alert alert-warning">Block template not found: {{ e($rawBlock->machine_name) }}</div>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @else
      @php
      $rawBlock = $group['blocks'][0];
      $config = is_array($rawBlock->config) ? $rawBlock->config : (is_string($rawBlock->config) ? json_decode($rawBlock->config, true) ?? [] : []);
      $computedData = $rawBlock->computed_data ?? null;

      $sectionClasses = ['landing-block', 'landing-block-' . $rawBlock->machine_name];
      if (!empty($rawBlock->css_classes)) {
          $sectionClasses[] = $rawBlock->css_classes;
      }
      if (!$rawBlock->is_visible && $isPreview) {
          $sectionClasses[] = 'opacity-50';
      }
      $sectionClasses[] = 'py-' . ($rawBlock->padding_top ?? '3');

      $sectionStyles = [];
      if (!empty($rawBlock->background_color) && $rawBlock->background_color !== '#ffffff') {
          $sectionStyles[] = 'background-color: ' . $rawBlock->background_color;
      }
      if (!empty($rawBlock->text_color) && $rawBlock->text_color !== '#212529') {
          $sectionStyles[] = 'color: ' . $rawBlock->text_color;
      }
      $containerClass = $rawBlock->container_type ?? 'container';
      @endphp
      <section class="{{ implode(' ', $sectionClasses) }}"
               style="{{ implode('; ', $sectionStyles) }}"
               data-block-id="{{ $rawBlock->id }}">
        @if ($rawBlock->machine_name !== 'hero_banner' && $containerClass !== 'fluid')
        <div class="{{ $containerClass }}">
        @endif
        @php
        $templateName = '_block_' . $rawBlock->machine_name;
        $data = $computedData;
        $block = $rawBlock;
        @endphp
        @if (view()->exists('ahg-landing-page::blocks.' . $templateName))
          @include('ahg-landing-page::blocks.' . $templateName, ['block' => $block, 'config' => $config, 'data' => $data])
        @else
          <div class="alert alert-warning">Block template not found: {{ e($rawBlock->machine_name) }}</div>
        @endif
        @if ($rawBlock->machine_name !== 'hero_banner' && $containerClass !== 'fluid')
        </div>
        @endif
      </section>
    @endif
  @endforeach
</div>
