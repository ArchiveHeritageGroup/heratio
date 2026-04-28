{{--
  Attachments list (icons + size + download).
  Vars: $attachments (iterable of objects/arrays with file_name|filename, file_url|url,
  mime_type, file_size|size, download_count).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $iconFor = function ($att): string {
        $att = (object) $att;
        $ext = strtolower(pathinfo($att->file_name ?? $att->filename ?? '', PATHINFO_EXTENSION));
        $mime = $att->mime_type ?? '';
        if (in_array($ext, ['jpg','jpeg','png','gif','svg','webp','bmp','tiff']) || str_starts_with($mime, 'image/'))
            return 'fas fa-file-image text-success';
        if ($ext === 'pdf' || stripos($mime, 'pdf') !== false)
            return 'fas fa-file-pdf text-danger';
        if (in_array($ext, ['xls','xlsx','csv','ods']))
            return 'fas fa-file-excel text-success';
        if (in_array($ext, ['ppt','pptx','odp']))
            return 'fas fa-file-powerpoint text-warning';
        if (in_array($ext, ['zip','tar','gz','rar','7z']))
            return 'fas fa-file-archive text-secondary';
        if (in_array($ext, ['mp4','avi','mov','mkv','webm']) || str_starts_with($mime, 'video/'))
            return 'fas fa-file-video text-info';
        if (in_array($ext, ['mp3','wav','ogg','flac','aac']) || str_starts_with($mime, 'audio/'))
            return 'fas fa-file-audio text-purple';
        if (in_array($ext, ['py','php','js','html','css','json','xml']))
            return 'fas fa-file-code text-dark';
        return 'fas fa-file-alt text-primary';
    };
    $formatSize = function ($bytes): string {
        $b = (int) $bytes;
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            $b >= 1024       => number_format($b / 1024, 1) . ' KB',
            default          => $b . ' B',
        };
    };
@endphp
@if (! empty($attachments))
    <div class="list-group list-group-flush">
        @foreach ($attachments as $att)
            @php
                $att = (object) $att;
                $name = $att->file_name ?? $att->filename ?? __('Unnamed file');
                $url = $att->file_url ?? $att->url ?? '#';
                $size = (int) ($att->file_size ?? $att->size ?? 0);
                $downloads = (int) ($att->download_count ?? 0);
            @endphp
            <div class="list-group-item d-flex align-items-center py-2">
                <i class="{{ $iconFor($att) }} me-3 fa-lg flex-shrink-0"></i>
                <div class="flex-grow-1 min-width-0">
                    <a href="{{ $url }}" class="text-decoration-none small fw-semibold" target="_blank" rel="noopener">{{ $name }}</a>
                    <br>
                    <small class="text-muted">
                        @if ($size > 0){{ $formatSize($size) }}@endif
                        @if ($downloads > 0)<span class="ms-2"><i class="fas fa-download me-1"></i>{{ number_format($downloads) }}</span>@endif
                    </small>
                </div>
                <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary flex-shrink-0" download title="{{ __('Download') }}">
                    <i class="fas fa-download"></i>
                </a>
            </div>
        @endforeach
    </div>
@else
    <p class="text-muted small mb-0">{{ __('No attachments.') }}</p>
@endif
