{{-- Distribution log rows (heratio#1761). $log, $statusLabels, $presets; $showRecord adds the record column. --}}
@php($showRecord = $showRecord ?? false)
@if(count($log) === 0)
  <p class="text-muted small">{{ __('No publish packages yet.') }}</p>
@else
<div class="table-responsive">
<table class="table table-sm align-middle small">
  <thead><tr>
    @if($showRecord)<th>{{ __('Record') }}</th>@endif
    <th>{{ __('Platform') }}</th><th>{{ __('Status') }}</th><th>{{ __('Made by') }}</th><th>{{ __('Made') }}</th><th>{{ __('Size') }}</th><th>{{ __('Posted') }}</th><th></th>
  </tr></thead>
  <tbody>
  @foreach($log as $p)
    <tr>
      @if($showRecord)<td>@if($p->slug)<a href="{{ url('/'.$p->slug) }}">{{ $p->title ?: $p->slug }}</a>@else #{{ $p->information_object_id }}@endif</td>@endif
      <td>{{ $presets[$p->platform]['label'] ?? $p->platform }}@if($p->trimmed) <span class="badge bg-secondary">{{ __('trimmed') }}</span>@endif</td>
      <td>{{ $statusLabels[$p->status] ?? $p->status }}@if($p->error)<div class="text-danger">{{ $p->error }}</div>@endif</td>
      <td>{{ $p->made_by_name ?? ($p->made_by ? '#'.$p->made_by : '') }}</td>
      <td class="text-nowrap">{{ $p->made_at ?? $p->created_at }}</td>
      <td class="text-nowrap">@if($p->byte_size){{ number_format($p->byte_size / 1048576, 1) }} MB @endif @if($p->width){{ $p->width }}x{{ $p->height }}@endif</td>
      <td>@if($p->posted_url)<a href="{{ $p->posted_url }}" rel="noopener noreferrer" target="_blank">{{ \Illuminate\Support\Str::limit($p->posted_url, 40) }}</a><div class="text-muted">{{ $p->posted_at }}@if($p->posted_via && $p->posted_via !== 'manual') ({{ $p->posted_via }})@endif</div>@endif</td>
      <td class="text-nowrap">
        @if(in_array($p->status, ['made', 'downloaded', 'posted'], true))
          <a class="btn btn-sm atom-btn-white" href="{{ route('media-streaming.publish.download', $p->id) }}">{{ __('Download') }}</a>
        @endif
        @if(in_array($p->status, ['made', 'downloaded'], true))
          <form method="post" action="{{ route('media-streaming.publish.posted', $p->id) }}" class="d-inline-flex gap-1 mt-1">@csrf
            <input type="url" name="posted_url" class="form-control form-control-sm" placeholder="{{ __('Posted URL') }}" required aria-label="{{ __('Posted URL') }}">
            <input type="date" name="posted_at" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required aria-label="{{ __('Date posted') }}">
            <button class="btn btn-sm atom-btn-outline-success">{{ __('Mark posted') }}</button>
          </form>
        @endif
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
</div>
@endif
