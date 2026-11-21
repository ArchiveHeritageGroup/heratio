{{-- Record sidebar: this record's live events and "Schedule a live event" (heratio#1743). Staff with update permission only. --}}
@php
  $lvpUser = auth()->user();
  $lvpShow = $lvpUser && \Illuminate\Support\Facades\Route::has('media-streaming.live.create')
    && \Illuminate\Support\Facades\Schema::hasTable('live_event')
    && \AhgCore\Services\AclService::hasPermission($lvpUser->id, 'update', (int) $ioId);
  $lvpSvc = $lvpShow ? app(\AhgMediaStreaming\Services\LiveStreamService::class) : null;
  $lvpEvents = $lvpSvc ? $lvpSvc->list((int) $ioId, null, 5) : collect();
  $lvpLabels = $lvpSvc ? $lvpSvc->statusLabels() : [];
@endphp
@if($lvpShow)
<div class="card mb-3">
  <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-tower-broadcast me-1"></i>{{ __('Live events') }}</div>
  <div class="card-body small">
    <a class="btn btn-sm atom-btn-white w-100 mb-2" href="{{ route('media-streaming.live.create', $ioId) }}">{{ __('Schedule a live event') }}</a>
    @foreach($lvpEvents as $e)
      <div><a href="{{ route('media-streaming.live.show', $e->id) }}">{{ $e->title }}</a> - {{ $lvpLabels[$e->status] ?? $e->status }}</div>
    @endforeach
    <div class="mt-2"><a href="{{ route('media-streaming.live.index') }}">{{ __('All live events') }}</a></div>
  </div>
</div>
@endif
