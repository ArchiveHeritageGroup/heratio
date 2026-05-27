{{-- BackupFailedMail HTML body --}}
<p>Hi,</p>

<p style="color:#b30000;"><strong>A Heratio backup run has FAILED.</strong>
No complete artefact set was produced. Please investigate as soon as possible.</p>

<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
  <tr>
    <td><strong>Run ID:</strong></td>
    <td>{{ $backup['id'] ?? '(unknown)' }}</td>
  </tr>
  <tr>
    <td><strong>Requested components:</strong></td>
    <td>{{ implode(', ', $backup['components'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>Partial files written:</strong></td>
    <td>{{ count($backup['partial_files'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>Duration:</strong></td>
    <td>{{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s</td>
  </tr>
  <tr>
    <td><strong>Failed at:</strong></td>
    <td>{{ $backup['completed_at'] ?? now()->toIso8601String() }}</td>
  </tr>
</table>

@if(!empty($backup['errors']))
<h4>{{ __('Errors') }}</h4>
<ul>
  @foreach($backup['errors'] as $err)
    <li><code>{{ \Illuminate\Support\Str::limit($err, 500) }}</code></li>
  @endforeach
</ul>
@endif

@if(!empty($backup['partial_files']))
<h4>{{ __('Partial artefacts on disk (may be incomplete)') }}</h4>
<ul>
  @foreach($backup['partial_files'] as $f)
    <li>{{ $f['component'] ?? '?' }} &mdash; {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})</li>
  @endforeach
</ul>
@endif

<p>
  <a href="{{ url('/admin/backup') }}">Open the backup dashboard</a>,
  or check the Laravel queue logs / <code>storage/logs/laravel.log</code> for the
  full stack trace.
</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
