@extends('ahg-theme-b5::layout')

@section('title', 'Batch Print Labels')

@section('content')
<style>
@@media print {
    .no-print, nav, header, footer, .navbar, .breadcrumb { display: none !important; }
    body { background: white !important; }
    .label-preview { page-break-inside: avoid; box-shadow: none !important; border: 1px solid #ccc !important; }
}
.label-preview {
    display: inline-block;
    background: white;
    border: 2px solid #333;
    padding: 15px;
    margin: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    vertical-align: top;
}
.barcode-img { max-height: 60px; width: auto; max-width: 100%; }
.qr-img { max-width: 120px; max-height: 120px; }
</style>

<h1 class="no-print">Batch Print Labels ({{ count($labels) }} items)</h1>

<div class="mb-3 no-print">
    <button type="button" class="btn btn-primary" onclick="window.print()">
        <i class="fas fa-print me-1"></i>Print All Labels
    </button>
    <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
        <i class="fas fa-arrow-left me-1"></i>Back
    </button>
</div>

<div class="d-flex flex-wrap justify-content-center">
    @foreach ($labels as $label)
        <div class="label-preview" style="max-width: {{ $labelSize }}px;">
            @if ($showTitle)
                <div class="fw-bold mb-2" style="font-size: 11pt;">
                    {{ e($label['title'] ?: $label['slug']) }}
                </div>
            @endif

            @if ($showRepo && !empty($label['repository']))
                <div class="small text-muted mb-2">
                    {{ e($label['repository']) }}
                </div>
            @endif

            @if ($showBarcode && !empty($label['barcodeData']))
                <div class="mb-2">
                    <img class="barcode-img"
                         src="https://barcodeapi.org/api/128/{{ rawurlencode($label['barcodeData']) }}"
                         alt="Barcode">
                    <div class="small mt-1">{{ e($label['barcodeData']) }}</div>
                </div>
            @endif

            @if ($showQr)
                <div>
                    <img class="qr-img"
                         src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ rawurlencode($label['qrUrl']) }}"
                         alt="QR Code">
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
