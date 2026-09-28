{{-- BackupFailedMail HTML body --}}
<p>{{ __('Hi,') }}</p>

<p style="color:#b30000;"><strong>A Heratio backup run has FAILED.</strong>
No complete artefact set was produced. Please investigate as soon as possible.</p>

<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
  <tr>
    <td><strong>{{ __('Run ID:') }}</strong></td>
    <td>{{ $backup['id'] ?? '(unknown)' }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Requested components:') }}</strong></td>
    <td>{{ implode(', ', $backup['components'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Partial files written:') }}</strong></td>
    <td>{{ count($backup['partial_files'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Duration:') }}</strong></td>
    <td>{{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s</td>
  </tr>
  <tr>
    <td><strong>{{ __('Failed at:') }}</strong></td>
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
    <li>{{ $f['component'] ?? '?' }} - {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})</li>
  @endforeach
</ul>
@endif

<p>
  <a href="{{ url('/admin/backup') }}">{{ __('Open the backup dashboard') }}</a>,
  or check the Laravel queue logs / <code>storage/logs/laravel.log</code> for the
  full stack trace.
</p>

<p>{{ __('Thanks,') }}<br>{{ config('app.name', 'Heratio') }}</p>
