{{--
  PayFast return / cancel page.
  Receives: $transaction, $success (bool), $cancelled (bool, optional).
--}}
@extends('theme::layouts.1col')

@section('title', $success ? 'Payment received' : ($cancelled ?? false ? 'Payment cancelled' : 'Payment status'))
@section('body-class', 'marketplace payment-return')

@section('content')

  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body p-4 text-center">
          @if($success)
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h1 class="h3">{{ __('Payment received') }}</h1>
            <p class="text-muted">
              Thank you. Your payment has been confirmed.
            </p>
          @elseif($cancelled ?? false)
            <i class="fas fa-times-circle fa-4x text-warning mb-3"></i>
            <h1 class="h3">{{ __('Payment cancelled') }}</h1>
            <p class="text-muted">
              You cancelled the payment. The listing has been kept available — you can retry whenever you're ready.
            </p>
          @else
            <i class="fas fa-clock fa-4x text-info mb-3"></i>
            <h1 class="h3">{{ __('Awaiting confirmation') }}</h1>
            <p class="text-muted">
              Your payment is being processed. PayFast will confirm via webhook shortly &mdash; you'll see the status update on your purchases page.
            </p>
          @endif

          @if($transaction)
            <dl class="row text-start small mt-4 mb-3">
              <dt class="col-sm-5 text-muted">Transaction</dt>
              <dd class="col-sm-7"><code>{{ $transaction->transaction_number }}</code></dd>

              <dt class="col-sm-5 text-muted">Amount</dt>
              <dd class="col-sm-7">{{ $transaction->currency ?? 'ZAR' }} {{ number_format((float) $transaction->grand_total, 2) }}</dd>

              <dt class="col-sm-5 text-muted">Status</dt>
              <dd class="col-sm-7">
                <span class="badge bg-{{ $transaction->payment_status === 'paid' ? 'success' : ($transaction->payment_status === 'cancelled' ? 'warning text-dark' : 'secondary') }}">
                  {{ $transaction->payment_status }}
                </span>
              </dd>
            </dl>
          @endif

          <div class="d-flex gap-2 justify-content-center mt-3">
            <a href="{{ route('ahgmarketplace.my-purchases') }}" class="btn btn-primary">
              <i class="fas fa-receipt me-1"></i> My purchases
            </a>
            <a href="{{ route('ahgmarketplace.browse') }}" class="btn btn-outline-secondary">
              <i class="fas fa-shopping-bag me-1"></i> Continue browsing
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>

@endsection
