{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_attachmentList.php --}}
@php
    $fileIcons = [
        'image' => 'fas fa-file-image text-success',
        'document' => 'fas fa-file-alt text-primary',
        'pdf' => 'fas fa-file-pdf text-danger',
        'spreadsheet' => 'fas fa-file-excel text-success',
        'presentation' => 'fas fa-file-powerpoint text-warning',
        'archive' => 'fas fa-file-archive text-secondary',
        'video' => 'fas fa-file-video text-info',
        'audio' => 'fas fa-file-audio text-purple',
        'code' => 'fas fa-file-code text-dark',
    ];

    $fileIconFor = function ($attachment) use ($fileIcons) {
        $ext = strtolower(pathinfo($attachment->file_name ?? $attachment->filename ?? '', PATHINFO_EXTENSION));
        $mime = $attachment->mime_type ?? '';

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'tiff']) || stripos($mime, 'image/') === 0) {
            return $fileIcons['image'];
        }
        if (in_array($ext, ['pdf']) || stripos($mime, 'pdf') !== false) {
            return $fileIcons['pdf'];
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv', 'ods'])) {
            return $fileIcons['spreadsheet'];
        }
        if (in_array($ext, ['ppt', 'pptx', 'odp'])) {
            return $fileIcons['presentation'];
        }
        if (in_array($ext, ['zip', 'tar', 'gz', 'rar', '7z'])) {
            return $fileIcons['archive'];
        }
        if (in_array($ext, ['mp4', 'avi', 'mov', 'mkv', 'webm']) || stripos($mime, 'video/') === 0) {
            return $fileIcons['video'];
        }
        if (in_array($ext, ['mp3', 'wav', 'ogg', 'flac', 'aac']) || stripos($mime, 'audio/') === 0) {
            return $fileIcons['audio'];
        }
        if (in_array($ext, ['py', 'php', 'js', 'html', 'css', 'json', 'xml'])) {
            return $fileIcons['code'];
        }
        return $fileIcons['document'];
    };

    $formatSize = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return number_format($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return number_format($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    };
@endphp
@if (!empty($attachments))
<div class="list-group list-group-flush">
  @foreach ($attachments as $att)
    @php
      $icon = $fileIconFor($att);
      $fileName = $att->file_name ?? $att->filename ?? __('Unnamed file');
      $fileUrl = $att->file_url ?? $att->url ?? '#';
      $fileSize = $att->file_size ?? $att->size ?? 0;
      $downloads = (int) ($att->download_count ?? 0);
    @endphp
    <div class="list-group-item d-flex align-items-center py-2">
      <i class="{{ $icon }} me-3 fa-lg flex-shrink-0"></i>
      <div class="flex-grow-1 min-width-0">
        <a href="{{ $fileUrl }}" class="text-decoration-none small fw-semibold" target="_blank" rel="noopener">
          {{ $fileName }}
        </a>
        <br>
        <small class="text-muted">
          @if ($fileSize > 0)
            {{ $formatSize($fileSize) }}
          @endif
          @if ($downloads > 0)
            <span class="ms-2"><i class="fas fa-download me-1"></i>{{ number_format($downloads) }}</span>
          @endif
        </small>
      </div>
      <a href="{{ $fileUrl }}" class="btn btn-sm btn-outline-secondary flex-shrink-0" download title="{{ __('Download') }}">
        <i class="fas fa-download"></i>
      </a>
    </div>
  @endforeach
</div>
@else
<p class="text-muted small mb-0">{{ __('No attachments.') }}</p>
@endif
