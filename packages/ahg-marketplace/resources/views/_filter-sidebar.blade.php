{{--
  Partial: filter-sidebar (ported from atom-ahg-plugins/ahgMarketplacePlugin/_filterSidebar.php)

  Variables:
    $facets     (array)  Associative array of facet counts, e.g. ['sectors' => [...], 'types' => [...], 'conditions' => [...]]
    $filters    (array)  Currently active filter values from request
    $sectors    (array)  Available sector values
    $categories (array)  Available category objects (id, name)
--}}
@php
  $facets     = $facets ?? [];
  $filters    = $filters ?? [];
  $sectors    = $sectors ?? [];
  $categories = $categories ?? [];
@endphp
<form method="get" action="{{ route('marketplace.browse') }}" id="mkt-filter-form">

  {{-- Sector --}}
  @if (!empty($sectors))
    <div class="card mkt-filter-group mb-3">
      <div class="card-header fw-semibold py-2" data-bs-toggle="collapse" data-bs-target="#mkt-filter-sector" role="button" aria-expanded="true">
        {{ __('Sector') }}
        <i class="fas fa-chevron-down float-end mt-1 small"></i>
      </div>
      <div class="collapse show" id="mkt-filter-sector">
        <div class="card-body py-2">
          @foreach ($sectors as $s)
            @php
              $checked = isset($filters['sector']) && ((is_array($filters['sector']) && in_array($s, $filters['sector'])) || $filters['sector'] === $s);
            @endphp
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="sector[]" value="{{ $s }}" id="mkt-f-sector-{{ $s }}" @checked($checked)>
              <label class="form-check-label" for="mkt-f-sector-{{ $s }}">
                {{ ucfirst($s) }}
                @if (isset($facets['sectors'][$s]))
                  <span class="mkt-filter-count badge bg-secondary ms-1">{{ (int) $facets['sectors'][$s] }}</span>
                @endif
              </label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  {{-- Listing Type --}}
  <div class="card mkt-filter-group mb-3">
    <div class="card-header fw-semibold py-2" data-bs-toggle="collapse" data-bs-target="#mkt-filter-type" role="button" aria-expanded="true">
      {{ __('Listing Type') }}
      <i class="fas fa-chevron-down float-end mt-1 small"></i>
    </div>
    <div class="collapse show" id="mkt-filter-type">
      <div class="card-body py-2">
        @php $types = ['fixed_price' => __('Buy Now'), 'auction' => __('Auction'), 'offer_only' => __('Make an Offer')]; @endphp
        @foreach ($types as $val => $label)
          @php
            $checked = isset($filters['listing_type']) && ((is_array($filters['listing_type']) && in_array($val, $filters['listing_type'])) || $filters['listing_type'] === $val);
          @endphp
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="listing_type[]" value="{{ $val }}" id="mkt-f-type-{{ $val }}" @checked($checked)>
            <label class="form-check-label" for="mkt-f-type-{{ $val }}">
              {{ $label }}
              @if (isset($facets['types'][$val]))
                <span class="mkt-filter-count badge bg-secondary ms-1">{{ (int) $facets['types'][$val] }}</span>
              @endif
            </label>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Price Range --}}
  <div class="card mkt-filter-group mb-3">
    <div class="card-header fw-semibold py-2" data-bs-toggle="collapse" data-bs-target="#mkt-filter-price" role="button" aria-expanded="true">
      {{ __('Price Range') }}
      <i class="fas fa-chevron-down float-end mt-1 small"></i>
    </div>
    <div class="collapse show" id="mkt-filter-price">
      <div class="card-body py-2">
        <div class="row g-2">
          <div class="col-6">
            <input type="number" class="form-control form-control-sm" name="price_min" placeholder="{{ __('Min') }}" value="{{ $filters['price_min'] ?? '' }}" min="0" step="0.01">
          </div>
          <div class="col-6">
            <input type="number" class="form-control form-control-sm" name="price_max" placeholder="{{ __('Max') }}" value="{{ $filters['price_max'] ?? '' }}" min="0" step="0.01">
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Condition --}}
  <div class="card mkt-filter-group mb-3">
    <div class="card-header fw-semibold py-2" data-bs-toggle="collapse" data-bs-target="#mkt-filter-condition" role="button" aria-expanded="false">
      {{ __('Condition') }}
      <i class="fas fa-chevron-down float-end mt-1 small"></i>
    </div>
    <div class="collapse" id="mkt-filter-condition">
      <div class="card-body py-2">
        @php
          $conditions = ['mint' => __('Mint'), 'excellent' => __('Excellent'), 'good' => __('Good'), 'fair' => __('Fair'), 'poor' => __('Poor')];
        @endphp
        @foreach ($conditions as $val => $label)
          @php
            $checked = isset($filters['condition_rating']) && ((is_array($filters['condition_rating']) && in_array($val, $filters['condition_rating'])) || $filters['condition_rating'] === $val);
          @endphp
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="condition_rating[]" value="{{ $val }}" id="mkt-f-cond-{{ $val }}" @checked($checked)>
            <label class="form-check-label" for="mkt-f-cond-{{ $val }}">
              {{ $label }}
              @if (isset($facets['conditions'][$val]))
                <span class="mkt-filter-count badge bg-secondary ms-1">{{ (int) $facets['conditions'][$val] }}</span>
              @endif
            </label>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Digital / Physical --}}
  <div class="card mkt-filter-group mb-3">
    <div class="card-header fw-semibold py-2" data-bs-toggle="collapse" data-bs-target="#mkt-filter-delivery" role="button" aria-expanded="false">
      {{ __('Delivery') }}
      <i class="fas fa-chevron-down float-end mt-1 small"></i>
    </div>
    <div class="collapse" id="mkt-filter-delivery">
      <div class="card-body py-2">
        <div class="form-check">
          <input class="form-check-input" type="radio" name="delivery_type" value="" id="mkt-f-del-all" @checked(empty($filters['delivery_type']))>
          <label class="form-check-label" for="mkt-f-del-all">{{ __('All') }}</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="delivery_type" value="physical" id="mkt-f-del-phys" @checked(isset($filters['delivery_type']) && $filters['delivery_type'] === 'physical')>
          <label class="form-check-label" for="mkt-f-del-phys">{{ __('Physical') }}</label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="delivery_type" value="digital" id="mkt-f-del-dig" @checked(isset($filters['delivery_type']) && $filters['delivery_type'] === 'digital')>
          <label class="form-check-label" for="mkt-f-del-dig">{{ __('Digital') }}</label>
        </div>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary w-100 mb-2">{{ __('Apply Filters') }}</button>
  <a href="{{ route('marketplace.browse') }}" class="btn btn-outline-secondary w-100">{{ __('Clear All') }}</a>

</form>
