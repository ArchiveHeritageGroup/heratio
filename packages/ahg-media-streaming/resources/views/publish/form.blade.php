{{-- Publish to platforms (heratio#1761, GCIS 6.8.2). Staff only (acl:update). --}}
@extends('theme::layouts.1col')
@section('title', __('Publish to platforms'))

@section('content')
<h1 class="h3"><i class="fas fa-share-nodes me-2"></i>{{ __('Publish to platforms') }}</h1>
<p><a href="{{ $io->slug ? url('/'.$io->slug) : '#' }}">{{ $io->title ?: __('Untitled') }}</a></p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

@include('ahg-media-streaming::publish._roadmap-note')

@if($refusals)
  <div class="alert alert-warning">
    <strong>{{ __('This record cannot go to a platform. Platforms are public, so only what the public may see can be published.') }}</strong>
    <ul class="mb-0">@foreach($refusals as $r)<li>{{ $r }}</li>@endforeach</ul>
  </div>
@else
  <p class="small text-muted">
    {{ __('Made from the access copy, never the master, so any watermark carries over.') }}
    {{ __('Source') }}: {{ $source['type'] }}@if($duration), {{ gmdate($duration >= 3600 ? 'H:i:s' : 'i:s', (int) round($duration)) }}@endif.
    {{ __('Each copy is fitted to the platform\'s shape on a blurred background; nothing is cropped. A small access copy is scaled up, so raise the playback size in the media settings for sharper platform copies.') }}
  </p>
  <form method="post" action="{{ route('media-streaming.publish.store', $io->id) }}">@csrf
    <div class="table-responsive">
    <table class="table table-sm align-middle small">
      <thead><tr><th></th><th>{{ __('Platform') }}</th><th>{{ __('Shape') }}</th><th>{{ __('Size') }}</th><th>{{ __('Longest') }}</th><th>{{ __('Largest file') }}</th><th>{{ __('Caption') }}</th></tr></thead>
      <tbody>
      @foreach($presets as $code => $p)
        @php($tooLong = $duration && $p['max_duration'] && $duration > $p['max_duration'])
        <tr>
          <td><input class="form-check-input" type="checkbox" name="platforms[]" value="{{ $code }}" id="pf-{{ $code }}"></td>
          <td><label for="pf-{{ $code }}">{{ $p['label'] }}</label>@if($tooLong) <span class="badge bg-warning text-dark">{{ __('longer than allowed') }}</span>@endif</td>
          <td>{{ $p['aspect'] }}</td>
          <td>{{ $p['width'] }}x{{ $p['height'] }}</td>
          <td>{{ $p['max_duration'] ? gmdate($p['max_duration'] >= 3600 ? 'H:i:s' : 'i:s', $p['max_duration']) : '-' }}</td>
          <td>{{ $p['max_bytes'] ? ($p['max_bytes'] >= 1e9 ? round($p['max_bytes'] / 1073741824).' GB' : round($p['max_bytes'] / 1e6).' MB') : '-' }}</td>
          <td>{{ number_format($p['caption_max']) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
    </div>
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" name="trim" value="1" id="pf-trim">
      <label class="form-check-label" for="pf-trim">{{ __('Trim to the platform\'s longest allowed length when the clip is longer (otherwise that platform is refused)') }}</label>
    </div>
    <button class="btn atom-btn-outline-success">{{ __('Make packages') }}</button>
    <span class="small text-muted ms-2">{{ __('Each package is a zip with the media, caption.txt and a thumbnail. Long clips are made in the background.') }}</span>
  </form>
@endif

<h2 class="h5 mt-4">{{ __('Distribution log') }}</h2>
@include('ahg-media-streaming::publish._log-table', ['log' => $log, 'statusLabels' => $statusLabels, 'presets' => $presets])
<p class="small"><a href="{{ route('media-streaming.publish.index') }}">{{ __('All publish packages') }}</a></p>
@endsection
