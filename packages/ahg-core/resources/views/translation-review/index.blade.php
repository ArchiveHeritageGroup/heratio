{{-- Native-speaker review of machine-translated UI strings (heratio#1445). Translators and admins. --}}
@extends('theme::layouts.1col')
@section('title', __('Translation review'))

@section('content')
<h1 class="h3"><i class="fas fa-language me-2"></i>{{ __('Translation review') }}</h1>
<p class="text-muted">{{ __('Machine translations reach the live site only after a native speaker has accepted or corrected them. Review here one string at a time, or send a workbook to a reviewer and import it back.') }}</p>
@include('ahg-core::translation-review._flash')

<form method="GET" action="{{ route('translation-review.index') }}" class="row g-2 align-items-end mb-4">
  <div class="col-auto">
    <label class="form-label" for="tr-locale">{{ __('Language') }}</label>
    <select class="form-select" id="tr-locale" name="locale" onchange="this.form.submit()">
      @foreach($locales as $code)
        <option value="{{ $code }}" @selected($code === $locale)>{{ (class_exists(\Locale::class) ? \Locale::getDisplayName($code, 'en') : $code) }} ({{ $code }})</option>
      @endforeach
    </select>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">{{ $statuses['pending'] ?? __('Awaiting review') }}</div><div class="h4 mb-0">{{ number_format($counts['pending']) }}</div></div></div>
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">{{ __('Held - not live until reviewed') }}</div><div class="h4 mb-0">{{ number_format($counts['held']) }}</div></div></div>
  <div class="col-md-4"><div class="card card-body"><div class="text-muted small">{{ __('Reviewed') }}</div><div class="h4 mb-0">{{ number_format($counts['reviewed']) }}</div></div></div>
</div>

<div class="row g-4">
  <div class="col-lg-4">
    <h2 class="h5">{{ __('Review on screen') }}</h2>
    <p>{{ __('Step through the strings, accept the machine text or type the correct one.') }}</p>
    <a class="btn btn-primary" href="{{ route('translation-review.step', ['locale' => $locale]) }}">{{ __('Start reviewing') }}</a>
    @if($counts['held'] > 0)
      <a class="btn btn-outline-primary" href="{{ route('translation-review.step', ['locale' => $locale, 'held' => 1]) }}">{{ __('Held strings only') }}</a>
    @endif
  </div>
  <div class="col-lg-4">
    <h2 class="h5">{{ __('Review workbook') }}</h2>
    <p>{{ __('One row per string: id, English, machine text, an empty correction column, the placeholders to keep and where the string is used.') }}</p>
    <a class="btn btn-outline-secondary" href="{{ route('translation-review.export', ['locale' => $locale]) }}"><i class="fas fa-download me-1"></i>{{ __('Download workbook') }}</a>
  </div>
  <div class="col-lg-4">
    <h2 class="h5">{{ __('Import a workbook') }}</h2>
    <form method="POST" action="{{ route('translation-review.upload') }}" enctype="multipart/form-data" class="card card-body">
      @csrf
      <input type="hidden" name="locale" value="{{ $locale }}">
      <input class="form-control mb-2" type="file" name="workbook" accept=".xlsx,.csv" required>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="mode" id="tr-mode-review" value="review" checked>
        <label class="form-check-label" for="tr-mode-review">{{ __('Reviewed workbook - preview the changes') }}</label>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="mode" id="tr-mode-stage" value="stage">
        <label class="form-check-label" for="tr-mode-stage">{{ __('Held machine translations - stage for review, nothing goes live') }}</label>
      </div>
      <label class="form-label" for="tr-reviewer">{{ __('Reviewer') }}</label>
      <input class="form-control mb-2" id="tr-reviewer" name="reviewer" maxlength="255" placeholder="{{ __('Name of the native speaker') }}">
      <input type="hidden" name="accept_blank" value="0">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="accept_blank" id="tr-accept-blank" value="1" checked>
        <label class="form-check-label" for="tr-accept-blank">{{ __('A blank correction cell means the reviewer accepted the machine text') }}</label>
      </div>
      <button class="btn btn-primary">{{ __('Upload') }}</button>
    </form>
  </div>
</div>
@endsection
