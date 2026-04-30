@extends('ahg-theme-b5::layout')

@section('title', 'Print Labels: ' . e($title ?: $slug))

@section('content')
<style>
@@media print {
    .no-print, #sidebar, #context-menu, nav, header, footer, .navbar, .breadcrumb { display: none !important; }
    body { background: white !important; }
    .label-preview { width: fit-content; min-width: 200px; box-shadow: none !important; border: 1px solid #ccc !important; }
}
.label-preview {
    width: fit-content;
    min-width: 200px;
    background: white;
    border: 2px solid #333;
    padding: 15px;
    margin: 20px auto;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.barcode-img { max-height: 60px; width: auto; max-width: 100%; }
.qr-img { max-width: 120px; max-height: 120px; }
</style>

<h1 class="no-print">Print Labels: {{ e($title ?: $slug) }}</h1>

<div class="row">
    {{-- Configuration panel --}}
    <div class="col-md-8 no-print">
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-cog me-2"></i>Label Configuration
                <span class="badge bg-secondary ms-2">{{ $sectorLabel }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    {{-- Barcode Source Dropdown --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-barcode me-1"></i>Barcode Source
                        </label>
                        <select class="form-select" id="barcodeSource" onchange="updateBarcodeSource()">
                            @foreach ($barcodeSources as $key => $source)
                                @if (!empty($source['value']))
                                    <option value="{{ e($source['value']) }}"
                                            {{ $source['value'] === $defaultBarcodeData ? 'selected' : '' }}>
                                        {{ $source['label'] }}: {{ e($source['value']) }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    {{-- Label Size --}}
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">{{ __('Label Size') }}</label>
                        <select class="form-select" id="labelSize" onchange="updateLabelSize()">
                            <option value="200">{{ __('Small (50mm)') }}</option>
                            <option value="300" selected>{{ __('Medium (75mm)') }}</option>
                            <option value="400">{{ __('Large (100mm)') }}</option>
                        </select>
                    </div>

                    {{-- Show Options --}}
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

        {{-- Action Buttons --}}
        <div class="mb-3">
            <a class="btn btn-outline-secondary" href="/{{ e($slug) }}">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to record') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>{{ __('Print Label') }}
            </button>
            <button type="button" class="btn btn-secondary" onclick="downloadLabel()">
                <i class="fas fa-download me-1"></i>{{ __('Download PNG') }}
            </button>
        </div>
    </div>

    {{-- Preview panel --}}
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Preview</div>
            <div class="card-body text-center">
                <div class="label-preview" id="labelContent" style="max-width: 300px;">
                    <div id="labelTitle" class="fw-bold mb-2" style="font-size: 11pt;">
                        {{ e($title ?: $slug) }}
                    </div>

                    <div id="labelRepo" class="small text-muted mb-2">
                        @if (!empty($repositoryName))
                            {{ e($repositoryName) }}
                        @endif
                    </div>

                    <div id="barcodeSection" class="mb-2">
                        @if (!empty($defaultBarcodeData))
                            <img id="barcodeImg" class="barcode-img"
                                 src="https://barcodeapi.org/api/128/{{ rawurlencode($defaultBarcodeData) }}"
                                 alt="{{ __('Barcode') }}">
                            <div class="small mt-1" id="barcodeText">{{ e($defaultBarcodeData) }}</div>
                        @else
                            <div class="text-muted small" id="barcodeText">No barcode data available</div>
                        @endif
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
    var barcodeImg = document.getElementById('barcodeImg');
    if (barcodeImg) {
        barcodeImg.src = 'https://barcodeapi.org/api/128/' + encodeURIComponent(value);
    }
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
            link.download = 'label-{{ e($slug) }}.png';
            link.href = canvas.toDataURL();
            link.click();
        });
    } else {
        alert('Download requires html2canvas library. Use Print instead.');
    }
}
</script>
@endsection
