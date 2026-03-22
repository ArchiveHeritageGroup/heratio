@extends('theme::layouts.1col')
@section('title', 'Payment Processed')
@section('body-class', 'success')
@section('content')
  <div class="card"><div class="card-body text-center py-5">
    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
    <h3>Payment Processed</h3><p class="text-muted">Your payment has been processed successfully.</p>
    <a href="{{ url()->previous() }}" class="btn atom-btn-white mt-3"><i class="fas fa-arrow-left me-1"></i> Back to Cart</a>
  </div></div>
@endsection
