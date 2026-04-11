@extends('theme::layouts.1col')
@section('title', 'E-Commerce Settings')
@section('body-class', 'admin settings')

@section('content')
<h1><i class="fas fa-store me-2"></i>E-Commerce Settings</h1>

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
      <i class="fas fa-cog me-1"></i>General
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? '') === 'payment' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#payment" type="button">
      <i class="fas fa-credit-card me-1"></i>Payment
    </button>
  </li>
  <li class="nav-item">
    <button class="nav-link {{ ($activeTab ?? '') === 'pricing' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#pricing" type="button">
      <i class="fas fa-tags me-1"></i>Pricing
    </button>
  </li>
</ul>

<div class="tab-content">

  {{-- General Settings Tab --}}
  <div class="tab-pane fade {{ ($activeTab ?? 'general') === 'general' ? 'show active' : '' }}" id="general">
    <form method="post" action="{{ route('cart.admin.settings') }}">
      @csrf
      <input type="hidden" name="action_type" value="save_settings">

      <div class="card shadow-sm">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
          <i class="fas fa-sliders-h me-2"></i>General Settings
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
                    <strong>Enable E-Commerce Mode</strong>
                  </label>
                </div>
                <small class="text-muted">When disabled, cart will use standard Request to Publish workflow.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Currency</label>
                <select name="currency" class="form-select">
                  @foreach(['ZAR' => 'ZAR - South African Rand', 'USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'GBP' => 'GBP - British Pound'] as $code => $label)
                    <option value="{{ $code }}" {{ ($settings->currency ?? 'ZAR') === $code ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">VAT Rate (%)</label>
                <input type="number" name="vat_rate" class="form-control" step="0.01" min="0" max="50"
                       value="{{ $settings->vat_rate ?? 15.00 }}">
              </div>

              <div class="mb-3">
                <label class="form-label">VAT Number</label>
                <input type="text" name="vat_number" class="form-control"
                       value="{{ e($settings->vat_number ?? '') }}">
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Admin Notification Email</label>
                <input type="email" name="admin_notification_email" class="form-control"
                       value="{{ e($settings->admin_notification_email ?? '') }}"
                       placeholder="orders@example.com">
                <small class="text-muted">Receive order notifications at this email.</small>
              </div>

              <div class="mb-3">
                <label class="form-label">Terms and Conditions</label>
                <textarea name="terms_conditions" class="form-control" rows="6">{{ e($settings->terms_conditions ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>Save Settings
          </button>
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
          <i class="fas fa-credit-card me-2"></i>PayFast Configuration
        </div>
        <div class="card-body">

          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            PayFast is a South African payment gateway. Sign up at
            <a href="https://www.payfast.co.za" target="_blank">www.payfast.co.za</a>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Merchant ID</label>
                <input type="text" name="payfast_merchant_id" class="form-control"
                       value="{{ e($settings->payfast_merchant_id ?? '') }}">
              </div>

              <div class="mb-3">
                <label class="form-label">Merchant Key</label>
                <input type="text" name="payfast_merchant_key" class="form-control"
                       value="{{ e($settings->payfast_merchant_key ?? '') }}">
              </div>

              <div class="mb-3">
                <label class="form-label">Passphrase</label>
                <input type="password" name="payfast_passphrase" class="form-control"
                       value="{{ e($settings->payfast_passphrase ?? '') }}">
                <small class="text-muted">Optional but recommended for security.</small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="mb-3">
                <div class="form-check form-switch">
                  <input type="hidden" name="payfast_sandbox" value="0">
                  <input type="checkbox" class="form-check-input" id="payfast_sandbox" name="payfast_sandbox" value="1"
                         {{ ($settings->payfast_sandbox ?? 1) ? 'checked' : '' }}>
                  <label class="form-check-label" for="payfast_sandbox">
                    <strong>Sandbox Mode (Testing)</strong>
                  </label>
                </div>
                <small class="text-muted">Enable for testing. Disable for live payments.</small>
              </div>

              <div class="alert alert-warning mt-4">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>ITN URL</strong><br>
                Configure this URL in your PayFast dashboard:<br>
                <code>{{ config('app.url') }}/cart/payment/notify</code>
              </div>
            </div>
          </div>

        </div>
        <div class="card-footer">
          <button type="submit" class="btn btn-success">
            <i class="fas fa-save me-2"></i>Save Payment Settings
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
          <i class="fas fa-tags me-2"></i>Product Pricing
        </div>
        <div class="card-body">

          <p class="text-muted mb-4">Set prices for each product type. Prices include VAT.</p>

          <div class="table-responsive">
            <table class="table table-hover">
              <thead class="table-light">
                <tr>
                  <th>Product Type</th>
                  <th>Type</th>
                  <th class="text-center" style="width:100px">Active</th>
                  <th style="width:150px">Price ({{ $settings->currency ?? 'ZAR' }})</th>
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
                        <span class="badge bg-info"><i class="fas fa-download me-1"></i>Digital</span>
                      @else
                        <span class="badge bg-secondary"><i class="fas fa-print me-1"></i>Physical</span>
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
            <i class="fas fa-save me-2"></i>Save Pricing
          </button>
        </div>
      </div>
    </form>
  </div>

</div>

<div class="mt-4">
  <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-2"></i>Back to Admin
  </a>
  <a href="{{ route('cart.admin.orders') }}" class="btn btn-outline-primary ms-2">
    <i class="fas fa-list me-2"></i>View Orders
  </a>
</div>
@endsection
