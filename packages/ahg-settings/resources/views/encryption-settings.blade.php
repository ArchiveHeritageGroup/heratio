{{--
  Encryption — XChaCha20 encryption settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('encryption')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Encryption')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-lock me-2"></i>Encryption</h1>
<p class="text-muted">Field-level encryption and key management</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.encryption') }}">
    @csrf

    {{-- Card 1: Encryption Configuration --}}
    <div class="card mb-4">
      <div class="card-header bg-dark text-white">
        <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Encryption Configuration</h5>
      </div>
      <div class="card-body">
        @php
          $keyPath = '/etc/heratio/encryption.key';
          $keyExists = file_exists($keyPath);
          $keyPerms = $keyExists ? substr(sprintf('%o', fileperms($keyPath)), -4) : null;
          $hasSodium = extension_loaded('sodium') && function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push');
          $algoName = $hasSodium ? 'XChaCha20-Poly1305 (libsodium)' : 'AES-256-GCM (OpenSSL)';
        @endphp

        <p class="text-muted mb-3">Encryption for digital object files and sensitive database fields using <strong>{{ $algoName }}</strong>. Requires an encryption key at <code>{{ $keyPath }}</code>.</p>

        {{-- Key Status --}}
        <div class="alert {{ $keyExists ? 'alert-success' : 'alert-warning' }} mb-3">
          <i class="fas {{ $keyExists ? 'fa-check-circle' : 'fa-exclamation-triangle' }} me-2"></i>
          @if ($keyExists)
            <strong>Encryption key found</strong>
            <span class="ms-2 text-muted">Path: <code>{{ $keyPath }}</code> | Permissions: <code>{{ $keyPerms }}</code> | Algorithm: <code>{{ $algoName }}</code></span>
            @if ($keyPerms !== '0600')
              <br><small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Permissions should be 0600 for security.</small>
            @endif
          @else
            <strong>No encryption key found</strong>
            <br><small>Generate with: <code>php artisan encryption:key --generate</code></small>
          @endif
        </div>

        {{-- Master Toggle --}}
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="encryption_enabled"
                 name="encryption_enabled" value="1"
                 {{ ($settings['encryption_enabled'] ?? '') === 'true' || ($settings['encryption_enabled'] ?? '') === '1' ? 'checked' : '' }}
                 {{ !$keyExists ? 'disabled' : '' }}>
          <label class="form-check-label fw-bold" for="encryption_enabled">{{ __('Enable Encryption') }}</label>
        </div>
        <div class="form-text mb-3">Master toggle. When enabled, new file uploads will be encrypted automatically.</div>
      </div>
    </div>

    {{-- Card 2: Layer 1 — Digital Object Encryption --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file-shield me-2"></i>Layer 1: Digital Object Encryption</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Encrypts uploaded files (masters and derivatives) on disk using {{ $algoName }}.</p>

        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="encryption_encrypt_derivatives"
                 name="encryption_encrypt_derivatives" value="1"
                 {{ ($settings['encryption_encrypt_derivatives'] ?? 'true') === 'true' || ($settings['encryption_encrypt_derivatives'] ?? '') === '1' ? 'checked' : '' }}>
          <label class="form-check-label fw-bold" for="encryption_encrypt_derivatives">{{ __('Encrypt derivatives') }}</label>
        </div>
        <div class="form-text mb-3">Also encrypt thumbnails and reference images. Recommended for full protection.</div>

        @php
          $totalDOs = 0;
          try {
            $totalDOs = \DB::table('digital_object')->whereNotNull('path')->whereNotNull('name')->count();
          } catch (\Throwable $e) {}
        @endphp

        <div class="alert alert-info mb-0">
          <i class="fas fa-info-circle me-2"></i>
          <strong>{{ $totalDOs }}</strong> digital objects on disk.
          <br><small>To encrypt existing files: <code>php artisan encryption:encrypt-files --limit=100</code></small>
        </div>
      </div>
    </div>

    {{-- Card 3: Layer 2 — Database Field Encryption --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Layer 2: Database Field Encryption</h5>
      </div>
      <div class="card-body">
        <p class="text-muted mb-3">Transparent encryption of sensitive database columns. Toggle categories below, then run the CLI to encrypt existing data.</p>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="encryption_field_contact_details"
                     name="encryption_field_contact_details" value="1"
                     {{ ($settings['encryption_field_contact_details'] ?? '') === 'true' || ($settings['encryption_field_contact_details'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="encryption_field_contact_details">
                <strong><i class="fas fa-address-card me-1 text-primary"></i>Contact Details</strong>
              </label>
            </div>
            <div class="form-text">Email, address, telephone, fax, contact person (contact_information tables).</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="encryption_field_financial_data"
                     name="encryption_field_financial_data" value="1"
                     {{ ($settings['encryption_field_financial_data'] ?? '') === 'true' || ($settings['encryption_field_financial_data'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="encryption_field_financial_data">
                <strong><i class="fas fa-coins me-1 text-warning"></i>Financial Data</strong>
              </label>
            </div>
            <div class="form-text">Appraisal values in accession records.</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="encryption_field_donor_information"
                     name="encryption_field_donor_information" value="1"
                     {{ ($settings['encryption_field_donor_information'] ?? '') === 'true' || ($settings['encryption_field_donor_information'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="encryption_field_donor_information">
                <strong><i class="fas fa-user-shield me-1 text-success"></i>Donor Information</strong>
              </label>
            </div>
            <div class="form-text">Actor history (biographical/administrative history for donors).</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="encryption_field_personal_notes"
                     name="encryption_field_personal_notes" value="1"
                     {{ ($settings['encryption_field_personal_notes'] ?? '') === 'true' || ($settings['encryption_field_personal_notes'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="encryption_field_personal_notes">
                <strong><i class="fas fa-sticky-note me-1 text-info"></i>Personal Notes</strong>
              </label>
            </div>
            <div class="form-text">Note content (internal staff notes on records).</div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="encryption_field_access_restrictions"
                     name="encryption_field_access_restrictions" value="1"
                     {{ ($settings['encryption_field_access_restrictions'] ?? '') === 'true' || ($settings['encryption_field_access_restrictions'] ?? '') === '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="encryption_field_access_restrictions">
                <strong><i class="fas fa-ban me-1 text-danger"></i>Access Restrictions</strong>
              </label>
            </div>
            <div class="form-text">Rights notes (access restriction details in rights statements).</div>
          </div>
        </div>

        <div class="alert alert-secondary mt-3 mb-0">
          <i class="fas fa-terminal me-2"></i>
          <strong>CLI Commands</strong>
          <br><code>php artisan encryption:encrypt-fields --category=contact_details</code> -- Encrypt a category
          <br><code>php artisan encryption:encrypt-fields --category=contact_details --reverse</code> -- Decrypt a category
          <br><code>php artisan encryption:encrypt-fields --list</code> -- Show category status
          <br><code>php artisan encryption:status</code> -- Full encryption dashboard
        </div>
      </div>
    </div>

    {{-- Card 4: Compliance --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-gavel me-2"></i>Compliance</h5>
      </div>
      <div class="card-body">
        <p class="mb-2">Encryption at rest satisfies requirements from:</p>
        <ul class="mb-0">
          <li><strong>POPIA</strong> -- Protection of Personal Information Act (South Africa), Section 19</li>
          <li><strong>GDPR</strong> -- General Data Protection Regulation (EU), Article 32</li>
          <li><strong>CCPA</strong> -- California Consumer Privacy Act, reasonable security measures</li>
          <li><strong>NARSSA</strong> -- National Archives and Record Service of South Africa</li>
          <li><strong>PAIA</strong> -- Promotion of Access to Information Act, secure record keeping</li>
        </ul>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
