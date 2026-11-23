@extends('theme::layouts.1col')
@section('title', __('Stocktake'))
@section('body-class', 'admin library')

@section('content')
<h1><i class="fas fa-clipboard-check me-2"></i>{{ __('Stocktake') }}</h1>
<p class="text-muted">{{ __('Scan a shelf range, reconcile it against the catalogue and open loans, and sign off the variance.') }}</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-4">
  <div class="card-header">{{ __('Start a stocktake') }}</div>
  <div class="card-body">
    <form method="post" action="{{ route('library.stocktake.store') }}" class="row g-3">
      @csrf
      <div class="col-md-4">
        <label class="form-label" for="st-name">{{ __('Name') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
        <input id="st-name" name="name" class="form-control @error('name') is-invalid @enderror" maxlength="255" required value="{{ old('name') }}">
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-3">
        <label class="form-label" for="st-branch">{{ __('Branch') }}</label>
        <select id="st-branch" name="branch_id" class="form-select @error('branch_id') is-invalid @enderror" @disabled($branchLocked)>
          <option value="">{{ __('All branches') }}</option>
          @foreach($branchOptions as $bid => $bname)
            <option value="{{ $bid }}" @selected((string) old('branch_id', $currentBranch) === (string) $bid)>{{ $bname }}</option>
          @endforeach
        </select>
        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-2">
        <label class="form-label" for="st-from">{{ __('First shelf') }}</label>
        <input id="st-from" name="shelf_from" class="form-control" maxlength="100" value="{{ old('shelf_from') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="st-to">{{ __('Last shelf') }}</label>
        <input id="st-to" name="shelf_to" class="form-control @error('shelf_to') is-invalid @enderror" maxlength="100" value="{{ old('shelf_to') }}">
        @error('shelf_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn atom-btn-outline-success w-100">{{ __('Start') }}</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Shelves') }}</th><th>{{ __('Status') }}</th><th>{{ __('Started') }}</th></tr></thead>
      <tbody>
        @forelse($stocktakes as $s)
          <tr>
            <td><a href="{{ route('library.stocktake.show', $s->id) }}">{{ $s->name }}</a></td>
            <td>{{ $s->branch_id ? ($branchOptions[(int) $s->branch_id] ?? '#' . $s->branch_id) : __('All branches') }}</td>
            <td>{{ $s->shelf_from || $s->shelf_to ? ($s->shelf_from ?? '...') . ' - ' . ($s->shelf_to ?? '...') : __('All shelves') }}</td>
            <td>{{ $statusLabels[$s->status] ?? $s->status }}</td>
            <td>{{ $s->started_at }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-muted text-center py-3">{{ __('No stocktakes yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<div class="mt-3">{{ $stocktakes->links() }}</div>
@endsection
