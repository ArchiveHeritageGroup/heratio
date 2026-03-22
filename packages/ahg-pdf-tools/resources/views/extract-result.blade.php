@extends('theme::layouts.1col')

@section('title', 'Extracted Text')

@section('content')

<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
    <h5 class="mb-0">
      <i class="bi bi-file-earmark-text me-2"></i>Extracted Text
    </h5>
    <a href="{{ route('pdf-tools.index') }}" class="btn atom-btn-white btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>
  <div class="card-body">

    {{-- Source Info --}}
    <div class="mb-3">
      <strong>Source:</strong> {{ e($filename) }}
      <span class="badge bg-info ms-2">{{ number_format(strlen($extractedText)) }} characters</span>
      <span class="badge bg-secondary">{{ number_format(str_word_count($extractedText)) }} words</span>
    </div>

    {{-- Copy Button --}}
    <div class="mb-3">
      <button type="button" class="btn atom-btn-white btn-sm" id="copyBtn">
        <i class="bi bi-clipboard me-1"></i>Copy to Clipboard
      </button>
    </div>

    {{-- Extracted Text --}}
    <div class="card">
      <div class="card-body bg-light" style="max-height: 600px; overflow-y: auto;">
        <pre class="mb-0" style="white-space: pre-wrap; word-wrap: break-word; font-size: 0.85rem;" id="extractedText">{{ e($extractedText) }}</pre>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var copyBtn = document.getElementById('copyBtn');
  var textEl = document.getElementById('extractedText');

  if (copyBtn && textEl) {
    copyBtn.addEventListener('click', function() {
      navigator.clipboard.writeText(textEl.textContent).then(function() {
        copyBtn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(function() {
          copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy to Clipboard';
        }, 2000);
      }).catch(function() {
        // Fallback for older browsers
        var range = document.createRange();
        range.selectNode(textEl);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        document.execCommand('copy');
        window.getSelection().removeAllRanges();
        copyBtn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        setTimeout(function() {
          copyBtn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy to Clipboard';
        }, 2000);
      });
    });
  }
});
</script>

@endsection
