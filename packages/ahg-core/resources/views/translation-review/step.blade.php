{{-- One string at a time: accept the machine text or correct it (heratio#1445). --}}
@extends('theme::layouts.1col')
@section('title', __('Translation review'))

@section('content')
<h1 class="h3">{{ __('Review :locale', ['locale' => $locale]) }}</h1>
<p><a href="{{ route('translation-review.index', ['locale' => $locale]) }}">{{ __('Back to translation review') }}</a></p>
@include('ahg-core::translation-review._flash')

@if(! $item)
  <div class="alert alert-info">{{ __('Nothing is waiting for review in this language.') }}</div>
@else
  <p class="text-muted">{{ __('String :n of :total', ['n' => $pos + 1, 'total' => $total]) }}
    @if($item['held']) <span class="badge bg-warning text-dark">{{ __('held - not live until reviewed') }}</span>@endif</p>
  <div class="card mb-3">
    <div class="card-body">
      <div class="small text-muted">{{ __('English') }}</div>
      <p class="fs-5">{{ $item['key'] }}</p>
      <div class="small text-muted">{{ __('Machine text') }}</div>
      <p class="fs-5" lang="{{ $locale }}">{{ $item['machine'] }}</p>
      @if($keep !== '')<p class="small mb-1">{{ __('Keep unchanged:') }} <code>{{ $keep }}</code></p>@endif
      @if($context !== '')<p class="small text-muted mb-0">{{ __('Used in:') }} {{ $context }}</p>@endif
    </div>
  </div>
  <form method="POST" action="{{ route('translation-review.decide') }}">
    @csrf
    <input type="hidden" name="locale" value="{{ $locale }}">
    <input type="hidden" name="key" value="{{ $item['key'] }}">
    <input type="hidden" name="pos" value="{{ $pos }}">
    @if($held)<input type="hidden" name="held" value="1">@endif
    <label class="form-label" for="tr-correction">{{ __('Correction (leave empty to accept the machine text)') }}</label>
    <textarea class="form-control mb-2" id="tr-correction" name="correction" rows="3" lang="{{ $locale }}">{{ old('correction') }}</textarea>
    <button class="btn btn-success" name="decision" value="accept">{{ __('Accept') }}</button>
    <button class="btn btn-primary" name="decision" value="correct">{{ __('Save correction') }}</button>
    @if($pos + 1 < $total)
      <a class="btn btn-link" href="{{ route('translation-review.step', ['locale' => $locale, 'pos' => $pos + 1, 'held' => $held ? 1 : null]) }}">{{ __('Skip') }}</a>
    @endif
    @if($pos > 0)
      <a class="btn btn-link" href="{{ route('translation-review.step', ['locale' => $locale, 'pos' => $pos - 1, 'held' => $held ? 1 : null]) }}">{{ __('Previous') }}</a>
    @endif
  </form>
@endif
@endsection
