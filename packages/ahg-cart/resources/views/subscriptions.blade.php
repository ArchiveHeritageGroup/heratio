{{-- Subscription tiers (heratio#1770). --}}
@extends('theme::layouts.1col')
@section('title', __('Subscriptions'))
@section('body-class', 'cart subscriptions')

@section('content')
<h1><i class="fas fa-id-card me-2"></i>{{ __('Subscriptions') }}</h1>

@if($type)
  <p class="text-muted">
    {{ __('Your user type: :type', ['type' => $type->name]) }}
    @if(! empty($type->free_access))
      - {{ __('reproductions are free for you') }}
    @elseif((float) ($type->discount_percent ?? 0) > 0)
      - {{ __(':pct% off reproductions', ['pct' => rtrim(rtrim(number_format((float) $type->discount_percent, 2), '0'), '.')]) }}
    @endif
  </p>
@endif

@if($tiers->isEmpty())
  <div class="alert alert-info">{{ __('No subscriptions are offered at the moment.') }}</div>
@else
  <div class="row g-3 mb-4">
    @foreach($tiers as $t)
      <div class="col-md-6 col-lg-4">
        <div class="card h-100">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ $t->name }}</div>
          <div class="card-body">
            <p class="fs-4 mb-1">{{ (float) $t->price > 0 ? $currency.' '.number_format((float) $t->price, 2) : __('Free') }}</p>
            <p class="small text-muted">{{ __('For :months months', ['months' => $t->period_months]) }}</p>
            @if($t->description)<p>{{ $t->description }}</p>@endif
            <ul class="small mb-0">
              <li>{{ __('You are treated as: :type', ['type' => $t->type_name]) }}</li>
              @if(! empty($t->free_access))<li>{{ __('Reproductions free of charge') }}</li>
              @elseif((float) $t->discount_percent > 0)<li>{{ __(':pct% off reproductions', ['pct' => rtrim(rtrim(number_format((float) $t->discount_percent, 2), '0'), '.')]) }}</li>@endif
              @if(! empty($t->can_remote_access))<li>{{ __('Originals online without a request') }}</li>@endif
            </ul>
          </div>
          <div class="card-footer">
            @auth
              <form method="post" action="{{ route('cart.subscribe', $t->id) }}">
                @csrf
                <button type="submit" class="btn atom-btn-outline-success w-100"><i class="fas fa-check me-1"></i>{{ __('Subscribe') }}</button>
              </form>
            @else
              <a href="{{ route('login') }}" class="btn atom-btn-white w-100">{{ __('Sign in to subscribe') }}</a>
            @endauth
          </div>
        </div>
      </div>
    @endforeach
  </div>
@endif

@if($mine->isNotEmpty())
  <h2 class="h5">{{ __('Your subscriptions') }}</h2>
  <table class="table table-sm">
    <thead><tr><th>{{ __('Subscription') }}</th><th>{{ __('Status') }}</th><th>{{ __('From') }}</th><th>{{ __('Until') }}</th><th></th></tr></thead>
    <tbody>
      @foreach($mine as $s)
        <tr>
          <td>{{ $s->tier_name }}</td>
          <td>{{ ucfirst($s->status) }}</td>
          <td>{{ $s->starts_at ? \Illuminate\Support\Carbon::parse($s->starts_at)->format('Y-m-d') : '' }}</td>
          <td>{{ $s->ends_at ? \Illuminate\Support\Carbon::parse($s->ends_at)->format('Y-m-d') : '' }}</td>
          <td>@if($s->status === 'pending' && $s->order_id)<a href="{{ route('cart.order-confirmation', $s->order_id) }}" class="btn btn-sm atom-btn-white">{{ __('Pay now') }}</a>@endif</td>
        </tr>
      @endforeach
    </tbody>
  </table>
@endif
@endsection
