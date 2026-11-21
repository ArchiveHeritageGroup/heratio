{{-- A live event: encoder settings, start / stop, status (heratio#1743). Staff only (acl:update). --}}
@extends('theme::layouts.1col')
@section('title', $event->title)

@section('content')
<h1 class="h3"><i class="fas fa-tower-broadcast me-2"></i>{{ $event->title }}
  <span class="badge {{ $event->status === 'live' ? 'bg-danger' : 'bg-secondary' }} align-middle">{{ $statusLabels[$event->status] ?? $event->status }}</span></h1>
<p><a href="{{ $io->slug ? url('/'.$io->slug) : '#' }}">{{ $io->title ?: __('Untitled') }}</a> - <a href="{{ route('media-streaming.live.index') }}">{{ __('All live events') }}</a></p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
@if($event->error)<div class="alert alert-warning">{{ $event->error }}</div>@endif

<table class="table table-sm small" style="max-width:48rem">
  <tr><th style="width:12rem">{{ __('Starts at') }}</th><td>{{ \Carbon\Carbon::parse($event->scheduled_at)->isoFormat('LLL') }}</td></tr>
  @if($event->started_at)<tr><th>{{ __('Went live') }}</th><td>{{ \Carbon\Carbon::parse($event->started_at)->isoFormat('LLL') }}</td></tr>@endif
  @if($event->ended_at)<tr><th>{{ __('Ended') }}</th><td>{{ \Carbon\Carbon::parse($event->ended_at)->isoFormat('LLL') }}</td></tr>@endif
  @if($event->recording_digital_object_id)<tr><th>{{ __('Recording') }}</th><td>{{ __('Digital object #:id on the record', ['id' => $event->recording_digital_object_id]) }}</td></tr>@endif
  @if($event->description)<tr><th>{{ __('Description') }}</th><td>{{ $event->description }}</td></tr>@endif
</table>

@if(! in_array($event->status, ['ended'], true))
<div class="card mb-3" style="max-width:48rem">
  <div class="card-header fw-bold">{{ __('Encoder settings (OBS: Settings > Stream > Custom)') }}</div>
  <div class="card-body small">
    <div class="mb-2"><label class="form-label mb-0" for="lv-server">{{ __('Server') }}</label>
      <input class="form-control form-control-sm font-monospace" id="lv-server" readonly value="{{ $ingest['server'] }}"></div>
    <div class="mb-2"><label class="form-label mb-0" for="lv-key">{{ __('Stream key') }}</label>
      <input class="form-control form-control-sm font-monospace" id="lv-key" readonly value="{{ $ingest['key'] }}"></div>
    <div class="mb-2"><label class="form-label mb-0" for="lv-url">{{ __('Full ingest URL') }}</label>
      <input class="form-control form-control-sm font-monospace" id="lv-url" readonly value="{{ $ingest['url'] }}"></div>
    <div class="text-muted">{{ __('Send H.264 video and AAC audio with a keyframe every 2 seconds. The stream is passed through as it arrives, without re-encoding. Keep the stream key private: anyone with it can broadcast on this event.') }}</div>
  </div>
</div>
@endif

<div class="d-flex gap-2 align-items-center mb-3">
  @if(in_array($event->status, ['scheduled', 'failed'], true) && ! $workerActive && ! $leftover)
    <form method="post" action="{{ route('media-streaming.live.start', $event->id) }}">@csrf
      <button class="btn atom-btn-white" type="submit"><i class="fas fa-play me-1"></i>{{ __('Start ingest') }}</button></form>
  @endif
  @if($workerActive || $event->status === 'live' || $event->start_requested_at)
    <form method="post" action="{{ route('media-streaming.live.stop', $event->id) }}">@csrf
      <button class="btn btn-outline-danger" type="submit"><i class="fas fa-stop me-1"></i>{{ __('Stop') }}</button></form>
  @endif
  <span class="small text-muted">
    @if($workerActive && $event->status !== 'live'){{ __('Waiting for the encoder on port :port.', ['port' => $event->ingest_port]) }}
    @elseif($workerActive){{ __('Live on port :port.', ['port' => $event->ingest_port]) }}
    @elseif($event->start_requested_at){{ __('Waiting for the live queue worker to pick up the job.') }}
    @endif
  </span>
</div>
@if($leftover)
  <div class="alert alert-info small">{{ __('This event has a recording from an earlier run that is not yet on the record. Run ahg:live-ingest :id --archive first.', ['id' => $event->id]) }}</div>
@endif
@if($workerActive || $event->start_requested_at)
  <script nonce="{{ csp_nonce() }}">setTimeout(function () { window.location.reload(); }, 10000);</script>
@endif
@endsection
