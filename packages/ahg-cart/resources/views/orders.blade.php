@extends('theme::layouts.1col')
@section('title', 'My Orders')
@section('body-class', 'cart orders')

@section('content')
<h1><i class="fas fa-receipt me-2"></i>My Orders</h1>

@if($orders->isEmpty())
  <div class="alert alert-info">You have no orders yet.</div>
@else
  <div class="table-responsive">
    <table class="table table-bordered table-striped">
      <thead>
      <tbody>
        @foreach($orders as $order)
          <tr>
            <td><code>{{ $order->order_number }}</code></td>
            <td>{{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</td>
            <td>
              @php $badge = match($order->status) { 'paid' => 'bg-success', 'completed' => 'bg-info', 'cancelled' => 'bg-danger', default => 'bg-warning' }; @endphp
              <span class="badge {{ $badge }}">{{ ucfirst($order->status) }}</span>
            </td>
            <td>{{ $order->currency }} {{ number_format($order->total, 2) }}</td>
            <td><a href="{{ route('cart.order-confirmation', $order->id) }}" class="btn btn-sm atom-btn-white">View</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
