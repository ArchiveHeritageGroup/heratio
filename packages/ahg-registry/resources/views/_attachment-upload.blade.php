{{--
  Drag-and-drop file upload zone.
  Vars: $fieldName (default 'attachment'), $maxSize (MB, default 10),
        $allowedFileTypes (comma list, default 'PDF, DOC, …').

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $name = $fieldName ?? 'attachment';
    $maxMb = (int) ($maxSize ?? 10);
    $allowed = $allowedFileTypes ?? 'PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP';
    $nonce = csp_nonce() ?? '';
@endphp
<div class="attachment-upload-zone border rounded-3 p-4 text-center bg-light position-relative" id="upload-zone-{{ $name }}">
    <div class="upload-zone-content">
        <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
        <p class="mb-1 fw-semibold">{{ __('Click or drag files here') }}</p>
        <small class="text-muted d-block">{{ __('Allowed types: :types', ['types' => $allowed]) }}</small>
        <small class="text-muted d-block">{{ __('Max size: :mb MB', ['mb' => $maxMb]) }}</small>
    </div>
    <input type="file" class="position-absolute top-0 start-0 w-100 h-100 opacity-0"
           name="{{ $name }}" id="file-input-{{ $name }}" style="cursor: pointer;">
</div>

<script @if ($nonce) nonce="{{ $nonce }}" @endif>
(function () {
    var fieldName = {!! json_encode($name) !!};
    var zone = document.getElementById('upload-zone-' + fieldName);
    var input = document.getElementById('file-input-' + fieldName);
    if (!zone || !input) return;

    function setFiles(files) {
        var content = zone.querySelector('.upload-zone-content');
        if (!content) return;
        var names = [];
        for (var i = 0; i < files.length; i++) names.push(files[i].name);
        content.innerHTML = '<i class="fas fa-file fa-2x text-success mb-2"></i>'
            + '<p class="mb-0 fw-semibold">' + names.join(', ') + '</p>';
    }

    zone.addEventListener('dragover', function (e) {
        e.preventDefault(); e.stopPropagation();
        zone.classList.remove('bg-light');
        zone.classList.add('border-primary', 'bg-white');
    });
    zone.addEventListener('dragleave', function (e) {
        e.preventDefault(); e.stopPropagation();
        zone.classList.remove('border-primary', 'bg-white');
        zone.classList.add('bg-light');
    });
    zone.addEventListener('drop', function (e) {
        e.preventDefault(); e.stopPropagation();
        zone.classList.remove('border-primary', 'bg-white');
        zone.classList.add('bg-light');
        if (e.dataTransfer && e.dataTransfer.files.length > 0) {
            input.files = e.dataTransfer.files;
            setFiles(e.dataTransfer.files);
        }
    });
    input.addEventListener('change', function () {
        if (input.files && input.files.length > 0) setFiles(input.files);
    });
})();
</script>
