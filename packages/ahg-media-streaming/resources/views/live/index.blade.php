{{-- Every live event with its status (heratio#1743). Staff only (acl:update). --}}
@extends('theme::layouts.1col')
@section('title', __('Live events'))

@section('content')
<h1 class="h3"><i class="fas fa-tower-broadcast me-2"></i>{{ __('Live events') }}</h1>
<form method="get" class="d-flex gap-2 mb-3 align-items-center">
  <label for="lv-status" class="small">{{ __('Status') }}</label>
  <select name="status" id="lv-status" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
    <option value="">{{ __('All') }}</option>
    @foreach($statusLabels as $code => $label)<option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>@endforeach
  </select>
</form>
<div class="table-responsive">
<table class="table table-sm align-middle small">
  <thead><tr><th>{{ __('Event') }}</th><th>{{ __('Record') }}</th><th>{{ __('Starts at') }}</th><th>{{ __('Status') }}</th><th>{{ __('Went live') }}</th><th>{{ __('Ended') }}</th><th>{{ __('Recording') }}</th></tr></thead>
  <tbody>
  @forelse($events as $e)
    <tr>
      <td><a href="{{ route('media-streaming.live.show', $e->id) }}">{{ $e->title }}</a></td>
      <td>@if($e->slug)<a href="{{ url('/'.$e->slug) }}">{{ $e->record_title ?: __('Untitled') }}</a>@else{{ $e->record_title ?: '#'.$e->information_object_id }}@endif</td>
      <td>{{ \Carbon\Carbon::parse($e->scheduled_at)->isoFormat('lll') }}</td>
      <td><span class="badge {{ $e->status === 'live' ? 'bg-danger' : 'bg-secondary' }}">{{ $statusLabels[$e->status] ?? $e->status }}</span></td>
      <td>{{ $e->started_at ? \Carbon\Carbon::parse($e->started_at)->isoFormat('lll') : '-' }}</td>
      <td>{{ $e->ended_at ? \Carbon\Carbon::parse($e->ended_at)->isoFormat('lll') : '-' }}</td>
      <td>{{ $e->recording_digital_object_id ? '#'.$e->recording_digital_object_id : '-' }}</td>
    </tr>
  @empty
    <tr><td colspan="7" class="text-muted">{{ __('No live events yet. Schedule one from a record\'s sidebar.') }}</td></tr>
  @endforelse
  </tbody>
</table>
</div>
@endsection
