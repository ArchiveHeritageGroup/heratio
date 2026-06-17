@extends('ahg-theme-b5::layout')

@section('title', 'Batch Print Labels')

@php
    // #1281: template-driven Avery-style sheet. Falls back to sane defaults if
    // no template resolved (should not happen - a default is always seeded).
    $cols      = $template->columns ?? 3;
    $lw        = $template->label_width_mm ?? 63.50;
    $lh        = $template->label_height_mm ?? 33.90;
    $margin    = $template->margin_mm ?? 10.00;
    $gutter    = $template->gutter_mm ?? 2.50;
    $fontPt    = $template->font_size_pt ?? 9;
    $pageSize  = $template->page_size ?? 'A4';
@endphp

@section('content')
<style>
@@page { size: {{ $pageSize }}; margin: {{ $margin }}mm; }
@@media print {
    .no-print, nav, header, footer, .navbar, .breadcrumb { display: none !important; }
    body { background: white !important; }
}
.label-sheet {
    display: grid;
    grid-template-columns: repeat({{ $cols }}, {{ $lw }}mm);
    gap: {{ $gutter }}mm;
    justify-content: center;
}
.label-cell {
    width: {{ $lw }}mm;
    height: {{ $lh }}mm;
    box-sizing: border-box;
    border: 1px solid #ccc;
    padding: 2mm;
    overflow: hidden;
    font-size: {{ $fontPt }}pt;
    page-break-inside: avoid;
    display: flex;
    flex-direction: column;
    justify-content: center;
    text-align: center;
}
.label-cell .l-title { font-weight: bold; line-height: 1.1; }
.label-cell .l-repo { color: #555; font-size: {{ max(6, $fontPt - 2) }}pt; }
.barcode-img { max-height: 12mm; width: auto; max-width: 100%; }
.qr-img { max-height: 14mm; max-width: 14mm; }
</style>

<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3 no-print">
    <div>
      <h1 class="h4 mb-0">{{ __('Batch Print Labels') }} ({{ count($labels) }})</h1>
      @if($template)<div class="text-muted small">{{ __('Template') }}: {{ $template->name }} - {{ $cols }} &times; {{ $template->rows ?? '?' }} {{ __('on') }} {{ $pageSize }}</div>@endif
    </div>
    <div>
      <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
      <button type="button" class="btn btn-outline-secondary" onclick="history.back()"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</button>
    </div>
  </div>

  <div class="label-sheet">
    @foreach ($labels as $label)
      <div class="label-cell">
        @if ($showTitle)
          <div class="l-title mb-1">{{ e($label['title'] ?: $label['slug']) }}</div>
        @endif
        @if ($showRepo && !empty($label['repository']))
          <div class="l-repo mb-1">{{ e($label['repository']) }}</div>
        @endif
        @if ($showBarcode && !empty($label['barcodeData']))
          <div class="mb-1">
            <img class="barcode-img" src="https://barcodeapi.org/api/128/{{ rawurlencode($label['barcodeData']) }}" alt="{{ __('Barcode') }}">
            <div style="font-size: {{ max(5, $fontPt - 3) }}pt;">{{ e($label['barcodeData']) }}</div>
          </div>
        @endif
        @if ($showQr)
          <div><img class="qr-img" src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ rawurlencode($label['qrUrl']) }}" alt="{{ __('QR Code') }}"></div>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endsection
