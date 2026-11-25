@extends('theme::layouts.1col')
@section('title', $stocktake->name)
@section('body-class', 'admin library')

@section('content')
@php($signed = $stocktake->status === 'signed_off')
<nav aria-label="{{ __('breadcrumb') }}"><ol class="breadcrumb">
  <li class="breadcrumb-item"><a href="{{ route('library.stocktake.index') }}">{{ __('Stocktake') }}</a></li>
  <li class="breadcrumb-item active">{{ $stocktake->name }}</li>
</ol></nav>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <h1 class="mb-0">{{ $stocktake->name }}</h1>
  <span class="badge bg-{{ $signed ? 'success' : 'secondary' }} fs-6">{{ $statusLabels[$stocktake->status] ?? $stocktake->status }}</span>
</div>
<p class="text-muted">
  {{ $branchName ?? __('All branches') }} -
  {{ $stocktake->shelf_from || $stocktake->shelf_to ? ($stocktake->shelf_from ?? '...') . ' - ' . ($stocktake->shelf_to ?? '...') : __('All shelves') }} -
  {{ __(':n barcodes scanned', ['n' => $scanCount]) }}
</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="row g-3 mb-4">
  @unless($signed)
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header">{{ __('Scan') }}</div>
      <div class="card-body">
        <form method="post" action="{{ route('library.stocktake.scan', $stocktake->id) }}">
          @csrf
          <div class="mb-2">
            <label class="form-label" for="scan-shelf">{{ __('Shelf being counted') }}</label>
            <input id="scan-shelf" name="scanned_shelf" class="form-control" maxlength="100" value="{{ old('scanned_shelf') }}">
          </div>
          <div class="mb-2">
            <label class="form-label" for="scan-codes">{{ __('Barcodes, one per line') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
            <textarea id="scan-codes" name="barcodes" rows="5" class="form-control" required autofocus></textarea>
          </div>
          <button class="btn atom-btn-outline-success">{{ __('Record scans') }}</button>
        </form>
      </div>
    </div>
  </div>
  @endunless
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">{{ __('Variance') }}</div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <a class="btn btn-sm atom-btn-white" href="{{ route('library.stocktake.show', $stocktake->id) }}">{{ __('All') }}</a>
          @foreach($classLabels as $code => $label)
            <a class="btn btn-sm {{ $filter === $code ? 'atom-btn-outline-success' : 'atom-btn-white' }}" href="{{ route('library.stocktake.show', [$stocktake->id, 'classification' => $code]) }}">{{ $label }}: {{ $counts[$code] ?? 0 }}</a>
          @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2">
          @unless($signed)
            <form method="post" action="{{ route('library.stocktake.reconcile', $stocktake->id) }}">@csrf<button class="btn atom-btn-white">{{ __('Reconcile') }}</button></form>
          @endunless
          <a class="btn atom-btn-white" href="{{ route('library.stocktake.export', $stocktake->id) }}">{{ __('Export CSV') }}</a>
        </div>
        @if($stocktake->status === 'reconciled')
          <hr>
          @if($canSignOff)
            <form method="post" action="{{ route('library.stocktake.sign-off', $stocktake->id) }}">
              @csrf
              <label class="form-label" for="so-note">{{ __('Sign-off note') }}</label>
              <textarea id="so-note" name="sign_off_note" rows="2" class="form-control mb-2" maxlength="5000"></textarea>
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="confirm" value="1" id="so-confirm" required>
                <label class="form-check-label" for="so-confirm">{{ __('I have reviewed this variance. It cannot change after sign-off.') }}</label>
              </div>
              <button class="btn atom-btn-outline-success">{{ __('Sign off') }}</button>
            </form>
          @else
            <p class="text-muted mb-0">{{ __('An administrator other than the person who started this stocktake must sign it off.') }}</p>
          @endif
        @elseif($signed)
          <hr><p class="mb-0">{{ __('Signed off :when.', ['when' => $stocktake->signed_off_at]) }} {{ $stocktake->sign_off_note }}</p>
        @endif
      </div>
    </div>
  </div>
</div>

@if($signed && $isAdmin && ! $heritageAvailable)
  <div class="alert alert-info">{{ __('The heritage asset register is not available here, so missing copies cannot be posted.') }}</div>
@endif

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-sm mb-0 align-middle">
      <thead><tr>
        <th>{{ __('Barcode') }}</th><th>{{ __('Title') }}</th><th>{{ __('Home shelf') }}</th><th>{{ __('Seen on') }}</th><th>{{ __('Result') }}</th><th>{{ __('Reason') }}</th>
        @if($signed && $isAdmin)<th>{{ __('Asset register') }}</th>@endif
      </tr></thead>
      <tbody>
        @forelse($lines as $l)
          <tr>
            <td>{{ $l->barcode }}</td>
            <td>{{ $l->title }}</td>
            <td>{{ $l->home_shelf }}</td>
            <td>{{ $l->scanned_shelf }}</td>
            <td>{{ $classLabels[$l->classification] ?? $l->classification }}</td>
            <td>{{ $reasonLabels[$l->reason_code] ?? $l->reason_code }}</td>
            @if($signed && $isAdmin)
              <td>
                @if($l->classification !== 'missing')
                @elseif($l->posted_at)
                  {{ __('Posted :amount on :when', ['amount' => number_format((float) $l->impairment_amount, 2), 'when' => $l->posted_at]) }}
                @elseif(! $l->heritage_asset_id)
                  <span class="text-muted">{{ __('No single recognised entry') }}</span>
                @elseif(! empty($postingBlocks[$l->id]))
                  <span class="text-danger">{{ $postingBlocks[$l->id] }}</span>
                @elseif($heritageAvailable)
                  <form method="post" action="{{ route('library.stocktake.post-impairment', [$stocktake->id, $l->id]) }}" class="d-flex flex-wrap gap-1">
                    @csrf
                    <input type="number" name="amount" step="0.01" min="0.01" max="{{ $l->carrying_snapshot }}" value="{{ $l->proposed_amount }}" class="form-control form-control-sm" style="width:7rem" aria-label="{{ __('Impairment amount') }}" required>
                    <span class="align-self-center small">{{ $currency }}</span>
                    <input type="date" name="valuation_date" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" class="form-control form-control-sm" style="width:9rem" aria-label="{{ __('Impairment date') }}" required>
                    <button class="btn btn-sm atom-btn-outline-danger">{{ __('Post impairment') }}</button>
                  </form>
                  <small class="text-muted">{{ __('Carrying amount at sign-off: :amount', ['amount' => number_format((float) $l->carrying_snapshot, 2)]) }}</small>
                @endif
              </td>
            @endif
          </tr>
        @empty
          <tr><td colspan="7" class="text-muted text-center py-3">{{ __('Nothing to show. Record scans, then reconcile.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $lines->links() }}</div>
@endsection
