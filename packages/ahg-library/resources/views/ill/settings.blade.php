@extends('theme::layouts.1col')

@section('title', 'ILL Settings')

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-4">
    <a href="{{ route('library.ill') }}" class="text-muted me-2">
      <i class="fas fa-arrow-left"></i>
    </a>
    <h1 class="mb-0">ILL Settings</h1>
  </div>

  @if(session('ill_success'))
    <div class="alert alert-success">{{ session('ill_success') }}</div>
  @endif

  <form method="post" action="{{ route('library.ill-settings-store') }}">
    @csrf

    <div class="row">
      <div class="col-md-8">

        {{-- General ─────────────────────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-cog me-1"></i>{{ __('General') }}
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Default Loan Period (days)') }}</label>
                <input type="number" name="default_due_days"
                       value="{{ old('default_due_days', $settings['ill_default_due_days'] ?? 28) }}"
                       class="form-control" min="1" max="365">
                <div class="form-text">Standard lending period before escalation to overdue.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('Auto-Escalate Overdue After (days)') }}</label>
                <input type="number" name="auto_escalate_days"
                       value="{{ old('auto_escalate_days', $settings['ill_auto_escalate_days'] ?? 0) }}"
                       class="form-control" min="0" max="90">
                <div class="form-text">0 disables auto-escalation. Cron job runs nightly.</div>
              </div>
            </div>
          </div>
        </div>

        {{-- Tipasa / partner settings ────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-network-wired me-1"></i>{{ __('South African Library Network (Tipasa)') }}
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Default Partner') }}</label>
              <select name="tipasa_partner" class="form-select">
                <option value="">— none —</option>
                <option value="naz"      {{ (old('tipasa_partner', $settings['ill_tipasa_partner'] ?? '') === 'naz')      ? 'selected' : '' }}>National Archives of Zimbabwe (NAZ)</option>
                <option value="sabinet"  {{ (old('tipasa_partner', $settings['ill_tipasa_partner'] ?? '') === 'sabinet')  ? 'selected' : '' }}>SABINET (National Library of SA)</option>
                <option value="dals"     {{ (old('tipasa_partner', $settings['ill_tipasa_partner'] ?? '') === 'dals')     ? 'selected' : '' }}>DALS (Digital Access to Library Services)</option>
              </select>
            </div>
            <p class="text-muted small">
              Tipasa (SA ISSN agency / national library network) interlibrary loan partner.
              Configure your institution's Tipasa credentials in the <code>NAZ_INI</code> config
              or via environment variables.
            </p>
          </div>
        </div>

        {{-- OCLC ILL Protocol REST ──────────────────────────────────── --}}
        <div class="card mb-4">
          <div class="card-header">
            <i class="fas fa-globe me-1"></i>{{ __('OCLC ILL Protocol (ISO 10161-1 REST)') }}
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Connect to OCLC WorldShare or any ISO 10161-1 compliant ILL system via REST.
              See <code>docs/ill-oclc-protocol.md</code> for field mapping details.
            </p>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('OCLC API Key') }}</label>
                <input type="password" name="oclc_api_key"
                       value="{{ old('oclc_api_key', $settings['ill_oclc_api_key'] ?? '') }}"
                       class="form-control" maxlength="200" autocomplete="new-password">
                <div class="form-text">Stored encrypted. Never shown after save.</div>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">{{ __('OCLC Principal ID') }}</label>
                <input type="text" name="oclc_principal_id"
                       value="{{ old('oclc_principal_id', $settings['ill_oclc_principal_id'] ?? '') }}"
                       class="form-control" maxlength="100" placeholder="e.g. BOR-12345">
              </div>
            </div>
            <div class="mb-0">
              <label class="form-label">{{ __('ILL Protocol Base URL') }}</label>
              <input type="url" name="oclc_base_url"
                     value="{{ old('oclc_base_url', $settings['ill_oclc_base_url'] ?? 'https://ill.oclcrouting.org/v1') }}"
                     class="form-control" placeholder="https://ill.oclcrouting.org/v1">
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">
          <i class="fas fa-save me-1"></i>{{ __('Save Settings') }}
        </button>

      </div>

      {{-- Help sidebar ───────────────────────────────────────────────── --}}
      <div class="col-md-4">
        <div class="card">
          <div class="card-header">
            <i class="fas fa-question-circle me-1"></i>{{ __('Tipasa / NAZ setup') }}
          </div>
          <div class="card-body small">
            <p>To enable automated NAZ/SABINET ILL:</p>
            <ol class="ps-3">
              <li>Register with the National Library of Zimbabwe or SABINET.</li>
              <li>Obtain your institution's TIPASA client credentials.</li>
              <li>Configure <code>NAZ_INI</code> path in your environment.</li>
              <li>Set the default partner here.</li>
            </ol>
            <hr>
            <p>For OCLC WorldShare ILL:</p>
            <ol class="ps-3">
              <li>Apply for an OCLC API key at oclc.org.</li>
              <li>Enter your <code>Principal ID</code> (provided by OCLC).</li>
              <li>Set the base URL for your region.</li>
            </ol>
          </div>
        </div>
      </div>

    </div>
  </form>
</div>
@endsection