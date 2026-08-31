<!doctype html>
<html><head>
<meta charset="utf-8">
<title>{{ __('Container labels') }}</title>
<style>
  body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 10mm; }
  .sheet { display: flex; flex-wrap: wrap; gap: 6mm; }
  .lbl { width: 62mm; min-height: 34mm; border: 1px solid #999; border-radius: 2mm;
         padding: 3mm; display: flex; gap: 3mm; align-items: center; page-break-inside: avoid; }
  .lbl .meta { flex: 1; min-width: 0; }
  .lbl .name { font-weight: 700; font-size: 11pt; line-height: 1.2; word-break: break-word; }
  .lbl .sub  { font-size: 8pt; color: #444; margin-top: 1mm; }
  .lbl img   { width: 26mm; height: 26mm; }
  .noprint { margin-bottom: 6mm; }
  @media print { .noprint { display: none; } body { margin: 0; } }
</style>
</head><body>
<div class="noprint">
  <button onclick="window.print()">{{ __('Print') }}</button>
  <span style="margin-left:1rem;color:#555">{{ $labels->count() }} {{ __('label(s)') }}</span>
</div>
<div class="sheet">
@foreach($labels as $c)
  <div class="lbl">
    <div class="meta">
      <div class="name">{{ $c->name ?: __('(unnamed)') }}</div>
      <div class="sub">
        @if($c->type_name){{ $c->type_name }}<br>@endif
        @if($c->location){{ $c->location }}<br>@endif
        {{ __('Container') }} #{{ $c->id }}
      </div>
    </div>
    {{-- Rendered locally. A store room is exactly the place with no reliable
         network, and a label that needs one to print is no label at all. --}}
    @if($c->qr)<img src="{{ $c->qr }}" alt="{{ __('QR') }}">@endif
  </div>
@endforeach
</div>
</body></html>
