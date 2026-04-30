{{--
  Library Settings — loan rules, circulation, patrons, OPAC, holds, ISBN
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('library')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Library Settings')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-book me-2"></i>Library Settings</h1>
<p class="text-muted">Loan rules, circulation, fines, patron defaults, OPAC, ISBN providers</p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.library') }}">
    @csrf

    {{-- Card 1: Circulation Defaults --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Circulation Defaults</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="library_default_loan_days" class="form-label">{{ __('Default Loan Period (days)') }}</label>
            <input type="number" class="form-control" id="library_default_loan_days"
                   name="settings[library_default_loan_days]"
                   value="{{ $settings['library_default_loan_days'] ?? '14' }}" min="1">
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_max_renewals" class="form-label">{{ __('Default Max Renewals') }}</label>
            <input type="number" class="form-control" id="library_max_renewals"
                   name="settings[library_max_renewals]"
                   value="{{ $settings['library_max_renewals'] ?? '2' }}" min="0">
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_currency" class="form-label">{{ __('Currency') }}</label>
            <input type="text" class="form-control" id="library_currency"
                   name="settings[library_currency]"
                   value="{{ $settings['library_currency'] ?? 'ZAR' }}" maxlength="3">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_auto_fine"
                     name="settings[library_auto_fine]" value="true"
                     {{ ($settings['library_auto_fine'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_auto_fine">{{ __('Auto-generate daily overdue fines') }}</label>
            </div>
            <div class="form-text">When enabled, library:process-fines cron creates daily fine entries for overdue items.</div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_barcode_auto_generate"
                     name="settings[library_barcode_auto_generate]" value="true"
                     {{ ($settings['library_barcode_auto_generate'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_barcode_auto_generate">{{ __('Auto-generate barcodes for new copies') }}</label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_auto_expire_holds"
                     name="settings[library_auto_expire_holds]" value="true"
                     {{ ($settings['library_auto_expire_holds'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_auto_expire_holds">{{ __('Auto-expire unfulfilled holds') }}</label>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_auto_expire_patrons"
                     name="settings[library_auto_expire_patrons]" value="true"
                     {{ ($settings['library_auto_expire_patrons'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_auto_expire_patrons">{{ __('Auto-expire patron memberships') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Card 2: Patron Defaults --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-users me-2"></i>Patron Defaults</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3 mb-3">
            <label for="library_patron_max_checkouts" class="form-label">{{ __('Max Checkouts') }}</label>
            <input type="number" class="form-control" id="library_patron_max_checkouts"
                   name="settings[library_patron_max_checkouts]"
                   value="{{ $settings['library_patron_max_checkouts'] ?? '5' }}" min="1">
          </div>
          <div class="col-md-3 mb-3">
            <label for="library_patron_max_renewals" class="form-label">{{ __('Max Renewals') }}</label>
            <input type="number" class="form-control" id="library_patron_max_renewals"
                   name="settings[library_patron_max_renewals]"
                   value="{{ $settings['library_patron_max_renewals'] ?? '2' }}" min="0">
          </div>
          <div class="col-md-3 mb-3">
            <label for="library_patron_max_holds" class="form-label">{{ __('Max Holds') }}</label>
            <input type="number" class="form-control" id="library_patron_max_holds"
                   name="settings[library_patron_max_holds]"
                   value="{{ $settings['library_patron_max_holds'] ?? '3' }}" min="0">
          </div>
          <div class="col-md-3 mb-3">
            <label for="library_patron_membership_months" class="form-label">{{ __('Membership Duration (months)') }}</label>
            <input type="number" class="form-control" id="library_patron_membership_months"
                   name="settings[library_patron_membership_months]"
                   value="{{ $settings['library_patron_membership_months'] ?? '12' }}" min="1">
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="library_patron_fine_threshold" class="form-label">{{ __('Fine Threshold (block borrowing)') }}</label>
            <div class="input-group">
              <span class="input-group-text">{{ $settings['library_currency'] ?? 'ZAR' }}</span>
              <input type="number" class="form-control" id="library_patron_fine_threshold"
                     name="settings[library_patron_fine_threshold]"
                     value="{{ $settings['library_patron_fine_threshold'] ?? '50.00' }}" min="0" step="0.01">
            </div>
            <div class="form-text">Patrons with outstanding fines above this amount cannot borrow.</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_patron_default_type" class="form-label">{{ __('Default Patron Type') }}</label>
            <select class="form-select" id="library_patron_default_type" name="settings[library_patron_default_type]">
              @foreach (['public','student','faculty','staff','researcher','institutional'] as $pt)
                <option value="{{ $pt }}" {{ ($settings['library_patron_default_type'] ?? 'public') === $pt ? 'selected' : '' }}>{{ ucfirst($pt) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_patron_expiry_grace_days" class="form-label">{{ __('Expiry Grace Period (days)') }}</label>
            <input type="number" class="form-control" id="library_patron_expiry_grace_days"
                   name="settings[library_patron_expiry_grace_days]"
                   value="{{ $settings['library_patron_expiry_grace_days'] ?? '7' }}" min="0">
          </div>
        </div>
      </div>
    </div>

    {{-- Card 3: OPAC --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-globe me-2"></i>OPAC (Public Catalog)</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_opac_enabled"
                     name="settings[library_opac_enabled]" value="true"
                     {{ ($settings['library_opac_enabled'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_opac_enabled">{{ __('Enable public OPAC') }}</label>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_opac_show_availability"
                     name="settings[library_opac_show_availability]" value="true"
                     {{ ($settings['library_opac_show_availability'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_opac_show_availability">{{ __('Show copy availability in search results') }}</label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_opac_show_covers"
                     name="settings[library_opac_show_covers]" value="true"
                     {{ ($settings['library_opac_show_covers'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_opac_show_covers">{{ __('Show book cover images') }}</label>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="library_opac_allow_holds"
                     name="settings[library_opac_allow_holds]" value="true"
                     {{ ($settings['library_opac_allow_holds'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="library_opac_allow_holds">{{ __('Allow patrons to place holds via OPAC') }}</label>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="library_opac_results_per_page" class="form-label">{{ __('Results Per Page') }}</label>
            <input type="number" class="form-control" id="library_opac_results_per_page"
                   name="settings[library_opac_results_per_page]"
                   value="{{ $settings['library_opac_results_per_page'] ?? '20' }}" min="5" max="100">
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_opac_new_arrivals_count" class="form-label">{{ __('New Arrivals Count') }}</label>
            <input type="number" class="form-control" id="library_opac_new_arrivals_count"
                   name="settings[library_opac_new_arrivals_count]"
                   value="{{ $settings['library_opac_new_arrivals_count'] ?? '8' }}" min="1" max="50">
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_opac_popular_days" class="form-label">{{ __('Popular Items -- Days Window') }}</label>
            <input type="number" class="form-control" id="library_opac_popular_days"
                   name="settings[library_opac_popular_days]"
                   value="{{ $settings['library_opac_popular_days'] ?? '90' }}" min="7" max="365">
          </div>
        </div>
      </div>
    </div>

    {{-- Card 4: Hold Settings --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-hand-paper me-2"></i>Hold Settings</h5></div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4 mb-3">
            <label for="library_hold_expiry_days" class="form-label">{{ __('Hold Expiry (days after ready)') }}</label>
            <input type="number" class="form-control" id="library_hold_expiry_days"
                   name="settings[library_hold_expiry_days]"
                   value="{{ $settings['library_hold_expiry_days'] ?? '7' }}" min="1">
            <div class="form-text">Days a hold remains ready for pickup before expiring.</div>
          </div>
          <div class="col-md-4 mb-3">
            <label for="library_hold_max_queue" class="form-label">{{ __('Max Queue Size Per Item') }}</label>
            <input type="number" class="form-control" id="library_hold_max_queue"
                   name="settings[library_hold_max_queue]"
                   value="{{ $settings['library_hold_max_queue'] ?? '10' }}" min="1">
          </div>
        </div>
      </div>
    </div>

    {{-- Card 5: ISBN Providers --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-barcode me-2"></i>ISBN Providers</h5></div>
      <div class="card-body">
        <p class="text-muted mb-3">Manage ISBN lookup providers (Open Library, Google Books, WorldCat) for automatic metadata retrieval.</p>
        @if(\Route::has('library.isbn-providers'))
        <a href="{{ route('library.isbn-providers') }}" class="btn btn-outline-primary">
          <i class="fas fa-external-link-alt me-1"></i>{{ __('Manage ISBN Providers') }}
        </a>
        @else
        <a href="{{ url('/library/isbnProviders') }}" class="btn btn-outline-primary">
          <i class="fas fa-external-link-alt me-1"></i>{{ __('Manage ISBN Providers') }}
        </a>
        @endif
      </div>
    </div>

    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>{{ __('Save') }}
      </button>
    </div>
  </form>
@endsection
