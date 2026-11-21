{{-- Record sidebar: Publish to platforms + this record's distribution log (heratio#1761). Staff with update permission only. --}}
{{-- heratio#1743 live events ride on this include so the locked record page needs no second sidebar line. --}}
@include('ahg-media-streaming::live._record-panel', ['ioId' => $ioId])
@php
  $ppUser = auth()->user();
  $ppShow = $ppUser && \Illuminate\Support\Facades\Route::has('media-streaming.publish.form')
    && \Illuminate\Support\Facades\Schema::hasTable('media_publish_package')
    && \AhgCore\Services\AclService::hasPermission($ppUser->id, 'update', (int) $ioId);
  $ppSvc = $ppShow ? app(\AhgMediaStreaming\Services\PublishPackageService::class) : null;
  $ppSource = $ppSvc ? $ppSvc->source((int) $ioId) : null;
  $ppLog = $ppSvc ? $ppSvc->log((int) $ioId, 5) : collect();
@endphp
@if($ppShow && ($ppSource || count($ppLog)))
<div class="card mb-3">
  <div class="card-header fw-bold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-share-nodes me-1"></i>{{ __('Publish to platforms') }}</div>
  <div class="card-body small">
    @if($ppSource)
      <a class="btn btn-sm atom-btn-white w-100 mb-2" href="{{ route('media-streaming.publish.form', $ioId) }}">{{ __('Make platform packages') }}</a>
    @endif
    @php($ppLabels = $ppSvc->statusLabels())
    @php($ppPresets = $ppSvc->presets())
    @forelse($ppLog as $p)
      <div>{{ $ppPresets[$p->platform]['label'] ?? $p->platform }} - {{ $ppLabels[$p->status] ?? $p->status }}@if($p->posted_url) - <a href="{{ $p->posted_url }}" rel="noopener noreferrer" target="_blank">{{ __('post') }}</a>@endif</div>
    @empty
      <div class="text-muted">{{ __('No publish packages yet.') }}</div>
    @endforelse
  </div>
</div>
@endif
