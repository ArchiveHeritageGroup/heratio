@php
  $nonce = function_exists('csp_nonce') ? csp_nonce() : '';
  $w = (float) $template->page_width_mm;
  $h = (float) $template->page_height_mm;
  $fontClass = fn ($f) => 'f'.str_replace('.', '_', (string) $f);
  // Positions come through classes, not style attributes, so a strict style CSP still applies them.
  $xs = []; $ys = [];
  foreach ($sheets['pages'] as $page) { foreach ($page as $l) { $xs[(string) $l['x']] = $l['x']; $ys[(string) $l['y']] = $l['y']; } }
  $posClass = fn ($axis, $v) => $axis.str_replace(['.', '-'], ['_', 'm'], (string) $v);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Labels') }} - {{ $template->name }}</title>
<style nonce="{{ $nonce }}">
@page { size: {{ $w }}mm {{ $h }}mm; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #e9ecef; color: #000; font-family: Arial, Helvetica, sans-serif; }
.toolbar { background: #fff; padding: 12px 16px; border-bottom: 1px solid #adb5bd; }
.toolbar p { margin: 4px 0; font-size: 14px; }
.toolbar button { padding: 6px 14px; font-size: 14px; }
.warn { color: #842029; }
.ruler { width: 100mm; height: 4mm; border: 1px solid #000; border-top: 0; margin-top: 6px; }
.scroll { overflow-x: auto; padding: 16px; }
.sheet { position: relative; width: {{ $w }}mm; height: {{ $h }}mm; background: #fff; margin: 0 auto 16px; overflow: hidden; break-after: page; box-shadow: 0 0 4px rgba(0,0,0,.4); }
.sheet:last-child { break-after: auto; }
.label { position: absolute; width: {{ (float) $template->label_width_mm }}mm; height: {{ (float) $template->label_height_mm }}mm; padding: 1.5mm; overflow: hidden; display: flex; align-items: center; justify-content: center; gap: 1mm; outline: .2mm dashed #adb5bd; }
.spine { font-family: "DejaVu Sans Mono", "Courier New", monospace; font-weight: 700; line-height: 1.15; text-align: center; white-space: nowrap; }
.combined .spine { flex: 0 0 36%; }
.code { display: flex; flex-direction: column; align-items: center; min-width: 0; flex: 1; }
.code svg { display: block; }
.title { font-size: 2.2mm; line-height: 3mm; white-space: nowrap; overflow: hidden; max-width: 100%; }
.human { font-family: "DejaVu Sans Mono", "Courier New", monospace; font-size: 2.2mm; line-height: 3mm; }
@foreach($sheets['fonts'] as $f) .{{ $fontClass($f) }} { font-size: {{ $f }}mm; }
@endforeach
@foreach($xs as $v) .{{ $posClass('x', $v) }} { left: {{ $v }}mm; }
@endforeach
@foreach($ys as $v) .{{ $posClass('y', $v) }} { top: {{ $v }}mm; }
@endforeach
@media print {
  html, body { background: #fff; }
  .toolbar { display: none; }
  .scroll { padding: 0; overflow: visible; }
  .sheet { margin: 0; box-shadow: none; }
  .label { outline: {{ $outlines ? '.2mm solid #000' : 'none' }}; }
}
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" id="print-labels" @disabled($sheets['count'] === 0)>{{ __('Print') }}</button>
  <p><strong>{{ trans_choice(':count label on|:count labels on', $sheets['count'], ['count' => $sheets['count']]) }} {{ trans_choice(':count sheet|:count sheets', count($sheets['pages']), ['count' => count($sheets['pages'])]) }}</strong> - {{ $template->name }}</p>
  <p>{{ __('In the print dialog set scale to 100% (not "fit to page") and margins to none. At 100% browser zoom the bar below should measure 100 mm on a calibrated screen; the printed sheet is what counts, so test on plain paper first.') }}</p>
  <div class="ruler" role="img" aria-label="{{ __('100 mm reference bar') }}"></div>
  @if($resolved['truncated'])<p class="warn">{{ __('Only the first :n copies were taken. Print the rest in another run.', ['n' => number_format($maxLabels)]) }}</p>@endif
  @if($resolved['not_found'])<p class="warn">{{ __('Not found:') }} {{ implode(', ', array_slice($resolved['not_found'], 0, 50)) }}@if(count($resolved['not_found']) > 50) ... (+{{ count($resolved['not_found']) - 50 }})@endif</p>@endif
  @if($resolved['other_branch'])<p class="warn">{{ __(':n copies belong to another branch and were left out.', ['n' => $resolved['other_branch']]) }}</p>@endif
  @if($resolved['no_branch'])<p class="warn">{{ __(':n copies have no branch assigned and were left out. Assign them a branch, or print them from the all-branches view if your account has it.', ['n' => $resolved['no_branch']]) }}</p>@endif
  @if($sheets['skipped'])
    <p class="warn">{{ __('No label for:') }}
      @foreach(array_slice($sheets['skipped'], 0, 50) as $s){{ $s['ref'] }} ({{ $s['reason'] }})@if(! $loop->last), @endif @endforeach
      @if(count($sheets['skipped']) > 50) ... (+{{ count($sheets['skipped']) - 50 }})@endif
    </p>
  @endif
</div>
<div class="scroll">
@foreach($sheets['pages'] as $page)
  <section class="sheet" aria-label="{{ __('Sheet :n', ['n' => $loop->iteration]) }}">
    @foreach($page as $l)
      <div class="label {{ $l['format'] }} {{ $posClass('x', $l['x']) }} {{ $posClass('y', $l['y']) }}">
        @if($l['lines'])
          <div class="spine {{ $fontClass($l['font']) }}">@foreach($l['lines'] as $line){{ $line }}@unless($loop->last)<br>@endunless @endforeach</div>
        @endif
        @if($l['barcode'])
          <div class="code">
            @if($l['title'])<div class="title">{{ $l['title'] }}</div>@endif
            {!! $l['svg'] !!}
            <div class="human">{{ $l['barcode'] }}</div>
          </div>
        @endif
      </div>
    @endforeach
  </section>
@endforeach
</div>
<script nonce="{{ $nonce }}">document.getElementById('print-labels').addEventListener('click', function () { window.print(); });</script>
</body>
</html>
