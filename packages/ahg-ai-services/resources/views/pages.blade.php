@extends('theme::layouts.1col')

{{-- Per-page document type and privacy screening of a record's PDF, with the
     record-level roll-up (privacy is the maximum: one flagged page flags the
     record) and per-page review (heratio#1584). --}}

@section('title', __('Pages').' - '.($io->title ?? ''))
@section('body-class', 'browse ai')

@section('content')
@php
  $codes = array_keys($types);
  $palette = ['#10373E', '#E1B34F', '#653332', '#5b7f86', '#a8842f', '#8c5b5a', '#2f5d64', '#c9a14a', '#7a4644', '#3d6e75', '#b8934a', '#4d2827'];
  $colour = fn ($code) => $code === null ? '#C3CDCE' : $palette[(int) array_search($code, $codes, true) % count($palette)];
  $label = fn ($code) => $code === null ? __('Not classified') : ($types[$code] ?? $code);
  $range = fn ($s) => $s['from'] === $s['to'] ? (string) $s['from'] : $s['from'].'-'.$s['to'];
  $privacyLabels = ['flagged' => __('Personal information'), 'clear' => __('None found'), 'unread' => __('No text to read')];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="h3 mb-1"><i class="fas fa-layer-group me-2" aria-hidden="true"></i>{{ __('Pages') }}</h1>
    <a href="{{ url('/'.$io->slug) }}">{{ $io->title }}</a>
  </div>
  <form method="POST" action="{{ route('admin.ai.pages.run', $io->slug) }}">
    @csrf
    <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-magic me-1" aria-hidden="true"></i>{{ $segments ? __('Read the pages again') : __('Read the pages') }}</button>
  </form>
</div>

@foreach(['success' => 'success', 'error' => 'danger'] as $k => $cls)
  @if(session($k))<div class="alert alert-{{ $cls }}">{{ session($k) }}</div>@endif
@endforeach

@if(! $segments)
  <p class="text-muted">{{ __('Not read yet. Each page is screened for personal information and given a document type, so one label for the file does not hide what a mixed file contains.') }}</p>
@else
  @if($rollup['privacy'] === 'flagged')
    <div class="alert alert-danger"><i class="fas fa-user-shield me-1" aria-hidden="true"></i>{{ trans_choice('{1} Personal information on :n page (:pages): the record is flagged for privacy review.|[2,*] Personal information on :n pages (:pages): the record is flagged for privacy review.', count($rollup['flagged']), ['n' => count($rollup['flagged']), 'pages' => implode(', ', $rollup['flagged'])]) }}</div>
  @elseif($rollup['privacy'] === 'incomplete')
    <div class="alert alert-warning"><i class="fas fa-question-circle me-1" aria-hidden="true"></i>{{ __('No personal information found, but these pages had no text to read: :pages. Check them by eye.', ['pages' => implode(', ', $rollup['unread'])]) }}</div>
  @else
    <div class="alert alert-success"><i class="fas fa-check-circle me-1" aria-hidden="true"></i>{{ __('No personal information found on any of the :n pages.', ['n' => $rollup['pages']]) }}</div>
  @endif

  @if($rollup['spread'])
    <p class="mb-2">
      @foreach($rollup['spread'] as $code => $n)
        <span class="badge me-1" style="background: {{ $colour($code) }}">{{ __(':n of :total pages: :type', ['n' => $n, 'total' => $rollup['pages'], 'type' => $label($code)]) }}</span>
      @endforeach
    </p>
  @endif

  <h2 class="h5 mt-3">{{ __('Page strip') }}</h2>
  <div class="d-flex mb-1 border rounded overflow-hidden" style="height: 2.5rem" role="list" aria-label="{{ __('Pages of the file') }}">
    @foreach($segments as $s)
      @php $t = $s['doctype']['label'] ?? null; $p = $s['privacy']['label'] ?? null; @endphp
      <a role="listitem" href="{{ $pdfUrl ? $pdfUrl.'#page='.$s['from'] : '#seg-'.$s['from'] }}" @if($pdfUrl) target="_blank" rel="noopener" @endif
         class="d-flex align-items-center justify-content-center text-white text-decoration-none small"
         style="flex: {{ $s['to'] - $s['from'] + 1 }} 1 0; background: {{ $colour($t) }}; border-right: 1px solid #fff; {{ $p === 'flagged' ? 'box-shadow: inset 0 -6px 0 #dc3545;' : '' }}"
         title="{{ __('Page :p', ['p' => $range($s)]) }}: {{ $label($t) }}{{ $p === 'flagged' ? ' - '.__('Personal information') : '' }}">
        {{ $range($s) }}@if($p === 'flagged') <i class="fas fa-user-shield ms-1" aria-hidden="true"></i><span class="visually-hidden">{{ __('Personal information') }}</span>@endif
      </a>
    @endforeach
  </div>
  <p class="small text-muted mb-4">{{ __('Click a page to open it. A red bar marks a page with personal information.') }}</p>

  <div class="table-responsive mb-3">
    <table class="table table-bordered table-sm align-middle">
      <thead><tr><th scope="col">{{ __('Pages') }}</th><th scope="col">{{ __('Document type') }}</th><th scope="col">{{ __('Privacy') }}</th><th scope="col">{{ __('Review') }}</th></tr></thead>
      <tbody>
        @foreach($segments as $s)
          <tr id="seg-{{ $s['from'] }}">
            <td>{{ $range($s) }}</td>
            <td>
              {{ $label($s['doctype']['label'] ?? null) }}
              @if($s['doctype']['overridden'] ?? false)<span class="badge bg-secondary ms-1">{{ __('Reviewed') }}</span>@endif
              @if($s['doctype'] ?? null)<div class="small text-muted">{{ $s['doctype']['model'] }}</div>@endif
            </td>
            <td>
              {{ $privacyLabels[$s['privacy']['label'] ?? 'unread'] ?? ($s['privacy']['label'] ?? '') }}
              @if($s['privacy']['types'] ?? [])<div class="small text-muted">{{ implode(', ', $s['privacy']['types']) }}</div>@endif
              @if($s['privacy']['overridden'] ?? false)<span class="badge bg-secondary">{{ __('Reviewed') }}</span>@endif
            </td>
            <td>
              @if($s['doctype'] ?? null)
                <form method="POST" action="{{ route('admin.ai.pages.review', [$io->slug, $s['doctype']['id']]) }}" class="d-flex gap-1 mb-1">
                  @csrf
                  <label class="visually-hidden" for="t-{{ $s['doctype']['id'] }}">{{ __('Document type') }}</label>
                  <select id="t-{{ $s['doctype']['id'] }}" name="value" class="form-select form-select-sm">
                    @foreach($types as $code => $name)<option value="{{ $code }}" @selected($code === $s['doctype']['label'])>{{ __($name) }}</option>@endforeach
                  </select>
                  <button type="submit" class="btn btn-sm atom-btn-white">{{ __('Save') }}</button>
                </form>
              @endif
              @if($s['privacy'] ?? null)
                <form method="POST" action="{{ route('admin.ai.pages.review', [$io->slug, $s['privacy']['id']]) }}" class="d-flex gap-1">
                  @csrf
                  <label class="visually-hidden" for="p-{{ $s['privacy']['id'] }}">{{ __('Privacy') }}</label>
                  <select id="p-{{ $s['privacy']['id'] }}" name="value" class="form-select form-select-sm">
                    <option value="flagged" @selected(($s['privacy']['label'] ?? '') === 'flagged')>{{ __('Personal information') }}</option>
                    <option value="clear" @selected(($s['privacy']['label'] ?? '') === 'clear')>{{ __('None found') }}</option>
                  </select>
                  <button type="submit" class="btn btn-sm atom-btn-white">{{ __('Save') }}</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p class="small text-muted">{{ __('Each page result is recorded with its model and an input digest; a review is recorded as a correction, and the AI result is kept.') }}
    <a href="{{ url('/privacy/redaction/'.$io->slug) }}">{{ __('Redaction') }}</a></p>
@endif
@endsection
