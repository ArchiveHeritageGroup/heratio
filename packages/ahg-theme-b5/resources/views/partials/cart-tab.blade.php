{{-- Floating Cart Tab — left edge, only visible when cart has items --}}
@php
  $cartCount = 0;
  try {
    $cartUserId = auth()->id();
    $cartSessionId = session()->getId();
    $cartCount = \Illuminate\Support\Facades\DB::table('cart')
      ->when($cartUserId, fn ($q) => $q->where('user_id', $cartUserId))
      ->when(!$cartUserId, fn ($q) => $q->where('session_id', $cartSessionId))
      ->whereNull('completed_at')
      ->count();
  } catch (\Exception $e) {}
@endphp

@if($cartCount > 0)
<a href="{{ route('cart.browse') }}" id="cart-tab-btn" title="View Cart ({{ $cartCount }} {{ $cartCount === 1 ? 'item' : 'items' }})">
  <i class="fas fa-shopping-cart me-1"></i>Cart
  <span class="cart-tab-badge">{{ $cartCount }}</span>
</a>
<style>
  #cart-tab-btn {
    position: fixed;
    left: 0;
    top: 30%;
    z-index: 1050;
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: translateY(-50%) rotate(180deg);
    background: #0d6efd;
    color: #fff;
    border: none;
    border-radius: 0 6px 6px 0;
    padding: 12px 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 2px 0 8px rgba(0,0,0,0.15);
    transition: background 0.2s, padding 0.2s;
    letter-spacing: 0.5px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  #cart-tab-btn:hover {
    background: #0b5ed7;
    color: #fff;
    padding: 14px 10px;
    text-decoration: none;
  }
  .cart-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dc3545;
    color: #fff;
    border-radius: 50%;
    min-width: 18px;
    height: 18px;
    font-size: 0.65rem;
    font-weight: 700;
    line-height: 1;
    padding: 0 4px;
  }
  @media print { #cart-tab-btn { display: none !important; } }
</style>
@endif
