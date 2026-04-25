@extends('theme::layouts.1col')
@section('title', 'Cart')
@section('body-class', 'cart')

@section('content')
@php
  $totalCount = $items->count() + (isset($marketplaceCart) ? $marketplaceCart['items']->count() : 0);
  $hasMarketplace = isset($marketplaceCart) && $marketplaceCart['items']->isNotEmpty();
  $hasReproductions = $items->isNotEmpty();
  $ecommerceEnabled = app(\AhgCart\Services\EcommerceService::class)->isEcommerceEnabled();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-shopping-cart me-2"></i>Cart <span class="badge bg-primary">{{ $totalCount }}</span></h1>
  <div>
    @if($totalCount > 0)
      <form method="post" action="{{ route('cart.clear') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger btn-sm" onclick="return confirm('Clear all items?')"><i class="fas fa-trash me-1"></i>Clear cart</button>
      </form>
    @endif
  </div>
</div>

{{-- ============================================================ --}}
{{-- MARKETPLACE PURCHASES                                          --}}
{{-- ============================================================ --}}
@if($hasMarketplace)
  <div class="card mb-4">
    <div class="card-header bg-primary bg-opacity-10 fw-bold">
      <i class="fas fa-store me-1 text-primary"></i> Marketplace purchases ({{ $marketplaceCart['items']->count() }})
    </div>
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:80px;">&nbsp;</th>
            <th>Listing</th>
            <th>Seller</th>
            <th class="text-end">Price</th>
            <th class="text-center" style="width:60px;"></th>
          </tr>
        </thead>
        <tbody>
          @foreach($marketplaceCart['items'] as $m)
            <tr>
              <td>
                @if($m->featured_image_path)
                  <img src="{{ $m->featured_image_path }}" alt="" class="img-fluid rounded" style="max-height:60px;object-fit:cover;">
                @else
                  <div class="bg-light text-muted text-center py-3 rounded" style="height:60px;width:60px;"><i class="fas fa-image"></i></div>
                @endif
              </td>
              <td>
                <a href="{{ url('/marketplace/listing?slug=' . $m->slug) }}" class="text-decoration-none">{{ $m->title }}</a>
                <div class="small text-muted">Added {{ \Carbon\Carbon::parse($m->added_at)->diffForHumans() }}</div>
              </td>
              <td class="small">
                @if($m->seller_name)
                  <a href="{{ url('/marketplace/seller?slug=' . $m->seller_slug) }}" class="text-decoration-none">{{ $m->seller_name }}</a>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td class="text-end fw-semibold">{{ $m->currency ?: 'ZAR' }} {{ number_format((float) $m->price, 2) }}</td>
              <td class="text-center">
                <form method="post" action="{{ route('cart.remove', $m->cart_id) }}" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                    <i class="fas fa-times"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="table-light">
          <tr>
            <th colspan="3" class="text-end">Subtotal</th>
            <th class="text-end">{{ $marketplaceCart['currency'] }} {{ number_format($marketplaceCart['subtotal'], 2) }}</th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center">
      <a href="{{ url('/marketplace/browse') }}" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Continue browsing marketplace
      </a>
      @if($ecommerceEnabled)
        <form method="post" action="{{ route('cart.marketplace-checkout') }}">
          @csrf
          <button type="submit" class="btn btn-success">
            <i class="fas fa-credit-card me-1"></i> Pay {{ $marketplaceCart['currency'] }} {{ number_format($marketplaceCart['subtotal'], 2) }} via PayFast
          </button>
        </form>
      @else
        <form id="demo-checkout-form" method="post" action="{{ route('cart.marketplace-demo-checkout') }}">
          @csrf
          <button type="button" class="btn btn-warning"
                  data-bs-toggle="modal" data-bs-target="#dummySaleModal"
                  data-dummy-title="Marketplace cart ({{ $marketplaceCart['items']->count() }} items)"
                  data-dummy-price="{{ (string) $marketplaceCart['subtotal'] }}"
                  data-dummy-currency="{{ $marketplaceCart['currency'] }}"
                  data-demo-submit="#demo-checkout-form">
            <i class="fas fa-flask me-1"></i> Demo Sale (e-commerce disabled)
          </button>
        </form>
      @endif
    </div>
  </div>
@endif

{{-- ============================================================ --}}
{{-- REPRODUCTION REQUESTS                                          --}}
{{-- ============================================================ --}}
@if(!$hasMarketplace && $items->isEmpty())
  <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Your cart is empty. Browse the collection and add items.</div>
@elseif($hasReproductions)
  <h4 class="mb-2"><i class="fas fa-images me-1 text-secondary"></i> Reproduction requests ({{ $items->count() }})</h4>
@endif
@if($hasReproductions)
  <div class="table-responsive mb-4">
    <table class="table table-bordered table-striped">
      <thead>
        <tr>
          <th>Item</th>
          @if($isEcommerce)<th>Product</th><th>Price</th>@endif
          <th>Date added</th>
          <th style="width:80px">Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach($items as $item)
        <tr>
          <td>
            <a href="{{ url('/' . $item->slug) }}">{{ $item->archival_description }}</a>
          </td>
          @if($isEcommerce)
            <td>
              <select name="product_{{ $item->id }}" class="form-select form-select-sm product-select" data-item-id="{{ $item->id }}">
                <option value="">Select product...</option>
                @foreach($productTypes as $pt)
                  @php $price = $pricing->firstWhere('product_type_id', $pt->id); @endphp
                  <option value="{{ $pt->id }}" data-price="{{ $price->price ?? 0 }}" {{ $item->product_type_id == $pt->id ? 'selected' : '' }}>
                    {{ $pt->name }} {{ $price ? '(' . number_format($price->price, 2) . ')' : '' }}
                  </option>
                @endforeach
              </select>
            </td>
            <td class="item-price text-end">{{ number_format($item->unit_price ?? 0, 2) }}</td>
          @endif
          <td>{{ \Carbon\Carbon::parse($item->created_at)->format('Y-m-d H:i') }}</td>
          <td>
            <form method="post" action="{{ route('cart.remove', $item->id) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm atom-btn-outline-danger" title="Remove"><i class="fas fa-times"></i></button>
            </form>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between">
    <a href="{{ url('/informationobject/browse') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Continue browsing</a>
    <a href="{{ route('cart.checkout') }}" class="btn atom-btn-white"><i class="fas fa-credit-card me-1"></i>Proceed to checkout</a>
  </div>
@endif

@include('ahg-cart::_dummy-sale-modal')

@endsection
{{-- end-of-template --}}
