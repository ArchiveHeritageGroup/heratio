@extends('theme::layouts.1col')
@section('title', __('Order :number', ['number' => $order->order_number]))
@section('body-class', 'cart order-confirmation')

@section('content')
<h1><i class="fas fa-receipt me-2"></i>{{ __('Order :number', ['number' => $order->order_number]) }}</h1>
<div class="row">
  <div class="col-md-8">
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Order Items') }}</div>
      <div class="card-body p-0">
        <table class="table table-bordered mb-0">
          <thead>
          <tbody>
            @foreach($items as $item)
              <tr>
                <td><a href="{{ url('/' . $item->slug) }}">{{ Str::limit($item->archival_description, 60) }}</a>
                  @if(! empty($item->download_url) && (empty($item->download_expires_at) || \Illuminate\Support\Carbon::parse($item->download_expires_at)->isFuture()))
                    {{-- heratio#1775: released on payment; the link only works for the buyer. --}}
                    <div><a href="{{ $item->download_url }}" class="btn btn-sm atom-btn-white mt-1"><i class="fas fa-download me-1"></i>{{ __('Download') }}</a>
                      <span class="text-muted small">{{ __('until :date', ['date' => \Illuminate\Support\Carbon::parse($item->download_expires_at)->format('Y-m-d')]) }}</span></div>
                  @endif
                </td>
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
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Order Details') }}</div>
      <div class="card-body">
        <p><strong>{{ __('Status:') }}</strong>
          @php $badge = match($order->status) { 'paid' => 'bg-success', 'completed' => 'bg-info', 'cancelled' => 'bg-danger', default => 'bg-warning' }; @endphp
          <span class="badge {{ $badge }}">{{ ucfirst($order->status) }}</span>
        </p>
        <p><strong>{{ __('Date:') }}</strong> {{ \Carbon\Carbon::parse($order->created_at)->format('Y-m-d H:i') }}</p>
        <p><strong>{{ __('Customer:') }}</strong> {{ $order->customer_name }}</p>
        <p><strong>{{ __('Email:') }}</strong> {{ $order->customer_email }}</p>
        <hr>
        <div class="d-flex justify-content-between"><span>{{ __('Subtotal') }}</span><span>{{ number_format($order->subtotal, 2) }}</span></div>
        <div class="d-flex justify-content-between text-muted"><span>{{ __('VAT') }}</span><span>{{ number_format($order->vat_amount, 2) }}</span></div>
        <hr>
        <div class="d-flex justify-content-between fw-bold"><span>{{ __('Total') }}</span><span>{{ $order->currency }} {{ number_format($order->total, 2) }}</span></div>
        @if($order->status === 'pending' && (float) $order->total > 0 && (int) $order->user_id === (int) auth()->id())
          {{-- heratio#1774: pay online; the records are released once the payment is confirmed. --}}
          <a href="{{ route('cart.payment', $order->id) }}" class="btn atom-btn-outline-success w-100 mt-3"><i class="fas fa-credit-card me-1"></i>{{ __('Pay now') }}</a>
        @endif
        @if(! empty($order->access_request_id))
          <a href="{{ route('accessRequest.view', $order->access_request_id) }}" class="btn atom-btn-white w-100 mt-2"><i class="fas fa-key me-1"></i>{{ __('View the request') }}</a>
          @if($order->status === 'paid')
            <p class="small text-muted mt-2 mb-0">{{ __('Paid. Your files are on the request page.') }}</p>
          @endif
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
