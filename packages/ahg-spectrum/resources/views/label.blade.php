@extends('theme::layouts.1col')

@section('title', __('Print Labels') . ': ' . ($resource->title ?? $resource->slug))

@section('content')

@php
use Illuminate\Support\Facades\DB;

$objectId = $resource->id;

function safeSpectrumQuery($table, $objectId, $column) {
    try {
        return DB::table($table)->where('information_object_id', $objectId)->value($column);
    } catch (\Exception $e) {
        return null;
    }
}

$sector = 'archive';
$sectorConfig = null;
try {
    $sectorConfig = DB::table('display_object_config')
        ->where('object_id', $objectId)
        ->value('object_type');
} catch (\Exception $e) {}
if ($sectorConfig) $sector = $sectorConfig;

$barcodeSources = [];

if (!empty($resource->identifier)) {
    $barcodeSources['identifier'] = [
        'label' => __('Identifier'),
        'value' => $resource->identifier,
    ];
}

$isbn = safeSpectrumQuery('library_item', $objectId, 'isbn');
if (!empty($isbn)) {
    $barcodeSources['isbn'] = [
        'label' => __('ISBN'),
        'value' => $isbn,
    ];
    $sector = 'library';
}

$issn = safeSpectrumQuery('library_item', $objectId, 'issn');
if (!empty($issn)) {
    $barcodeSources['issn'] = [
        'label' => __('ISSN'),
        'value' => $issn,
    ];
}

$lccn = safeSpectrumQuery('library_item', $objectId, 'lccn');
if (!empty($lccn)) {
    $barcodeSources['lccn'] = [
        'label' => __('LCCN'),
        'value' => $lccn,
    ];
}

$openlibrary = safeSpectrumQuery('library_item', $objectId, 'openlibrary_id');
if (!empty($openlibrary)) {
    $barcodeSources['openlibrary'] = [
        'label' => __('OpenLibrary ID'),
        'value' => $openlibrary,
    ];
}

$barcode = safeSpectrumQuery('library_item', $objectId, 'barcode');
if (!empty($barcode)) {
    $barcodeSources['barcode'] = [
        'label' => __('Barcode'),
        'value' => $barcode,
    ];
}

$callNumber = safeSpectrumQuery('library_item', $objectId, 'call_number');
if (!empty($callNumber)) {
    $barcodeSources['call_number'] = [
        'label' => __('Call Number'),
        'value' => $callNumber,
    ];
}

$accession = safeSpectrumQuery('museum_object', $objectId, 'accession_number');
if (!empty($accession)) {
    $barcodeSources['accession'] = [
        'label' => __('Accession Number'),
        'value' => $accession,
    ];
    $sector = 'museum';
}

$objectNumber = safeSpectrumQuery('museum_object', $objectId, 'object_number');
if (!empty($objectNumber)) {
    $barcodeSources['object_number'] = [
        'label' => __('Object Number'),
        'value' => $objectNumber,
    ];
}

$barcodeSources['title'] = [
    'label' => __('Title'),
    'value' => $resource->title ?? '',
];

$defaultBarcodeData = '';
$preferredOrder = ['isbn', 'issn', 'barcode', 'accession', 'identifier', 'title'];
foreach ($preferredOrder as $key) {
    if (!empty($barcodeSources[$key]['value'])) {
        $defaultBarcodeData = $barcodeSources[$key]['value'];
        break;
    }
}

$qrUrl = url('/' . $resource->slug);

$sectorLabels = [
    'library' => __('Library Item'),
    'archive' => __('Archival Record'),
    'museum' => __('Museum Object'),
    'gallery' => __('Gallery Artwork'),
];
$sectorLabel = $sectorLabels[$sector] ?? __('Record');
@endphp

<h1 class="no-print">{{ __('Print Labels') }}: {{ $resource->title ?? $resource->slug }}</h1>

