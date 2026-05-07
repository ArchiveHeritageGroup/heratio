@extends('theme::layouts.1col')

@section('title', __('Loans Report'))

@section('content')

<h1><i class="fas fa-exchange-alt"></i> {{ __('Loans Report') }}</h1>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Summary') }}</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li><i class="fas fa-arrow-down text-success me-2"></i>{{ __('Loans In:') }} {{ $summary['totalIn'] ?? 0 }}</li>
                    <li><i class="fas fa-arrow-up text-warning me-2"></i>{{ __('Loans Out:') }} {{ $summary['totalOut'] ?? 0 }}</li>
                </ul>
                {{-- #91 verification follow-up: surface the default loan
                     period + currency from spectrum settings so operators
                     creating new loans can see the institution defaults
                     at a glance. The setting (spectrum_loan_default_period
                     + spectrum_default_currency) is now genuinely consumed. --}}
                @if(isset($defaultLoanDays) || isset($defaultCurrency))
                <hr class="my-2">
                <div class="small text-muted">
                    @if(isset($defaultLoanDays))
                      <div><i class="fas fa-calendar-day me-1"></i>{{ __('Default loan period') }}: <strong>{{ (int) $defaultLoanDays }} {{ __('days') }}</strong></div>
                    @endif
                    @if(isset($defaultCurrency))
                      <div><i class="fas fa-money-bill-wave me-1"></i>{{ __('Default currency') }}: <strong>{{ $defaultCurrency }}</strong></div>
                    @endif
                </div>
                @endif
            </div>
            <div class="card-footer">
                <a href="{{ route('ahgspectrum.dashboard') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back') }}</a>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <h4 class="text-success"><i class="fas fa-arrow-down me-2"></i>{{ __('Loans In') }} ({{ count($loansIn ?? []) }})</h4>
        @if(empty($loansIn))
        <p class="text-muted">{{ __('No loans in recorded.') }}</p>
        @else
        <div class="table-responsive mb-4">
            <table class="table table-striped">
                <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Lender') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($loansIn as $l)
                <tr>
                    <td>@if($l->slug)<a href="/{{ $l->slug }}">{{ $l->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $l->lender_name ?? $l->lender ?? '-' }}</td>
                    <td>{{ $l->loan_start_date ?? $l->start_date ?? '-' }}</td>
                    <td>{{ $l->loan_end_date ?? $l->end_date ?? '-' }}</td>
                    <td><span class="badge bg-info">{{ $l->status ?? 'Active' }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <h4 class="text-warning"><i class="fas fa-arrow-up me-2"></i>{{ __('Loans Out') }} ({{ count($loansOut ?? []) }})</h4>
        @if(empty($loansOut))
        <p class="text-muted">{{ __('No loans out recorded.') }}</p>
        @else
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark"><tr><th>{{ __('Object') }}</th><th>{{ __('Borrower') }}</th><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach($loansOut as $l)
                <tr>
                    <td>@if($l->slug)<a href="/{{ $l->slug }}">{{ $l->title ?? 'Untitled' }}</a>@else - @endif</td>
                    <td>{{ $l->borrower_name ?? $l->borrower ?? '-' }}</td>
                    <td>{{ $l->loan_start_date ?? $l->start_date ?? '-' }}</td>
                    <td>{{ $l->loan_end_date ?? $l->end_date ?? '-' }}</td>
                    <td><span class="badge bg-warning">{{ $l->status ?? 'Active' }}</span></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endsection
