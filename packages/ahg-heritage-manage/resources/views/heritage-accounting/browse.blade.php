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
    <p class="text-muted">Browse all heritage assets.</p>

    {{-- Stats --}}
    @if(!empty($stats))
    <div class="row mb-4">
      @foreach($stats as $key => $value)
      <div class="col-md-3 mb-3">
        <div class="card bg-{{ ['primary','success','warning','info'][$loop->index % 4] }} text-white h-100">
          <div class="card-body"><h6 class="text-white-50">{{ ucwords(str_replace('_', ' ', $key)) }}</h6><h2 class="mb-0">{{ is_numeric($value) ? number_format($value, (strpos($key,'value')!==false?2:0)) : $value }}</h2></div>
        </div>
      </div>
      @endforeach
    </div>
    @endif

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
                $itemName = $item->item_name ?: ('Asset #' . $item->id);
                // Link the asset to its accounting record; the item name also
                // deep-links to the archival record when a slug is available.
                $recordUrl = Route::has('heritage.accounting.view') ? route('heritage.accounting.view', $item->id) : null;
                $itemUrl = !empty($item->slug) ? url('/' . $item->slug) : $recordUrl;
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
                <td>
                  @auth
                  <select class="form-select form-select-sm hasset-status" data-id="{{ $item->id }}" data-prev="{{ $item->recognition_status ?? 'pending' }}" style="min-width:9rem;" aria-label="{{ __('Recognition status') }}">
                    @foreach(['pending' => __('Pending'), 'recognised' => __('Recognised'), 'not_recognised' => __('Not Recognised')] as $sv => $sl)
                      <option value="{{ $sv }}" {{ ($item->recognition_status ?? 'pending') === $sv ? 'selected' : '' }}>{{ $sl }}</option>
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
              <tr><td colspan="{{ count($columns ?? ['Asset','Class','Status','Carrying value','Recognised']) }}" class="text-center text-muted py-3">No records found</td></tr>
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