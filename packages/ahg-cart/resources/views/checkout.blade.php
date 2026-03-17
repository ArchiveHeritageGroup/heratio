@extends('theme::layouts.1col')
@section('title', 'Checkout')
@section('body-class', 'cart checkout')

@section('content')
<h1><i class="fas fa-credit-card me-2"></i>Checkout</h1>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="post" action="{{ route('cart.checkout') }}">
  @csrf
  <div class="row">
    <div class="col-md-8">
      @if($isEcommerce)
        {{-- E-Commerce checkout form --}}
        <div class="card mb-4">
          <div class="card-header"><i class="fas fa-user me-2"></i>Billing Information</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Name *</label>
                <input type="text" name="customer_name" class="form-control" required value="{{ old('customer_name') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="customer_email" class="form-control" required value="{{ old('customer_email') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="customer_phone" class="form-control" value="{{ old('customer_phone') }}">
              </div>
              <div class="col-md-12 mb-3">
                <label class="form-label">Billing address</label>
                <textarea name="billing_address" class="form-control" rows="2">{{ old('billing_address') }}</textarea>
              </div>
              <div class="col-md-12 mb-3">
                <label class="form-label">Order notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
              </div>
            </div>
          </div>
        </div>
      @else
        {{-- Standard (Request to Publish) form --}}
        <div class="card mb-4">
          <div class="card-header"><i class="fas fa-paper-plane me-2"></i>Request to Publish</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First name *</label>
                <input type="text" name="rtp_name" class="form-control" required value="{{ old('rtp_name') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Surname *</label>
                <input type="text" name="rtp_surname" class="form-control" required value="{{ old('rtp_surname') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="rtp_email" class="form-control" required value="{{ old('rtp_email') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="tel" name="rtp_phone" class="form-control" value="{{ old('rtp_phone') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Institution</label>
                <input type="text" name="rtp_institution" class="form-control" value="{{ old('rtp_institution') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Planned use</label>
                <select name="rtp_planned_use" class="form-select">
                  <option value="">Select...</option>
                  <option value="Publication">Publication</option>
                  <option value="Research">Research</option>
                  <option value="Exhibition">Exhibition</option>
                  <option value="Personal">Personal</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-12 mb-3">
                <label class="form-label">Motivation</label>
                <textarea name="rtp_motivation" class="form-control" rows="3">{{ old('rtp_motivation') }}</textarea>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Need image by</label>
                <input type="date" name="rtp_need_image_by" class="form-control" value="{{ old('rtp_need_image_by') }}">
              </div>
            </div>
          </div>
        </div>
      @endif
    </div>

    <div class="col-md-4">
      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-list me-2"></i>Order Summary</div>
        <div class="card-body">
          <ul class="list-unstyled mb-3">
            @foreach($items as $item)
              <li class="d-flex justify-content-between mb-2">
                <span class="small">{{ Str::limit($item->archival_description, 40) }}</span>
                @if($isEcommerce && $totals)
                  <span class="fw-bold">{{ number_format($item->line_total ?? 0, 2) }}</span>
                @endif
              </li>
            @endforeach
          </ul>
          @if($isEcommerce && $totals)
            <hr>
            <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ $totals['currency'] }} {{ number_format($totals['subtotal'], 2) }}</span></div>
            <div class="d-flex justify-content-between text-muted small"><span>VAT ({{ $totals['vat_rate'] }}%)</span><span>{{ number_format($totals['vat_amount'], 2) }}</span></div>
            <hr>
            <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>{{ $totals['currency'] }} {{ number_format($totals['total'], 2) }}</span></div>
          @else
            <p class="text-muted small">{{ $items->count() }} {{ Str::plural('item', $items->count()) }} selected</p>
          @endif
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-check me-1"></i>{{ $isEcommerce ? 'Place Order' : 'Submit Request' }}
          </button>
        </div>
      </div>
      <a href="{{ route('cart.browse') }}" class="btn btn-outline-secondary w-100"><i class="fas fa-arrow-left me-1"></i>Back to cart</a>
    </div>
  </div>
</form>
@endsection
