@extends('theme::layouts.1col')
@section('title', 'Cart')
@section('body-class', 'cart')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-shopping-cart me-2"></i>Cart <span class="badge bg-primary">{{ $items->count() }}</span></h1>
  <div>
    @if($items->isNotEmpty())
      <form method="post" action="{{ route('cart.clear') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn atom-btn-outline-danger btn-sm" onclick="return confirm('Clear all items?')"><i class="fas fa-trash me-1"></i>Clear cart</button>
      </form>
    @endif
  </div>
</div>

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif

@if($items->isEmpty())
  <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Your cart is empty. Browse the collection and add items.</div>
@else
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
@endsection
