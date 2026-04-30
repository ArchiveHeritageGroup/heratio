@extends('theme::layouts.1col')
@section('title', 'Gallery Loans')
@section('body-class', 'gallery loans')
@section('title-block')<h1 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Gallery Loans</h1>@endsection
@section('content')
@auth<div class="mb-3"><a href="{{ route('gallery.loans.create') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Create Loan') }}</a></div>@endauth
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Loan Records') }}</h5></div>
  <div class="card-body p-0">
    @if(isset($loans) && count($loans) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('Title') }}</th><th>{{ __('Type') }}</th><th>{{ __('Borrower/Lender') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead>
    <tbody>@foreach($loans as $l)<tr><td>{{ $l->title ?? '' }}</td><td>{{ ucfirst($l->loan_type ?? '') }}</td><td>{{ $l->borrower_name ?? $l->lender_name ?? '' }}</td><td>{{ $l->start_date ?? '-' }}</td><td>{{ $l->end_date ?? '-' }}</td><td><span class="badge bg-{{ ($l->status ?? '') === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($l->status ?? 'pending') }}</span></td><td><a href="{{ route('gallery.loans.show', $l->id) }}" class="btn btn-sm atom-btn-white">View</a></td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No loan records found.</div>@endif
  </div>
</div>
@endsection
