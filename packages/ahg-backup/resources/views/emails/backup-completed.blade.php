{{-- BackupCompletedMail HTML body --}}
<p>{{ __('Hi,') }}</p>

<p>A Heratio backup run has completed
@if(($backup['status'] ?? 'success') === 'success_with_warnings')
<strong>with warnings</strong>
@else
<strong>successfully</strong>
@endif
.</p>

<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;">
  <tr>
    <td><strong>{{ __('Run ID:') }}</strong></td>
    <td>{{ $backup['id'] ?? '(unknown)' }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Components:') }}</strong></td>
    <td>{{ implode(', ', $backup['components'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Files written:') }}</strong></td>
    <td>{{ count($backup['files'] ?? []) }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Total size:') }}</strong></td>
    <td>{{ $backup['size_human'] ?? ($backup['size_bytes'] ?? 0 . ' bytes') }}</td>
  </tr>
  <tr>
    <td><strong>{{ __('Duration:') }}</strong></td>
    <td>{{ number_format(($backup['duration_ms'] ?? 0) / 1000, 2) }} s</td>
  </tr>
  <tr>
    <td><strong>{{ __('Completed:') }}</strong></td>
    <td>{{ $backup['completed_at'] ?? now()->toIso8601String() }}</td>
  </tr>
</table>

@if(!empty($backup['files']))
<h4>{{ __('Files') }}</h4>
<ul>
  @foreach($backup['files'] as $f)
    <li>{{ $f['component'] ?? '?' }} - {{ $f['filename'] ?? '?' }} ({{ $f['size'] ?? '?' }})</li>
  @endforeach
</ul>
@endif

@if(!empty($backup['warnings']))
<h4>{{ __('Warnings') }}</h4>
<ul>
  @foreach($backup['warnings'] as $w)
    <li>{{ $w }}</li>
  @endforeach
</ul>
@endif

<p>
  <a href="{{ url('/admin/backup') }}">{{ __('Open the backup dashboard') }}</a> to review or download artefacts.
</p>

<p>{{ __('Thanks,') }}<br>{{ config('app.name', 'Heratio') }}</p>
