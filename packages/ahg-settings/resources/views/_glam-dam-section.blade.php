{{-- GLAM/DAM Section partial — shows sector-specific settings --}}
<div class="card mb-4">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
    <h5 class="mb-0"><i class="fas {{ $sectionIcon ?? 'fa-archive' }} me-2"></i>{{ $sectionLabel ?? 'GLAM/DAM Section' }}</h5>
  </div>
  <div class="card-body">
    @foreach($sectionSettings ?? [] as $setting)
      <div class="mb-3">
        <label class="form-label fw-semibold">{{ ucfirst(str_replace('_', ' ', $setting->setting_key)) }} <span class="badge bg-secondary ms-1">Optional</span></label>
        @if($setting->setting_type === 'boolean')
          <div class="form-check form-switch">
            <input type="hidden" name="settings[{{ $setting->setting_key }}]" value="0">
            <input class="form-check-input" type="checkbox" name="settings[{{ $setting->setting_key }}]" value="1" {{ $setting->setting_value == '1' ? 'checked' : '' }}>
          </div>
        @else
          <input type="text" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ $setting->setting_value ?? '' }}">
        @endif
        @if($setting->description)<small class="text-muted">{{ $setting->description }}</small>@endif
      </div>
    @endforeach
  </div>
</div>
