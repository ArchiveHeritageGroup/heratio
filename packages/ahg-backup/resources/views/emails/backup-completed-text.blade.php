{{-- BackupCompletedMail plain-text body --}}
Hi,

A Heratio backup run has completed @if(($backup['status'] ?? 'success') === 'success_with_warnings')with warnings@else successfully@endif.

Run ID:       {{ $backup['id'] ?? '(unknown)' }}
Components:   {{ implode(', ', $backup['components'] ?? []) }}
Files:        {{ count($backup['files'] ?? []) }}
Total size:   {{ $backup['size_human'] ?? (($backup['size_bytes'] ?? 0) . ' bytes') }}
Duration:     {{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s
Completed:    {{ $backup['completed_at'] ?? now()->toIso8601String() }}

@if(!empty($backup['files']))
Files written:
@foreach($backup['files'] as $f)
  - {{ $f['component'] ?? '?' }} -- {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})
@endforeach
@endif

@if(!empty($backup['warnings']))
Warnings:
@foreach($backup['warnings'] as $w)
  - {{ $w }}
@endforeach
@endif

Open the backup dashboard: {{ url('/admin/backup') }}

Thanks,
{{ config('app.name', 'Heratio') }}
