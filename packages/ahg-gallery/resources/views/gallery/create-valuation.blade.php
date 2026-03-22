@extends('theme::layouts.1col')
@section('title', 'Create Valuation')
@section('body-class', 'gallery create-valuation')
@section('title-block')<h1 class="mb-0">Create Valuation</h1>@endsection
@section('content')
<form method="post" action="{{ route('gallery.valuations.store') }}">@csrf
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Valuation Details</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-6 mb-3"><label for="valuation_type" class="form-label">Valuation Type</label><select name="valuation_type" id="valuation_type" class="form-select"><option value="insurance">Insurance</option><option value="market">Market</option><option value="replacement">Replacement</option><option value="fair_market">Fair Market</option></select></div>
  <div class="col-md-6 mb-3"><label for="value" class="form-label">Value (R) <span class="text-danger">*</span></label><input type="number" name="value" id="value" class="form-control" step="0.01" required></div>
  <div class="col-md-6 mb-3"><label for="valuation_date" class="form-label">Valuation Date</label><input type="date" name="valuation_date" id="valuation_date" class="form-control" value="{{ date('Y-m-d') }}"></div>
  <div class="col-md-6 mb-3"><label for="appraiser" class="form-label">Appraiser</label><input type="text" name="appraiser" id="appraiser" class="form-control"></div>
  <div class="col-12 mb-3"><label for="notes" class="form-label">Notes</label><textarea name="notes" id="notes" class="form-control" rows="3"></textarea></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><a href="{{ route('gallery.valuations') }}" class="btn atom-btn-outline-light">Cancel</a><button type="submit" class="btn atom-btn-outline-light">Save</button></section>
</form>
@endsection
