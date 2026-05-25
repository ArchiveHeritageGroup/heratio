{{-- BackupFailedMail plain-text body --}}
Hi,

** A Heratio backup run has FAILED. **
No complete artefact set was produced. Please investigate as soon as possible.

Run ID:                  {{ $backup['id'] ?? '(unknown)' }}
Requested components:    {{ implode(', ', $backup['components'] ?? []) }}
Partial files written:   {{ count($backup['partial_files'] ?? []) }}
Duration:                {{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s
Failed at:               {{ $backup['completed_at'] ?? now()->toIso8601String() }}

@if(!empty($backup['errors']))
Errors:
@foreach($backup['errors'] as $err)
  - {{ \Illuminate\Support\Str::limit($err, 500) }}
@endforeach
@endif

@if(!empty($backup['partial_files']))
Partial artefacts on disk (may be incomplete):
@foreach($backup['partial_files'] as $f)
  - {{ $f['component'] ?? '?' }} -- {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})
@endforeach
@endif

Open the backup dashboard: {{ url('/admin/backup') }}
Or check storage/logs/laravel.log for the full stack trace.

Thanks,
{{ config('app.name', 'Heratio') }}
