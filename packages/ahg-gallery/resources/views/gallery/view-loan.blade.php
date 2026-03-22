@extends('theme::layouts.1col')
@section('title', 'View Loan')
@section('body-class', 'gallery view-loan')
@section('title-block')<h1 class="mb-0">{{ $loan->title ?? 'Loan Details' }}</h1>@endsection
@section('content')
<div class="card"><div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Loan Details</h5></div>
<div class="card-body"><div class="row"><div class="col-md-6"><dl>
  @if($loan->title ?? null)<dt>Title</dt><dd>{{ $loan->title }}</dd>@endif
  @if($loan->loan_type ?? null)<dt>Type</dt><dd>{{ ucfirst($loan->loan_type) }}</dd>@endif
  @if($loan->borrower_name ?? null)<dt>Borrower/Lender</dt><dd>{{ $loan->borrower_name }}</dd>@endif
  @if($loan->start_date ?? null)<dt>Start Date</dt><dd>{{ $loan->start_date }}</dd>@endif
  @if($loan->end_date ?? null)<dt>End Date</dt><dd>{{ $loan->end_date }}</dd>@endif
</dl></div><div class="col-md-6"><dl>
  @if($loan->insurance_value ?? null)<dt>Insurance Value</dt><dd>R {{ number_format($loan->insurance_value, 2) }}</dd>@endif
  @if($loan->loan_fee ?? null)<dt>Loan Fee</dt><dd>R {{ number_format($loan->loan_fee, 2) }}</dd>@endif
  @if($loan->status ?? null)<dt>Status</dt><dd><span class="badge bg-{{ $loan->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($loan->status) }}</span></dd>@endif
</dl></div></div>
@if($loan->conditions ?? null)<h6>Conditions</h6><p>{!! nl2br(e($loan->conditions)) !!}</p>@endif
@if($loan->notes ?? null)<h6>Notes</h6><p>{!! nl2br(e($loan->notes)) !!}</p>@endif
</div></div>
<div class="mt-3"><a href="{{ route('gallery.loans') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Loans</a></div>
@endsection
