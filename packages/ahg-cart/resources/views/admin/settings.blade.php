@extends('theme::layouts.1col')
@section('title', 'E-Commerce Settings')
@section('body-class', 'admin settings')

@section('content')
<h1><i class="fas fa-shopping-bag me-2"></i>E-Commerce Settings</h1>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<form method="post" action="{{ route('cart.admin.settings') }}">
  @csrf
  <div class="row">
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header">General</div>
        <div class="card-body">
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="is_enabled" value="0">
            <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled" {{ ($settings->is_enabled ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_enabled">Enable e-commerce</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Currency</label>
            <input type="text" name="currency" class="form-control" value="{{ e($settings->currency ?? 'ZAR') }}" maxlength="3">
          </div>
          <div class="mb-3">
            <label class="form-label">VAT rate (%)</label>
            <input type="number" name="vat_rate" class="form-control" value="{{ $settings->vat_rate ?? 15 }}" step="0.01">
          </div>
          <div class="mb-3">
            <label class="form-label">VAT number</label>
            <input type="text" name="vat_number" class="form-control" value="{{ e($settings->vat_number ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Admin notification email</label>
            <input type="email" name="admin_notification_email" class="form-control" value="{{ e($settings->admin_notification_email ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Terms & conditions</label>
            <textarea name="terms_conditions" class="form-control" rows="4">{{ e($settings->terms_conditions ?? '') }}</textarea>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card mb-4">
        <div class="card-header">PayFast Gateway</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Merchant ID</label>
            <input type="text" name="payfast_merchant_id" class="form-control" value="{{ e($settings->payfast_merchant_id ?? '') }}">
          </div>
          <div class="mb-3">
            <label class="form-label">Merchant Key</label>
            <input type="password" name="payfast_merchant_key" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="mb-3">
            <label class="form-label">Passphrase</label>
            <input type="password" name="payfast_passphrase" class="form-control" placeholder="Leave blank to keep current">
          </div>
          <div class="form-check form-switch mb-3">
            <input type="hidden" name="payfast_sandbox" value="0">
            <input class="form-check-input" type="checkbox" name="payfast_sandbox" value="1" id="sandbox" {{ ($settings->payfast_sandbox ?? 1) ? 'checked' : '' }}>
            <label class="form-check-label" for="sandbox">Sandbox mode</label>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Product Pricing</div>
        <div class="card-body p-0">
          <table class="table table-bordered table-sm mb-0">
            <thead><tr><th>Product</th><th>Price</th><th>Status</th></tr></thead>
            <tbody>
              @foreach($productTypes as $pt)
                @php $price = $pricing->firstWhere('product_type_id', $pt->id); @endphp
                <tr>
                  <td>{{ $pt->name }}</td>
                  <td>{{ $price ? number_format($price->price, 2) : 'N/A' }}</td>
                  <td>
                    @if($pt->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
  <a href="{{ route('cart.admin.orders') }}" class="btn btn-outline-secondary ms-2">Back to Orders</a>
</form>
@endsection
