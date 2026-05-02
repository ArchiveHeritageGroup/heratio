@extends('theme::layouts.1col')

@section('title', __('UI string translations'))
@section('body-class', 'admin translation-strings')

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">{{ __('Admin') }}</a></li>
      <li class="breadcrumb-item"><a href="{{ route('ahgtranslation.languages') }}">{{ __('Translation') }}</a></li>
      <li class="breadcrumb-item active">{{ __('UI strings') }}</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <h1 class="h3 mb-1"><i class="fas fa-language me-2"></i>{{ __('UI string translations') }}</h1>
      <p class="small text-muted mb-0">
        {{ __('Edit lang/{locale}.json files inline. Changes apply immediately on next request — no view-cache rebuild needed.') }}
      </p>
    </div>
    <a href="{{ route('ahgtranslation.languages') }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to languages') }}
    </a>
  </div>

  {{-- Filter bar --}}
  <form method="GET" class="card mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label form-label-sm mb-0">{{ __('Locale (column)') }}</label>
          <select name="locale" class="form-select form-select-sm">
            <option value="">{{ __('All enabled') }}</option>
            @foreach($allLocales as $code)
              @if($code === 'en') @continue @endif
              <option value="{{ $code }}" {{ ($locale ?? '') === $code ? 'selected' : '' }}>{{ $code }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm mb-0">{{ __('Missing in') }}</label>
          <select name="missing" class="form-select form-select-sm">
            <option value="">{{ __('— any —') }}</option>
            @foreach($allLocales as $code)
              @if($code === 'en') @continue @endif
              <option value="{{ $code }}" {{ ($missing ?? '') === $code ? 'selected' : '' }}>{{ $code }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label form-label-sm mb-0">{{ __('Contains') }}</label>
          <input type="text" name="contains" class="form-control form-control-sm" placeholder="{{ __('substring of key, English source, or translation') }}" value="{{ $contains }}">
        </div>
        <div class="col-md-2 text-end">
          <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fas fa-filter me-1"></i>{{ __('Apply') }}</button>
        </div>
      </div>
    </div>
  </form>

  {{-- Stats --}}
  <p class="small text-muted">
    {{ __(':total keys', ['total' => number_format($matrix['total'] ?? 0)]) }}
    @if($missing) — {{ __('missing in :code', ['code' => $missing]) }} @endif
    @if($contains) — {{ __('matching ":q"', ['q' => $contains]) }} @endif
  </p>

  {{-- Editor table --}}
  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle mb-0">
      <thead class="table-light sticky-top">
        <tr>
          <th style="width:30%;">{{ __('Key (English source)') }}</th>
          @foreach($matrix['locales'] as $code)
            <th style="width:{{ floor(70 / max(1, count($matrix['locales']))) }}%;">{{ $code }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @forelse($matrix['rows'] as $row)
          <tr>
            <td class="small">
              <div class="fw-semibold">{{ $row['key'] }}</div>
              @if($row['en'] !== $row['key'])
                <div class="text-muted">{{ $row['en'] }}</div>
              @endif
            </td>
            @foreach($matrix['locales'] as $code)
              @php $cell = $row['translations'][$code] ?? ['value' => null, 'missing' => true]; @endphp
              <td class="ui-string-cell" data-key="{{ $row['key'] }}" data-locale="{{ $code }}">
                <textarea
                  class="form-control form-control-sm ui-string-input {{ $cell['missing'] ? 'border-warning' : '' }}"
                  rows="1"
                  placeholder="{{ __('(missing)') }}"
                  data-original="{{ $cell['value'] ?? '' }}">{{ $cell['value'] ?? '' }}</textarea>
                <div class="ui-string-status small mt-1" style="min-height:1em;"></div>
              </td>
            @endforeach
          </tr>
        @empty
          <tr><td colspan="{{ 1 + count($matrix['locales']) }}" class="text-center text-muted py-4">{{ __('No keys match the current filter.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if(($totalPages ?? 1) > 1)
    <nav class="mt-3">
      <ul class="pagination justify-content-center pagination-sm">
        <li class="page-item{{ ($page ?? 1) <= 1 ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => ($page ?? 1) - 1])) }}">&laquo;</a>
        </li>
        @for($i = max(1, ($page ?? 1) - 2); $i <= min($totalPages, ($page ?? 1) + 2); $i++)
          <li class="page-item{{ $i === ($page ?? 1) ? ' active' : '' }}">
            <a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => $i])) }}">{{ $i }}</a>
          </li>
        @endfor
        <li class="page-item{{ ($page ?? 1) >= $totalPages ? ' disabled' : '' }}">
          <a class="page-link" href="?{{ http_build_query(array_merge(request()->query(), ['page' => ($page ?? 1) + 1])) }}">&raquo;</a>
        </li>
      </ul>
    </nav>
  @endif

</div>
@endsection

@push('css')
<style>
  .ui-string-input { min-height:34px; resize:vertical; }
  .ui-string-cell.is-saving .ui-string-input { background:#fff8e1; }
  .ui-string-cell.is-saved  .ui-string-status { color:#198754; }
  .ui-string-cell.is-error  .ui-string-status { color:#dc3545; }
</style>
@endpush

@push('js')
<script>
(function () {
  function ready(fn){ document.readyState !== 'loading' ? fn() : document.addEventListener('DOMContentLoaded', fn); }
  ready(function () {
    var saveUrl = "{{ route('ahgtranslation.strings.save') }}";
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute && document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function debounce(fn, ms) {
      var t; return function () {
        var ctx = this, args = arguments;
        clearTimeout(t); t = setTimeout(function () { fn.apply(ctx, args); }, ms);
      };
    }

    document.querySelectorAll('.ui-string-cell').forEach(function (cell) {
      var input  = cell.querySelector('.ui-string-input');
      var status = cell.querySelector('.ui-string-status');
      if (!input) return;

      var commit = debounce(function () {
        var value = input.value;
        var orig  = input.getAttribute('data-original') || '';
        if (value === orig) return;
        cell.classList.remove('is-saved', 'is-error');
        cell.classList.add('is-saving');
        status.textContent = 'Saving...';

        var fd = new FormData();
        fd.append('_token', csrf || '');
        fd.append('locale', cell.getAttribute('data-locale'));
        fd.append('key',    cell.getAttribute('data-key'));
        fd.append('value',  value);

        fetch(saveUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          cell.classList.remove('is-saving');
          if (d && d.ok) {
            cell.classList.add('is-saved');
            status.textContent = '✓ saved';
            input.setAttribute('data-original', value);
            input.classList.remove('border-warning');
            setTimeout(function () { cell.classList.remove('is-saved'); status.textContent = ''; }, 2500);
          } else {
            cell.classList.add('is-error');
            status.textContent = '✗ ' + ((d && d.error) || 'save failed');
          }
        })
        .catch(function (e) {
          cell.classList.remove('is-saving');
          cell.classList.add('is-error');
          status.textContent = '✗ ' + (e.message || 'network error');
        });
      }, 600);

      // Save on blur OR after 600ms idle while typing.
      input.addEventListener('blur', commit);
      input.addEventListener('input', commit);
    });
  });
})();
</script>
@endpush
