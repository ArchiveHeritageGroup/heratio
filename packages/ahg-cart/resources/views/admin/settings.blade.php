@extends('theme::layouts.1col')
@section('title', 'E-Commerce Settings')
@section('body-class', 'admin settings')

@section('content')
<h1><i class="fas fa-store me-2"></i>{{ __('E-Commerce Settings') }}</h1>

@if(session('success'))
  <div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

@if(session('error'))
  <div class="alert alert-danger alert-dismissible fade show">
    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif

<ul class="nav nav-tabs mb-4" id="ecommerceTabs" role="tablist">
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? 'general') === 'general' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#general" type="button">
      <i class="fas fa-cog me-1"></i>{{ __('General') }}
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? '') === 'payment' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#payment" type="button">
      <i class="fas fa-credit-card me-1"></i>{{ __('Payment') }}
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? '') === 'pricing' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pricing" type="button">
      <i class="fas fa-tags me-1"></i>{{ __('Pricing') }}
    </button>
  </li>
  @if($hasTiers ?? false)
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? '') === 'tiers' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tiers" type="button">
      <i class="fas fa-id-card me-1"></i>{{ __('User types and subscriptions') }}
    </button>
  </li>
  @endif
</ul>

<div class="tab-content">

  {{-- General Settings Tab --}}
  <div class="tab-pane fade {{ ($activeTab ?? 'general') === 'general' ? 'show active' : '' }}" id="general">
    <form method="post" action="{{ route('cart.admin.settings') }}">
      @csrf
      <input type="hidden" name="action_type" value="save_settings">

      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <i class="fas fa-sliders-h me-2"></i>{{ __('General Settings') }}
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input type="hidden" name="is_enabled" value="0">
                  <input type="checkbox" class="form-check-input" id="is_enabled" name="is_enabled" value="1"
                         {{ ($settings->is_enabled ?? 0) ? 'checked' : '' }}>
                  <label class="form-check-label" for="is_enabled">
                    <strong>{{ __('Enable E-Commerce Mode') }}</strong>
                  </label>
                </div>
                <small class="text-muted">{{ __('When disabled, cart will use standard Request to Publish workflow.') }}</small>
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('Currency') }}</label>
                <select name="currency" class="form-select">
                  @foreach(['ZAR' => 'ZAR - South African Rand', 'USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound'] as $code => $label)
                    <option value="{{ $code }}" {{ ($settings->currency ?? 'ZAR') === $code ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('VAT Rate (%)') }}</label>
                <input type="number" name="vat_rate" class="form-control" step="0.01" min="0" max="50"
                       value="{{ $settings->vat_rate ?? 15.00 }}">
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('VAT Number') }}</label>
                <input type="text" name="vat_number" class="form-control"
                       value="{{ e($settings->vat_number ?? '') }}">
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('Admin Notification Email') }}</label>
                <input type="email" name="admin_notification_email" class="form-control"
                       value="{{ e($settings->admin_notification_email ?? '') }}"
                       placeholder="{{ __('orders@example.com') }}">
                <small class="text-muted">{{ __('Receive order notifications at this email.') }}</small>
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('Terms and Conditions') }}</label>
                <textarea name="terms_conditions" class="form-control" rows="6">{{ e($settings->terms_conditions ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer d-flex justify-content-between align-items-center">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>{{ __('Save Settings') }}
          </button>
          @unless($settings->is_enabled ?? 0)
            <button type="button" class="btn btn-outline-warning"
                    data-bs-toggle="modal" data-bs-target="#dummySaleModal"
                    data-dummy-title="{{ __('Sample marketplace listing') }}"
                    data-dummy-price="6500.00"
                    data-dummy-currency="{{ $settings->currency ?? 'ZAR' }}">
              <i class="fas fa-flask me-1"></i> {{ __('Preview dummy sale') }}
            </button>
          @endunless
        </div>
      </div>
    </form>
  </div>

  {{-- Payment Tab --}}
  <div class="tab-pane fade {{ ($activeTab ?? '') === 'payment' ? 'show active' : '' }}" id="payment">
    <form method="post" action="{{ route('cart.admin.settings') }}">
      @csrf
      <input type="hidden" name="action_type" value="save_settings">

      {{-- Preserve general settings so they are not blanked on payment-tab save --}}
      <input type="hidden" name="is_enabled" value="{{ ($settings->is_enabled ?? 0) ? '1' : '0' }}">
      <input type="hidden" name="currency" value="{{ e($settings->currency ?? 'ZAR') }}">
      <input type="hidden" name="vat_rate" value="{{ $settings->vat_rate ?? 15.00 }}">
      <input type="hidden" name="vat_number" value="{{ e($settings->vat_number ?? '') }}">
      <input type="hidden" name="admin_notification_email" value="{{ e($settings->admin_notification_email ?? '') }}">
      <input type="hidden" name="terms_conditions" value="{{ e($settings->terms_conditions ?? '') }}">

      <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
          <i class="fas fa-credit-card me-2"></i>{{ __('PayFast Configuration') }}
        </div>
        <div class="card-body">

          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            {{ __('PayFast is a South African payment gateway. Sign up at') }}
            <a href="https://{{ __('www.payfast.co.za') }}" target="_blank">{{ __('www.payfast.co.za') }}</a>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">{{ __('Merchant ID') }}</label>
                <input type="text" name="payfast_merchant_id" class="form-control"
                       value="{{ e($settings->payfast_merchant_id ?? '') }}">
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('Merchant Key') }}</label>
                <input type="text" name="payfast_merchant_key" class="form-control"
                       value="{{ e($settings->payfast_merchant_key ?? '') }}">
              </div>

              <div class="mb-3">
                <label class="form-label">{{ __('Passphrase') }}</label>
                <input type="password" name="payfast_passphrase" class="form-control"
                       value="{{ e($settings->payfast_passphrase ?? '') }}">
                <small class="text-muted">{{ __('Optional but recommended for security.') }}</small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input type="hidden" name="payfast_sandbox" value="0">
                  <input type="checkbox" class="form-check-input" id="payfast_sandbox" name="payfast_sandbox" value="1"
                         {{ ($settings->payfast_sandbox ?? 1) ? 'checked' : '' }}>
                  <label class="form-check-label" for="payfast_sandbox">
                    <strong>{{ __('Sandbox Mode (Testing)') }}</strong>
                  </label>
                </div>
                <small class="text-muted">{{ __('Enable for testing. Disable for live payments.') }}</small>
              </div>

              <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>{{ __('ITN URL') }}</strong><br>
                {{ __('Configure this URL in your PayFast dashboard:') }}<br>
                <code>{{ config('app.url') }}/cart/payment/notify</code>
              </div>
            </div>
          </div>

        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-2"></i>{{ __('Save Payment Settings') }}
          </button>
        </div>
      </div>
    </form>
  </div>

  {{-- Pricing Tab --}}
  <div class="tab-pane fade {{ ($activeTab ?? '') === 'pricing' ? 'show active' : '' }}" id="pricing">
    <form method="post" action="{{ route('cart.admin.settings') }}">
      @csrf
      <input type="hidden" name="action_type" value="save_pricing">

      <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
          <i class="fas fa-tags me-2"></i>{{ __('Product Pricing') }}
        </div>
        <div class="card-body">

          <p class="text-muted mb-4">{{ __('Set prices for each product type. Prices include VAT.') }}</p>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>{{ __('Product Type') }}</th>
                  <th>{{ __('Type') }}</th>
                  <th class="text-center" style="width:100px">{{ __('Active') }}</th>
                  <th style="width:150px">{{ __('Price (:currency)', ['currency' => $settings->currency ?? 'ZAR']) }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($productTypes as $pt)
                  @php $currentPrice = $pricing->firstWhere('product_type_id', $pt->id); @endphp
                  <tr>
                    <td>
                      <strong>{{ e($pt->name) }}</strong>
                      @if(!empty($pt->description))
                        <br><small class="text-muted">{{ e($pt->description) }}</small>
                      @endif
                    </td>
                    <td>
                      @if($pt->is_digital ?? false)
                        <span class="badge bg-info"><i class="fas fa-download me-1"></i>{{ __('Digital') }}</span>
                      @else
                        <span class="badge bg-secondary"><i class="fas fa-print me-1"></i>{{ __('Physical') }}</span>
                      @endif
                    </td>
                    <td class="text-center">
                      <input type="checkbox" class="form-check-input" name="price_active[{{ $pt->id }}]" value="1"
                             {{ (!$currentPrice || ($currentPrice->is_active ?? 1)) ? 'checked' : '' }}>
                    </td>
                    <td>
                      <input type="number" name="price[{{ $pt->id }}]" class="form-control" step="0.01" min="0"
                             value="{{ $currentPrice->price ?? 0 }}">
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-warning">
            <i class="fas fa-save me-2"></i>{{ __('Save Pricing') }}
          </button>
        </div>
      </div>
    </form>
  </div>

  @if($hasTiers ?? false)
  {{-- heratio#1770: prices by user type and subscription tiers. --}}
  <div class="tab-pane fade {{ ($activeTab ?? '') === 'tiers' ? 'show active' : '' }}" id="tiers">
    <div class="card shadow-sm mb-4">
      <div class="card-header"><i class="fas fa-id-badge me-2"></i>{{ __('Prices by user type') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('Each user type can have a discount or free access to reproductions. Set them under Research > Researcher types.') }}
          @if(\Illuminate\Support\Facades\Route::has('research.adminTypes'))<a href="{{ route('research.adminTypes') }}">{{ __('Edit user types') }}</a>@endif</p>
        <table class="table table-sm mb-0">
          <thead><tr><th>{{ __('User type') }}</th><th>{{ __('Discount') }}</th><th>{{ __('Free access') }}</th><th>{{ __('Originals online') }}</th><th>{{ __('Verifying email domains') }}</th></tr></thead>
          <tbody>
            @foreach($userTypes as $ut)
              <tr><td>{{ $ut->name }}</td><td>{{ (float) ($ut->discount_percent ?? 0) > 0 ? rtrim(rtrim(number_format((float) $ut->discount_percent, 2), '0'), '.').'%' : '-' }}</td>
                <td>{{ ! empty($ut->free_access) ? __('Yes') : '-' }}</td><td>{{ ! empty($ut->can_remote_access) ? __('Yes') : '-' }}</td><td class="small">{{ $ut->email_domains ?? '' }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header"><i class="fas fa-id-card me-2"></i>{{ __('Subscription tiers') }}</div>
      <div class="card-body">
        <p class="text-muted small">{{ __('A subscription treats the subscriber as the chosen user type for its period: that type\'s prices and rights apply. Users take one out on the Subscriptions page.') }}
          <a href="{{ route('cart.subscriptions') }}">{{ __('Subscriptions page') }}</a></p>
        @foreach($tiers->concat([null]) as $t)
          <form method="post" action="{{ route('cart.admin.settings') }}" class="row g-2 align-items-end border-bottom pb-3 mb-3">
            @csrf
            <input type="hidden" name="action_type" value="save_tier">
            <input type="hidden" name="tier_id" value="{{ $t->id ?? '' }}">
            <div class="col-md-3"><label class="form-label small">{{ $t ? __('Tier') : __('New tier') }}</label><input name="tier_name" class="form-control form-control-sm" value="{{ $t->name ?? '' }}" required></div>
            <div class="col-md-2"><label class="form-label small">{{ __('User type') }}</label>
              <select name="tier_type_id" class="form-select form-select-sm">@foreach($userTypes as $ut)<option value="{{ $ut->id }}" @selected(($t->researcher_type_id ?? null) == $ut->id)>{{ $ut->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">{{ __('Price') }}</label><input type="number" step="0.01" min="0" name="tier_price" class="form-control form-control-sm" value="{{ $t->price ?? '0' }}" required></div>
            <div class="col-md-1"><label class="form-label small">{{ __('Months') }}</label><input type="number" min="1" max="120" name="tier_months" class="form-control form-control-sm" value="{{ $t->period_months ?? 12 }}" required></div>
            <div class="col-md-1"><div class="form-check"><input type="checkbox" class="form-check-input" name="tier_active" value="1" id="tier-act-{{ $t->id ?? 'new' }}" @checked($t ? $t->is_active : true)><label class="form-check-label small" for="tier-act-{{ $t->id ?? 'new' }}">{{ __('Active') }}</label></div></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary">{{ $t ? __('Save') : __('Add tier') }}</button>
              @if($t)<span class="small text-muted ms-1">{{ __(':count active', ['count' => (int) $t->active_count]) }}</span>@endif</div>
            <div class="col-12"><input name="tier_description" class="form-control form-control-sm" value="{{ $t->description ?? '' }}" placeholder="{{ __('What the tier gives (shown to users)') }}" aria-label="{{ __('Description') }}"></div>
          </form>
        @endforeach
      </div>
    </div>
  </div>
  @endif
</div>

<div class="mt-4">
  <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>{{ __('Back to Admin') }}
  </a>
  <a href="{{ route('cart.admin.orders') }}" class="btn btn-outline-primary ms-2">
    <i class="fas fa-list me-2"></i>{{ __('View Orders') }}
  </a>
</div>

@include('ahg-cart::_dummy-sale-modal')

@endsection
