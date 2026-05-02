@extends('theme::layouts.1col')

@section('title', __('UI string review queue'))
@section('body-class', 'admin translation-strings-pending')

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgtranslation.languages') }}">{{ __('Translation') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgtranslation.strings') }}">{{ __('UI strings') }}</a></li>
      <li class="breadcrumb-item active">{{ __('Review queue') }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h3 mb-1"><i class="fas fa-clipboard-check me-2"></i>{{ __('UI string review queue') }}</h1>
      <p class="small text-muted mb-0">
        {{ __('Pending changes submitted by editors (and admins who opted in to peer review). Approving applies the change to lang/{locale}.json immediately.') }}
      </p>
    </div>
    <a href="{{ route('ahgtranslation.strings') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to editor') }}
    </a>
  </div>

  <form method="GET" class="d-flex gap-2 mb-3">
    <select name="locale" class="form-select form-select-sm" style="max-width:200px;">
      <option value="">{{ __('All locales') }}</option>
      @foreach($allLocales as $code)
        @if($code === 'en') @continue @endif
        <option value="{{ $code }}" {{ ($locale ?? '') === $code ? 'selected' : '' }}>{{ $code }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
  </form>

  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle">
      <thead class="table-light">
        <tr>
          <th>{{ __('Locale') }}</th>
          <th style="width:25%;">{{ __('Key') }}</th>
          <th style="width:25%;">{{ __('Old value') }}</th>
          <th style="width:25%;">{{ __('Proposed value') }}</th>
          <th>{{ __('Submitted by') }}</th>
          <th class="text-end" style="width:140px;">{{ __('Action') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse($changes as $c)
          <tr id="change-{{ $c->id }}">
            <td><span class="badge bg-info">{{ $c->locale }}</span></td>
            <td><code class="small">{{ \Illuminate\Support\Str::limit($c->key_text, 80) }}</code></td>
            <td class="small text-muted">{{ \Illuminate\Support\Str::limit($c->old_value ?? '(empty)', 80) }}</td>
            <td class="small">{{ \Illuminate\Support\Str::limit($c->new_value ?? '(empty)', 80) }}</td>
            <td class="small">
              <strong>{{ $c->submitted_by_name ?? $c->username ?? ('user#' . $c->submitted_by_user_id) }}</strong><br>
              <span class="text-muted">{{ \Carbon\Carbon::parse($c->submitted_at)->diffForHumans() }}</span>
            </td>
            <td class="text-end">
              <button type="button" class="btn btn-sm btn-success approve-btn" data-id="{{ $c->id }}"><i class="fas fa-check me-1"></i>{{ __('Approve') }}</button>
              <button type="button" class="btn btn-sm btn-outline-danger reject-btn" data-id="{{ $c->id }}"><i class="fas fa-times"></i></button>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No pending changes.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

</div>
@endsection

@push('js')
<script>
(function () {
  function ready(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  ready(function () {
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute && document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function act(id, action) {
      var url = '/admin/translation/strings/' + id + '/' + action;
      var fd = new FormData();
      fd.append('_token', csrf || '');
      var note = action === 'reject' ? prompt('Reason (optional):') || '' : '';
      if (note) fd.append('note', note);
      return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json' }, body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var row = document.getElementById('change-' + id);
          if (d && d.ok && row) {
            row.style.transition = 'opacity 0.3s'; row.style.opacity = '0';
            setTimeout(function () { row.remove(); }, 300);
          } else if (d && d.error) {
            alert('Failed: ' + d.error);
          }
        });
    }

    document.querySelectorAll('.approve-btn').forEach(function (b) { b.addEventListener('click', function () { act(b.dataset.id, 'approve'); }); });
    document.querySelectorAll('.reject-btn').forEach(function (b) { b.addEventListener('click', function () { act(b.dataset.id, 'reject'); }); });
  });
})();
</script>
@endpush
