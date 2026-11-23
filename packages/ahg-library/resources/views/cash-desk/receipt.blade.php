@extends('theme::layouts.1col')
@section('title', 'Receipt')
@section('content')
<div class="container py-4" style="max-width: 640px">
    <div class="d-flex justify-content-between align-items-center mb-3 d-print-none">
        <a href="{{ route('library.fines.index', $receipt->patron_id) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to fines') }}</a>
        <button class="btn btn-sm atom-btn-white" type="button" onclick="window.print()"><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
    </div>
    <div class="d-print-none">@include('ahg-library::cash-desk._alerts')</div>

    <div class="card">
        <div class="card-body">
            <h1 class="h4">{{ __('Receipt') }} {{ $receipt->receipt_number }}</h1>
            <dl class="row mb-3">
                <dt class="col-5">{{ __('Date') }}</dt><dd class="col-7">{{ $receipt->issued_at }}</dd>
                <dt class="col-5">{{ __('Patron') }}</dt><dd class="col-7">{{ trim($receipt->first_name . ' ' . $receipt->last_name) }} ({{ $receipt->card_number }})</dd>
                <dt class="col-5">{{ __('Payment method') }}</dt><dd class="col-7">{{ $tenders[$receipt->payment_method]['label'] ?? $receipt->payment_method }}</dd>
                @if($receipt->payment_reference)
                    <dt class="col-5">{{ __('Reference') }}</dt><dd class="col-7">{{ $receipt->payment_reference }}</dd>
                @endif
                <dt class="col-5">{{ __('Served by') }}</dt><dd class="col-7">{{ $issuer }}</dd>
            </dl>
            <table class="table table-sm">
                <thead><tr><th scope="col">{{ __('Fine') }}</th><th scope="col" class="text-end">{{ __('Paid') }}</th><th scope="col" class="text-end">{{ __('Balance left') }}</th></tr></thead>
                <tbody>
                    @foreach($receipt->lines as $l)
                        <tr>
                            <td>{{ $l->fine_date }} - {{ $l->description ?: ucfirst($l->fine_type) }}</td>
                            <td class="text-end">{{ number_format((float) $l->amount, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $l->balance_after, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th scope="row">{{ __('Total paid') }}</th><th class="text-end">{{ number_format((float) $receipt->total_amount, 2) }} {{ $receipt->currency }}</th><th></th></tr>
                    @if($receipt->tendered_amount !== null)
                        <tr><td>{{ __('Tendered') }}</td><td class="text-end">{{ number_format((float) $receipt->tendered_amount, 2) }}</td><td></td></tr>
                        <tr><td>{{ __('Change') }}</td><td class="text-end">{{ number_format((float) $receipt->change_given, 2) }}</td><td></td></tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
