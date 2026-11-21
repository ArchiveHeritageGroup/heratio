{{-- Preview of a reviewed workbook before it is applied (heratio#1445). --}}
@extends('theme::layouts.1col')
@section('title', __('Translation review'))

@php
  $items = $preview['items'];
  $refused = array_filter($items, fn ($i) => $i['problem'] !== null);
  $corrected = array_filter($items, fn ($i) => $i['problem'] === null && $i['action'] === 'corrected');
  $accepted = array_filter($items, fn ($i) => $i['problem'] === null && $i['action'] === 'accepted');
@endphp

@section('content')
<h1 class="h3">{{ __('Check the imported review (:locale)', ['locale' => $locale]) }}</h1>
<p>{{ __(':c correction(s), :a accepted, :r refused, :u unknown id(s), :n unchanged.', ['c' => count($corrected), 'a' => count($accepted), 'r' => count($refused), 'u' => $preview['unknown'], 'n' => $preview['unchanged']]) }}</p>

@if($refused)
  <h2 class="h5 text-danger">{{ __('Refused - the placeholders do not match the English') }}</h2>
  <table class="table table-sm table-bordered">
    <thead><tr><th>{{ __('English') }}</th><th>{{ __('Text') }}</th><th>{{ __('Problem') }}</th></tr></thead>
    <tbody>
      @foreach($refused as $i)
        <tr><td>{{ $i['key'] }}</td><td>{{ $i['value'] }}</td><td>{{ $i['problem'] }}</td></tr>
      @endforeach
    </tbody>
  </table>
@endif

<h2 class="h5">{{ __('Corrections') }}</h2>
@if($corrected)
  <table class="table table-sm table-bordered">
    <thead><tr><th>{{ __('English') }}</th><th>{{ __('Machine text') }}</th><th>{{ __('Correction') }}</th></tr></thead>
    <tbody>
      @foreach($corrected as $i)
        <tr><td>{{ $i['key'] }}</td><td><del class="text-muted">{{ $i['machine'] }}</del></td><td><ins>{{ $i['value'] }}</ins></td></tr>
      @endforeach
    </tbody>
  </table>
@else
  <p class="text-muted">{{ __('None.') }}</p>
@endif

@if($accepted)
  <details class="mb-3">
    <summary>{{ __(':n string(s) accepted as machine translated', ['n' => count($accepted)]) }}</summary>
    <table class="table table-sm table-bordered mt-2">
      <thead><tr><th>{{ __('English') }}</th><th>{{ __('Machine text') }}</th></tr></thead>
      <tbody>
        @foreach(array_slice($accepted, 0, 500) as $i)
          <tr><td>{{ $i['key'] }}</td><td>{{ $i['value'] }}@if($i['held']) <span class="badge bg-warning text-dark">{{ __('held') }}</span>@endif</td></tr>
        @endforeach
      </tbody>
    </table>
  </details>
@endif

<form method="POST" action="{{ route('translation-review.apply') }}" class="card card-body">
  @csrf
  <input type="hidden" name="locale" value="{{ $locale }}">
  <input type="hidden" name="token" value="{{ $token }}">
  <input type="hidden" name="accept_blank" value="{{ $acceptBlank ? 1 : 0 }}">
  <label class="form-label" for="tr-apply-reviewer">{{ __('Reviewer') }}</label>
  <input class="form-control mb-2" id="tr-apply-reviewer" name="reviewer" required maxlength="255" value="{{ $reviewer }}">
  <div>
    <button class="btn btn-primary" @disabled(count($corrected) + count($accepted) === 0)>{{ __('Apply reviewed strings') }}</button>
    <a class="btn btn-link" href="{{ route('translation-review.index', ['locale' => $locale]) }}">{{ __('Cancel') }}</a>
  </div>
</form>
@endsection
