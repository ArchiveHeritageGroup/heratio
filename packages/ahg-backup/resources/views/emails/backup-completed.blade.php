{{-- BackupCompletedMail HTML body --}}
<p>Hi,</p>

<p>A Heratio backup run has completed
@if(($backup['status'] ?? 'success') === 'success_with_warnings')
<strong>with warnings</strong>
@else
<strong>successfully</strong>
@endif
.</p>

<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
  <tr>
    <td><strong>Run ID:</strong></td>
    <td>{{ $backup['id'] ?? '(unknown)' }}</td>
  </tr>
  <tr>
    <td><strong>Components:</strong></td>
    <td>{{ implode(', ', $backup['components'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>Files written:</strong></td>
    <td>{{ count($backup['files'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>Total size:</strong></td>
    <td>{{ $backup['size_human'] ?? ($backup['size_bytes'] ?? 0 . ' bytes') }}</td>
  </tr>
  <tr>
    <td><strong>Duration:</strong></td>
    <td>{{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s</td>
  </tr>
  <tr>
    <td><strong>Completed:</strong></td>
    <td>{{ $backup['completed_at'] ?? now()->toIso8601String() }}</td>
  </tr>
</table>

@if(!empty($backup['files']))
<h4>Files</h4>
<ul>
  @foreach($backup['files'] as $f)
    <li>{{ $f['component'] ?? '?' }} &mdash; {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})</li>
  @endforeach
</ul>
@endif

@if(!empty($backup['warnings']))
<h4>Warnings</h4>
<ul>
  @foreach($backup['warnings'] as $w)
    <li>{{ $w }}</li>
  @endforeach
</ul>
@endif

<p>
  <a href="{{ url('/admin/backup') }}">Open the backup dashboard</a> to review or download artefacts.
</p>

<p>Thanks,<br>{{ config('app.name', 'Heratio') }}</p>
