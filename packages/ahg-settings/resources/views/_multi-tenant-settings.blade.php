{{-- Multi-tenant settings partial --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas fa-building me-2"></i>Multi-Tenancy</h5>
  </div>
  <div class="card-body">
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[tenant_enabled]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[tenant_enabled]" id="tenant_enabled" value="1" {{ ($settings['tenant_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="tenant_enabled">Enable multi-tenancy</label>
    </div>
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[tenant_enforce_filter]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[tenant_enforce_filter]" id="tenant_filter" value="1" {{ ($settings['tenant_enforce_filter'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="tenant_filter">Enforce tenant data isolation</label>
    </div>
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[tenant_show_switcher]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[tenant_show_switcher]" id="tenant_switcher" value="1" {{ ($settings['tenant_show_switcher'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="tenant_switcher">Show tenant switcher in header</label>
    </div>
    <div class="form-check form-switch mb-3">
      <input type="hidden" name="settings[tenant_allow_branding]" value="0">
      <input class="form-check-input" type="checkbox" name="settings[tenant_allow_branding]" id="tenant_branding" value="1" {{ ($settings['tenant_allow_branding'] ?? '0') == '1' ? 'checked' : '' }}>
      <label class="form-check-label" for="tenant_branding">Allow per-tenant branding</label>
    </div>
  </div>
</div>
