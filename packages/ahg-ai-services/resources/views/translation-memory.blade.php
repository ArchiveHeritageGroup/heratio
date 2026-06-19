{{--
  AI Services - Translation memory browse (Issue #667 Phase 1).

  Lists ahg_translation_memory entries with hit counts and provenance,
  filters by target language and source/target substring, and lets the
  operator delete a stale entry.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Translation Memory')
@section('body-class', 'admin ai-services translation-memory')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="bi bi-translate"></i> {{ __('Translation Memory') }}</h1>
  <a href="{{ route('admin.ai.index') }}" class="btn btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>{{ __('Back to AI Services') }}
  </a>
</div>

<p class="text-muted">Cached translations indexed by SHA-256 of <em>source + langs</em>. A lookup hit skips the inference dispatch entirely; <code>hit_count</code> tracks reuse and <code>last_used_at</code> shows recency. Delete an entry to force a fresh translation.</p>

@if(session('status'))
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i>{{ session('status') }}
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
</div>
@endif

<form method="get" class="row g-2 mb-3 align-items-end">
  <div class="col-md-3">
    <label for="target_lang" class="form-label small">{{ __('Target language') }}</label>
    <select id="target_lang" name="target_lang" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      @foreach($targetLangs as $lang)
      <option value="{{ $lang }}" @selected(request('target_lang') === $lang)>{{ $lang }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-5">
    <label for="search" class="form-label small">{{ __('Search source / target') }}</label>
    <input id="search" name="search" type="text" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="{{ __('substring match') }}">
  </div>
  <div class="col-md-2">
    <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-funnel me-1"></i>{{ __('Filter') }}</button>
  </div>
  <div class="col-md-2">
    <a href="{{ route('admin.ai-services.tm') }}" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle me-1"></i>{{ __('Reset') }}</a>
  </div>
</form>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-striped mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Source') }} <span class="text-muted small">({{ __('lang') }})</span></th>
          <th>{{ __('Target') }} <span class="text-muted small">({{ __('lang') }})</span></th>
          <th>{{ __('Provenance') }}</th>
          <th class="text-end">{{ __('Confidence') }}</th>
          <th class="text-end">{{ __('Hits') }}</th>
          <th>{{ __('Last used') }}</th>
          <th class="text-center">{{ __('Action') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $row)
        <tr>
          <td style="max-width: 320px;">
            <div class="small text-muted">{{ $row->source_lang ?: '?' }}</div>
            <div class="text-truncate" title="{{ $row->source_text }}">{{ \Illuminate\Support\Str::limit($row->source_text, 160) }}</div>
          </td>
          <td style="max-width: 320px;">
            <div class="small text-muted">{{ $row->target_lang }}</div>
            <div class="text-truncate" title="{{ $row->target_text }}">{{ \Illuminate\Support\Str::limit($row->target_text, 160) }}</div>
          </td>
          <td><span class="badge bg-secondary">{{ $row->provenance }}</span></td>
          <td class="text-end">{{ $row->confidence !== null ? number_format((float) $row->confidence, 3) : '-' }}</td>
          <td class="text-end">{{ number_format((int) $row->hit_count) }}</td>
          <td class="small text-muted">{{ $row->last_used_at ?? '-' }}</td>
          <td class="text-center">
            <form action="{{ route('admin.ai-services.tm.delete') }}" method="post" class="d-inline" onsubmit="return confirm('{{ __('Delete this translation-memory entry?') }}');">
              @csrf
              <input type="hidden" name="id" value="{{ $row->id }}">
              <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No translation-memory entries match.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if(method_exists($rows, 'links'))
  <div class="card-footer bg-white">{{ $rows->withQueryString()->links() }}</div>
  @endif
</div>
@endsection
