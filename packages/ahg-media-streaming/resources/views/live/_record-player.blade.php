{{-- Record page: the live event on this record (heratio#1743). While live, the
     site's media player plays the access-checked live playlist; before it,
     "Starts at ..."; after it, "Ended". Viewers must pass the record's media
     access rules; failed events show to staff only. --}}
@php
  $lvSvc = \Illuminate\Support\Facades\Route::has('media-streaming.live.hls') ? app(\AhgMediaStreaming\Services\LiveStreamService::class) : null;
  $lvEvent = $lvSvc ? $lvSvc->forRecord((int) $ioId) : null;
  $lvStaff = $lvEvent && $lvSvc->mayManage($lvEvent, auth()->id());
  $lvShow = $lvEvent && ($lvStaff || ($lvEvent->status !== 'failed' && $lvSvc->mayView($lvEvent)));
  $lvPlaylist = $lvShow ? route('media-streaming.live.hls', ['eventId' => $lvEvent->id, 'file' => 'index.m3u8'], false) : null;
  $lvPlayable = $lvShow && $lvEvent->status === 'live' && $lvSvc->hlsPath($lvEvent, 'index.m3u8');
@endphp
@if($lvShow)
<div class="card mb-3" id="live-event-{{ $lvEvent->id }}">
  <div class="card-header fw-bold d-flex align-items-center gap-2">
    <i class="fas fa-tower-broadcast"></i>{{ $lvEvent->title }}
    @if($lvEvent->status === 'live')<span class="badge bg-danger">{{ __('Live') }}</span>@endif
    @if($lvStaff)<a class="ms-auto small fw-normal" href="{{ route('media-streaming.live.show', $lvEvent->id) }}">{{ __('Manage') }}</a>@endif
  </div>
  <div class="card-body">
    @if($lvPlayable)
      {{-- The player id must not end in a number: the caption composer reads one as a digital object id. --}}
      @include('theme::components.media-player', [
        'type' => 'video', 'playerId' => 'ahg-live-'.$lvEvent->id.'-stream',
        'src' => $lvPlaylist, 'mime' => 'application/x-mpegURL', 'hlsSrc' => $lvPlaylist,
        'name' => $lvEvent->title, 'masterUrl' => $lvPlaylist, 'masterMime' => 'application/x-mpegURL',
        'byteSize' => 0, 'showDownload' => false, 'tracks' => [],
      ])
    @elseif($lvEvent->status === 'scheduled' || $lvEvent->status === 'live')
      <p class="mb-0">{{ __('Starts at :time', ['time' => \Carbon\Carbon::parse($lvEvent->scheduled_at)->isoFormat('LLL')]) }}</p>
    @elseif($lvEvent->status === 'ended')
      <p class="mb-0">{{ __('Ended') }}@if($lvEvent->ended_at) - {{ \Carbon\Carbon::parse($lvEvent->ended_at)->isoFormat('LLL') }}@endif
        @if($lvEvent->recording_digital_object_id). {{ __('The recording is with this record\'s media.') }}@endif</p>
    @else
      <p class="mb-0 text-muted">{{ __('This live event failed.') }} {{ $lvEvent->error }}</p>
    @endif
    @if($lvEvent->description)<p class="small text-muted mt-2 mb-0">{{ $lvEvent->description }}</p>@endif
  </div>
</div>
@if(in_array($lvEvent->status, ['scheduled', 'live'], true))
<script nonce="{{ csp_nonce() }}">
(function () {
  // Reload when the event goes live or ends, so the player appears and goes.
  var was = @json($lvEvent->status . ($lvPlayable ? ':playing' : ''));
  var url = @json(route('media-streaming.live.status', $lvEvent->id, false));
  setInterval(function () {
    fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (s) {
      if (!s) return;
      var now = s.status + (s.status === 'live' && s.playlist ? ':playing' : '');
      if (now !== was) window.location.reload();
    }).catch(function () {});
  }, 15000);
})();
</script>
@endif
@endif
