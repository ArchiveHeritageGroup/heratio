{{--
  Cite these results - the citation page (heratio#1585).
  Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
  License: AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', __('Citation'))

@section('content')
<h1>{{ __('Cite these results') }}</h1>
<p class="lead">{{ $facts['title'] }}</p>

<div class="table-responsive mb-4">
  <table class="table table-bordered table-sm">
    <thead><tr><th scope="col" style="width:30%">{{ __('Recorded with the citation') }}</th><th scope="col">{{ __('Value') }}</th></tr></thead>
    <tbody>
      <tr><td>{{ __('Cited on') }}</td><td>{{ $facts['date'] }}</td></tr>
      <tr><td>{{ $cite->kind === 'chatbot' ? __('Records the answer rested on') : __('Results') }}</td>
          <td>{{ number_format((int) $cite->result_count) }}
            @if($cite->kind === 'browse' && $cite->viewer_scope === 'staff')<span class="text-muted">({{ __('counted for a signed-in user') }})</span>@endif</td></tr>
      <tr><td>{{ __('Heratio version') }}</td><td>{{ $cite->heratio_version }}</td></tr>
      <tr><td>{{ __('Search index generation') }}</td><td><code>{{ $cite->index_generation ?: __('not recorded') }}</code></td></tr>
      @if($cite->kind === 'chatbot')
        <tr><td>{{ __('Model') }}</td><td>{{ $cite->ai_model ?: __('not recorded') }}</td></tr>
        <tr><td>{{ __('Inference receipt') }}</td><td>
          @if($cite->ai_receipt_seq) #{{ $cite->ai_receipt_seq }} <code>{{ $cite->ai_receipt_hash }}</code>
          @else {{ __('No inference receipt was recorded for this answer.') }} @endif</td></tr>
      @endif
    </tbody>
  </table>
</div>

@if($cite->kind === 'browse')
  @php $changed = $countNow !== (int) $cite->result_count || $currentVersion !== $cite->heratio_version || ($currentGeneration && $cite->index_generation && $currentGeneration !== $cite->index_generation); @endphp
  <div class="alert {{ $changed ? 'alert-warning' : 'alert-info' }}">
    {{ __('Run now, the same search finds :n records for you.', ['n' => number_format($countNow)]) }}
    @if($changed) {{ __('The catalogue, its index or Heratio has changed since this was cited, so the results may differ.') }} @endif
    <a href="{{ $browseUrl }}" class="btn btn-sm atom-btn-outline-success ms-2"><i class="fas fa-search me-1" aria-hidden="true"></i>{{ __('Open these results') }}</a>
  </div>
@else
  <h2 class="h5">{{ __('Records cited') }}</h2>
  @if(count($records))
    <ul>
      @foreach($records as $r)
        <li>@if($r->slug)<a href="{{ url('/'.$r->slug) }}">{{ $r->title ?: $r->identifier }}</a>@else{{ $r->title ?: $r->identifier }}@endif
          @if($r->identifier)<span class="text-muted">({{ $r->identifier }})</span>@endif</li>
      @endforeach
    </ul>
  @endif
  @if(count($records) < (int) $cite->result_count)
    <p class="text-muted">{{ __('Some cited records are not available to you.') }}</p>
  @endif
@endif

<h2 class="h5 mt-4">{{ __('Citation') }}</h2>
@include('ahg-search::cite._formats', ['formats' => $formats, 'code' => $cite->code])
@endsection
