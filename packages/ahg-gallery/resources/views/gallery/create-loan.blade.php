@extends('theme::layouts.1col')
@section('title', 'Create Loan')
@section('body-class', 'gallery create-loan')
@section('title-block')<h1 class="mb-0">{{ __('Create Loan') }}</h1>@endsection
@section('content')
<form method="post" action="{{ route('gallery.loans.store') }}">@csrf
<div class="card mb-4"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Loan Details') }}</h5></div>
<div class="card-body"><div class="row">
  <div class="col-md-6 mb-3"><label for="title" class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="title" id="title" class="form-control" required></div>
  <div class="col-md-6 mb-3"><label for="loan_type" class="form-label">Loan Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="loan_type" id="loan_type" class="form-select"><option value="incoming">{{ __('Incoming') }}</option><option value="outgoing">{{ __('Outgoing') }}</option></select></div>
  <div class="col-md-6 mb-3"><label for="borrower_name" class="form-label">Borrower/Lender <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" name="borrower_name" id="borrower_name" class="form-control"></div>
  <div class="col-md-3 mb-3"><label for="start_date" class="form-label">Start Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" name="start_date" id="start_date" class="form-control"></div>
  <div class="col-md-3 mb-3"><label for="end_date" class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" name="end_date" id="end_date" class="form-control"></div>
  <div class="col-md-6 mb-3"><label for="insurance_value" class="form-label">Insurance Value (R) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" name="insurance_value" id="insurance_value" class="form-control" step="0.01"></div>
  <div class="col-md-6 mb-3"><label for="loan_fee" class="form-label">Loan Fee (R) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" name="loan_fee" id="loan_fee" class="form-control" step="0.01"></div>
  <div class="col-12 mb-3"><label for="conditions" class="form-label">Conditions <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="conditions" id="conditions" class="form-control" rows="3"></textarea></div>
  <div class="col-12 mb-3"><label for="notes" class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="notes" id="notes" class="form-control" rows="3"></textarea></div>
</div></div></div>
<section class="actions mb-3 nav gap-2" style="background-color:#495057;border-radius:.375rem;padding:1rem;"><a href="{{ route('gallery.loans') }}" class="btn atom-btn-outline-light">Cancel</a><button type="submit" class="btn atom-btn-outline-light">{{ __('Save') }}</button></section>
</form>
@endsection
