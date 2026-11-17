@extends('theme::layouts.1col')
@section('title', 'Browse Assets')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-list me-2"></i>{{ __('Browse Assets') }}</h1>
      <div class="d-flex gap-2">
        @if(Route::has('heritage.accounting.add') && session('add_io_id'))
          <a href="{{ route('heritage.accounting.add', ['io_id' => session('add_io_id')]) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add asset for this record') }}</a>
        @endif
        @if(Route::has('heritage.accounting.add'))
          <a href="{{ route('heritage.accounting.add') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Asset') }}</a>
        @endif
      </div>
    </div>
    <p class="text-muted">{{ __('Browse all heritage assets.') }} @if($items instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator){{ __(':n assets', ['n' => number_format($items->total())]) }}@endif</p>

    {{-- Filters --}}
    <form method="get" class="card mb-3">
      <div class="card-body">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label small" for="fSearch">{{ __('Search') }}</label>
            <input type="text" id="fSearch" name="sq" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Title, identifier or donor') }}">
          </div>
          <div class="col-md-2">
            <label class="form-label small" for="fStd">{{ __('Standard') }}</label>
            <select id="fStd" name="standard_id" class="form-select form-select-sm">
              <option value="">{{ __('All') }}</option>
              @foreach($standards as $st)
                <option value="{{ $st->id }}" {{ (string) ($filters['standard_id'] ?? '') === (string) $st->id ? 'selected' : '' }}>{{ $st->code }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label small" for="fClass">{{ __('Class') }}</label>
            <select id="fClass" name="class_id" class="form-select form-select-sm">
              <option value="">{{ __('All') }}</option>
              @foreach($classes as $cl)
                <option value="{{ $cl->id }}" {{ (string) ($filters['class_id'] ?? '') === (string) $cl->id ? 'selected' : '' }}>{{ $cl->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small" for="fStatus">{{ __('Status') }}</label>
            <select id="fStatus" name="status" class="form-select form-select-sm">
              <option value="">{{ __('All') }}</option>
              @foreach($statusOptions as $sv => $sl)
                <option value="{{ $sv }}" {{ ($filters['recognition_status'] ?? '') === $sv ? 'selected' : '' }}>{{ __($sl) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            @if(!empty($filters['information_object_id']))<input type="hidden" name="io_id" value="{{ $filters['information_object_id'] }}">@endif
            <button type="submit" class="btn btn-sm atom-btn-white"><i class="fas fa-filter me-1"></i>{{ __('Filter') }}</button>
            <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear') }}</a>
          </div>
        </div>
      </div>
    </form>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-list me-2"></i>{{ __('Browse Assets') }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-sm table-striped mb-0">
            <thead><tr>
              @foreach($columns ?? ['ID','Name','Class','Status','Value','Date'] as $col)
                <th>{{ $col }}</th>
              @endforeach
            </tr></thead>
            <tbody>
              @forelse($items ?? [] as $item)
              @php
                $itemName = $item->object_title ?: ($item->object_identifier ?: __('Asset #:id', ['id' => $item->id]));
                // Link the asset to its accounting record; the item name also
                // deep-links to the archival record when a slug is available.
                $recordUrl = Route::has('heritage.accounting.view') ? route('heritage.accounting.view', $item->id) : null;
                $itemUrl = !empty($item->object_slug) ? url('/' . $item->object_slug) : $recordUrl;
              @endphp
              <tr>
                <td>
                  @if($itemUrl)
                    <a href="{{ $itemUrl }}">{{ Str::limit($itemName, 80) }}</a>
                  @else
                    {{ Str::limit($itemName, 80) }}
                  @endif
                  @if($recordUrl && $itemUrl !== $recordUrl)
                    <a href="{{ $recordUrl }}" class="ms-2 small text-muted" title="{{ __('Accounting record') }}"><i class="fas fa-calculator"></i></a>
                  @endif
                </td>
                <td>{{ $item->class_name ?: '-' }}</td>
                <td>{{ $item->standard_code ?: '-' }}</td>
                <td>
                  @auth
                  <select class="form-select form-select-sm hasset-status" data-id="{{ $item->id }}" data-prev="{{ $item->recognition_status ?? 'pending' }}" style="min-width:9rem;" aria-label="{{ __('Recognition status') }}">
                    @foreach($statusOptions as $sv => $sl)
                      <option value="{{ $sv }}" {{ ($item->recognition_status ?? 'pending') === $sv ? 'selected' : '' }}>{{ __($sl) }}</option>
                    @endforeach
                  </select>
                  @else
                    {{ $item->recognition_status ? ucwords(str_replace('_', ' ', $item->recognition_status)) : '-' }}
                  @endauth
                </td>
                <td class="text-end">{{ $item->current_carrying_amount !== null ? number_format((float)$item->current_carrying_amount, 2) : '-' }}</td>
                <td id="hasset-date-{{ $item->id }}">{{ $item->recognition_date ? \Illuminate\Support\Carbon::parse($item->recognition_date)->format('Y-m-d') : '-' }}</td>
              </tr>
              @empty
              <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-3">{{ __('No records found') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if(isset($items) && method_exists($items, 'links'))
      <div class="mt-3">{{ $items->withQueryString()->links() }}</div>
    @endif
  </div>
</div>

@auth
<script nonce="{{ csp_nonce() }}">
document.addEventListener('DOMContentLoaded', function () {
  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';
  document.querySelectorAll('select.hasset-status').forEach(function (sel) {
    sel.addEventListener('change', function () {
      var id = sel.getAttribute('data-id');
      var prev = sel.getAttribute('data-prev');
      var val = sel.value;
      sel.disabled = true;
      fetch('{{ url('/heritage/accounting') }}/' + id + '/status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: JSON.stringify({ recognition_status: val })
      })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
          if (!res.ok || !res.d.ok) {
            sel.value = prev;                                   // revert on failure
            alert(res.d && res.d.error ? res.d.error : 'Could not update status.');
            return;
          }
          sel.setAttribute('data-prev', res.d.recognition_status);
          // Reflect a recognition date stamped server-side (recognising).
          var dateCell = document.getElementById('hasset-date-' + id);
          if (dateCell && res.d.recognition_date) { dateCell.textContent = String(res.d.recognition_date).substring(0, 10); }
          sel.classList.add('border-success');
          setTimeout(function () { sel.classList.remove('border-success'); }, 1200);
        })
        .catch(function () { sel.value = prev; alert('Could not update status.'); })
        .finally(function () { sel.disabled = false; });
    });
  });
});
</script>
@endauth
@endsection