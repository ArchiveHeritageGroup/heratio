@extends('theme::layouts.1col')
@section('title', 'Admin Orders')
@section('body-class', 'admin orders')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1><i class="fas fa-receipt me-2"></i>{{ __('Orders') }}</h1>
  <a href="{{ route('cart.admin.settings') }}" class="btn atom-btn-white"><i class="fas fa-cog me-1"></i>{{ __('E-Commerce Settings') }}</a>
</div>

<div class="row g-3 mb-4">
  @foreach(['total' => ['Total', 'primary'], 'pending' => ['Pending', 'warning'], 'paid' => ['Paid', 'success'], 'completed' => ['Completed', 'info'], 'cancelled' => ['Cancelled', 'danger']] as $key => [$label, $color])
    <div class="col">
      <div class="card text-center">
        <div class="card-body py-2">
          <div class="fs-4 fw-bold text-{{ $color }}">{{ $stats[$key] ?? 0 }}</div>
          <div class="small text-muted">{{ $label }}</div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between" style="background:var(--ahg-primary);color:#fff">
    <span>{{ __('All Orders') }}</span>
    <div>
      @foreach(['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $val => $label)
        <a href="{{ route('cart.admin.orders', ['status' => $val]) }}" class="btn btn-sm {{ $filterStatus === $val ? 'atom-btn-white' : 'atom-btn-white' }}">{{ $label }}</a>
      @endforeach
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-bordered table-striped mb-0">
      <thead>
      <tbody>
        @forelse($results as $order)
          <tr>
            <td><a href="{{ route('cart.order-confirmation', $order->id) }}"><code>{{ $order->order_number }}</code></a></td>
            <td>{{ $order->customer_name }}<br><small class="text-muted">{{ $order->customer_email }}</small></td>
            <td>{{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</td>
            <td>
              @php $badge = match($order->status) { 'paid' => 'bg-success', 'completed' => 'bg-info', 'cancelled' => 'bg-danger', default => 'bg-warning' }; @endphp
              <span class="badge {{ $badge }}">{{ ucfirst($order->status) }}</span>
            </td>
            <td>{{ $order->currency }} {{ number_format($order->total, 2) }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-muted text-center">No orders found</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