<style>
@media print {
    .no-print, #sidebar, #context-menu, nav, header, footer { display: none !important; }
    body { background: white !important; }
    .label-preview { box-shadow: none !important; border: 1px solid #ccc !important; }
}
.label-preview {
    background: white;
    border: 2px solid #333;
    padding: 15px;
    margin: 20px auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.barcode-img { max-height: 60px; }
.qr-img { max-width: 120px; max-height: 120px; }
</style>

<div class="row no-print">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-cog me-2"></i>{{ __('Label Configuration') }}
                <span class="badge bg-secondary ms-2">{{ $sectorLabel }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Barcode Source Dropdown -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-barcode me-1"></i>{{ __('Barcode Source') }}
                        </label>
                        <select class="form-select" id="barcodeSource" onchange="updateBarcodeSource()">
                            @foreach ($barcodeSources as $key => $source)
                                @if (!empty($source['value']))
                                <option value="{{ $source['value'] }}"
                                        {{ ($source['value'] === $defaultBarcodeData) ? 'selected' : '' }}>
                                    {{ $source['label'] }}: {{ $source['value'] }}
                                </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <!-- Label Size -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">{{ __('Label Size') }}</label>
                        <select class="form-select" id="labelSize" onchange="updateLabelSize()">
                            <option value="200">{{ __('Small (50mm)') }}</option>
                            <option value="300" selected>{{ __('Medium (75mm)') }}</option>
                            <option value="400">{{ __('Large (100mm)') }}</option>
                        </select>
                    </div>

                    <!-- Show Options -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Show') }}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showBarcode" checked onchange="toggleBarcode()">
                            <label class="form-check-label" for="showBarcode">{{ __('Linear Barcode') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showQR" checked onchange="toggleQR()">
                            <label class="form-check-label" for="showQR">{{ __('QR Code') }}</label>
                        </div>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">{{ __('Include') }}</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showTitle" checked onchange="toggleTitle()">
                            <label class="form-check-label" for="showTitle">{{ __('Title') }}</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="showRepo" checked onchange="toggleRepo()">
                            <label class="form-check-label" for="showRepo">{{ __('Repository') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="mb-3">
            <a class="btn btn-outline-secondary" href="{{ url('/' . $resource->slug) }}">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>{{ __('Print Label') }}
            </button>
            <button type="button" class="btn btn-secondary" onclick="downloadLabel()">
                <i class="fas fa-download me-1"></i>{{ __('Download PNG') }}
            </button>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">{{ __('Preview') }}</div>
            <div class="card-body text-center">
                <div class="label-preview" id="labelContent" style="max-width: 300px;">
                    <div id="labelTitle" class="fw-bold mb-2" style="font-size: 11pt;">
                        {{ $resource->title ?? $resource->slug }}
                    </div>

                    <div id="labelRepo" class="small text-muted mb-2">
                        @if ($resource->repository ?? null)
                            {{ $resource->repository->getAuthorizedFormOfName(['cultureFallback' => true]) }}
                        @endif
                    </div>

                    <div id="barcodeSection" class="mb-2">
                        <img id="barcodeImg" class="barcode-img"
                             src="https://barcodeapi.org/api/128/{{ rawurlencode($defaultBarcodeData) }}"
                             alt="{{ __('Barcode') }}">
                        <div class="small mt-1" id="barcodeText">{{ $defaultBarcodeData }}</div>
                    </div>

                    <div id="qrSection">
                        <img id="qrImg" class="qr-img"
                             src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data={{ rawurlencode($qrUrl) }}"
                             alt="{{ __('QR Code') }}">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updateBarcodeSource() {
    var value = document.getElementById('barcodeSource').value;
    document.getElementById('barcodeImg').src = 'https://barcodeapi.org/api/128/' + encodeURIComponent(value);
    document.getElementById('barcodeText').textContent = value;
}

function updateLabelSize() {
    document.getElementById('labelContent').style.maxWidth = document.getElementById('labelSize').value + 'px';
}

function toggleBarcode() {
    document.getElementById('barcodeSection').style.display = document.getElementById('showBarcode').checked ? 'block' : 'none';
}

function toggleQR() {
    document.getElementById('qrSection').style.display = document.getElementById('showQR').checked ? 'block' : 'none';
}

function toggleTitle() {
    document.getElementById('labelTitle').style.display = document.getElementById('showTitle').checked ? 'block' : 'none';
}

function toggleRepo() {
    document.getElementById('labelRepo').style.display = document.getElementById('showRepo').checked ? 'block' : 'none';
}

function downloadLabel() {
    if (typeof html2canvas !== 'undefined') {
        html2canvas(document.getElementById('labelContent')).then(function(canvas) {
            var link = document.createElement('a');
            link.download = 'label-{{ $resource->slug }}.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    } else {
        alert('{{ __('Download requires html2canvas. Use Print instead.') }}');
    }
}
</script>

@endsection
