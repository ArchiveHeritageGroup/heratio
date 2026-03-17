@extends('theme::layouts.1col')
@section('title', 'Order ' . $order->order_number)
@section('body-class', 'cart order-confirmation')

@section('content')
<h1><i class="fas fa-receipt me-2"></i>Order {{ $order->order_number }}</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="row">
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header">Order Items</div>
      <div class="card-body p-0">
        <table class="table table-bordered mb-0">
          <thead><tr><th>Item</th><th>Product</th><th>Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            @foreach($items as $item)
              <tr>
                <td><a href="{{ url('/' . $item->slug) }}">{{ Str::limit($item->archival_description, 60) }}</a></td>
                <td>{{ $item->product_name ?? '' }}</td>
                <td>{{ $item->quantity }}</td>
                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                <td class="text-end">{{ number_format($item->line_total, 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card mb-4">
      <div class="card-header">Order Details</div>
      <div class="card-body">
        <p><strong>Status:</strong>
          @php $badge = match($order->status) { 'paid' => 'bg-success', 'completed' => 'bg-info', 'cancelled' => 'bg-danger', default => 'bg-warning' }; @endphp
          <span class="badge {{ $badge }}">{{ ucfirst($order->status) }}</span>
        </p>
        <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</p>
        <p><strong>Customer:</strong> {{ $order->customer_name }}</p>
        <p><strong>Email:</strong> {{ $order->customer_email }}</p>
        <hr>
        <div class="d-flex justify-content-between"><span>Subtotal</span><span>{{ number_format($order->subtotal, 2) }}</span></div>
        <div class="d-flex justify-content-between text-muted"><span>VAT</span><span>{{ number_format($order->vat_amount, 2) }}</span></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>{{ $order->currency }} {{ number_format($order->total, 2) }}</span></div>
      </div>
    </div>
  </div>
</div>
@endsection
