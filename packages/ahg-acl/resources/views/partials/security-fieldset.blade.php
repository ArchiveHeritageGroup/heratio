{{-- Security Classification Fieldset with Watermark Selection - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_securityFieldset.php --}}
@php
use Illuminate\Support\Facades\DB;

// Get all classification levels
$classifications = $classifications ?? DB::table('security_classification')->where('active', 1)->orderBy('level')->get();

// Get current classification if editing
$currentClassification = $currentClassification ?? null;
if (!$currentClassification && isset($resource) && ($resource->id ?? null)) {
    $currentClassification = DB::table('object_security_classification as osc')
        ->join('security_classification as sc', 'sc.id', '=', 'osc.classification_id')
        ->where('osc.object_id', $resource->id)
        ->where('osc.active', 1)
        ->first();
}

// Get watermark types and custom watermarks
$watermarkTypes = $watermarkTypes ?? DB::table('watermark_type')->where('active', 1)->orderBy('sort_order')->get();
$customWatermarks = $customWatermarks ?? collect();

$currentWatermarkId = null;
$currentCustomWatermarkId = null;
$watermarkEnabled = true;
$currentPosition = 'center';
$currentOpacity = 0.40;

if (isset($resource) && ($resource->id ?? null)) {
    $watermarkSetting = DB::table('object_watermark_setting')
        ->where('object_id', $resource->id)
        ->first();

    if ($watermarkSetting) {
        $currentWatermarkId = $watermarkSetting->watermark_type_id;
        $currentCustomWatermarkId = $watermarkSetting->custom_watermark_id;
        $watermarkEnabled = (bool) $watermarkSetting->watermark_enabled;
        $currentPosition = $watermarkSetting->position ?? 'center';
        $currentOpacity = $watermarkSetting->opacity ?? 0.40;
    }
}
@endphp

{{-- Security Classification --}}
<div class="accordion-item">
  <h2 class="accordion-header" id="security-heading">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#security-collapse" aria-expanded="false" aria-controls="security-collapse">
      {{ __('Security Classification') }}
    </button>
  </h2>
  <div id="security-collapse" class="accordion-collapse collapse" aria-labelledby="security-heading">
    <div class="accordion-body">

      <div class="mb-3">
        <label for="security_classification_id" class="form-label">{{ __('Security Classification') }}</label>
        <select class="form-select" id="security_classification_id" name="security_classification_id">
          <option value="">{{ __('Public (No Classification)') }}</option>
          @foreach($classifications as $cls)
            <option value="{{ $cls->id }}"
                    data-level="{{ $cls->level }}"
                    {{ ($currentClassification && ($currentClassification->classification_id ?? null) == $cls->id) ? 'selected' : '' }}>
              {{ $cls->name }}
            </option>
          @endforeach
        </select>
        <small class="text-muted">{{ __('Security classification watermarks override all other watermarks.') }}</small>
      </div>

      <div id="classification-details" style="{{ $currentClassification ? '' : 'display: none;' }}">
        <div class="mb-3">
          <label for="security_reason" class="form-label">{{ __('Classification Reason') }}</label>
          <textarea class="form-control" id="security_reason" name="security_reason" rows="2">{{ $currentClassification ? e($currentClassification->reason ?? '') : '' }}</textarea>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label for="security_review_date" class="form-label">{{ __('Review Date') }}</label>
            <input type="date" class="form-control" id="security_review_date" name="security_review_date"
                   value="{{ $currentClassification ? ($currentClassification->review_date ?? '') : '' }}">
          </div>
          <div class="col-md-6 mb-3">
            <label for="security_declassify_date" class="form-label">{{ __('Declassify Date') }}</label>
            <input type="date" class="form-control" id="security_declassify_date" name="security_declassify_date"
                   value="{{ $currentClassification ? ($currentClassification->declassify_date ?? '') : '' }}">
          </div>
        </div>

        <div class="mb-3">
          <label for="security_handling_instructions" class="form-label">{{ __('Handling Instructions') }}</label>
          <textarea class="form-control" id="security_handling_instructions" name="security_handling_instructions" rows="2">{{ $currentClassification ? e($currentClassification->handling_instructions ?? '') : '' }}</textarea>
        </div>

        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" id="security_inherit_to_children" name="security_inherit_to_children" value="1"
                 {{ (!$currentClassification || ($currentClassification->inherit_to_children ?? true)) ? 'checked' : '' }}>
          <label class="form-check-label" for="security_inherit_to_children">
            Apply to child records
          </label>
        </div>
      </div>

    </div>
  </div>
</div>

{{-- Watermark Settings --}}
<div class="accordion-item">
  <h2 class="accordion-header" id="watermark-heading">
    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
            data-bs-target="#watermark-collapse" aria-expanded="false" aria-controls="watermark-collapse">
      {{ __('Watermark Settings') }}
    </button>
  </h2>
  <div id="watermark-collapse" class="accordion-collapse collapse" aria-labelledby="watermark-heading">
    <div class="accordion-body">

      <div class="mb-3">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled"
                 value="1" {{ $watermarkEnabled ? 'checked' : '' }}>
          <label class="form-check-label" for="watermark_enabled">
            Enable watermark for this object
          </label>
        </div>
      </div>

      <div id="watermark-options" style="{{ $watermarkEnabled ? '' : 'display: none;' }}">

        {{-- System Watermarks --}}
        <div class="mb-3">
          <label for="watermark_type_id" class="form-label">{{ __('System Watermark') }}</label>
          <select class="form-select" id="watermark_type_id" name="watermark_type_id">
            <option value="">{{ __('Use default') }}</option>
            @foreach($watermarkTypes as $wtype)
              <option value="{{ $wtype->id }}"
                      {{ ($currentWatermarkId == $wtype->id && !$currentCustomWatermarkId) ? 'selected' : '' }}
                      data-image="{{ $wtype->image_file ?? '' }}">
                {{ $wtype->name }}
              </option>
            @endforeach
          </select>
        </div>

        {{-- Custom Watermarks --}}
        @if($customWatermarks->count() > 0)
        <div class="mb-3">
          <label for="custom_watermark_id" class="form-label">{{ __('Or Custom Watermark') }}</label>
          <select class="form-select" id="custom_watermark_id" name="custom_watermark_id">
            <option value="">{{ __('None (use system watermark)') }}</option>
            @foreach($customWatermarks as $cw)
              <option value="{{ $cw->id }}"
                      {{ ($currentCustomWatermarkId == $cw->id) ? 'selected' : '' }}
                      data-path="{{ $cw->file_path ?? '' }}">
                {{ e($cw->name) }}
                @if($cw->object_id ?? null) (This record)@endif
              </option>
            @endforeach
          </select>
        </div>
        @endif

        {{-- Upload New Custom Watermark --}}
        <div class="card bg-light mb-3">
          <div class="card-body">
            <h6 class="card-title">{{ __('Upload NEW Custom Watermark') }}</h6>
            <small class="text-muted d-block mb-2">{{ __('Leave empty to keep existing selection above') }}</small>

            <div class="mb-2">
              <label for="new_watermark_name" class="form-label">{{ __('Watermark Name') }}</label>
              <input type="text" class="form-control form-control-sm" id="new_watermark_name" name="new_watermark_name"
                     placeholder="{{ __('e.g., Company Logo') }}">
            </div>

            <div class="mb-2">
              <label for="new_watermark_file" class="form-label">{{ __('Watermark Image') }}</label>
              <input type="file" class="form-control form-control-sm" id="new_watermark_file" name="new_watermark_file"
                     accept="image/png,image/gif">
              <small class="text-muted">{{ __('PNG or GIF with transparency recommended') }}</small>
            </div>

            <div class="row">
              <div class="col-md-6 mb-2">
                <label for="new_watermark_position" class="form-label">{{ __('Position') }}</label>
                <select class="form-select form-select-sm" id="new_watermark_position" name="new_watermark_position">
                  <option value="center" {{ $currentPosition == 'center' ? 'selected' : '' }}>{{ __('Center') }}</option>
                  <option value="repeat" {{ $currentPosition == 'repeat' ? 'selected' : '' }}>{{ __('Repeat (tile)') }}</option>
                  <option value="bottom right" {{ $currentPosition == 'bottom right' ? 'selected' : '' }}>{{ __('Bottom Right') }}</option>
                  <option value="bottom left" {{ $currentPosition == 'bottom left' ? 'selected' : '' }}>{{ __('Bottom Left') }}</option>
                  <option value="top right" {{ $currentPosition == 'top right' ? 'selected' : '' }}>{{ __('Top Right') }}</option>
                  <option value="top left" {{ $currentPosition == 'top left' ? 'selected' : '' }}>{{ __('Top Left') }}</option>
                </select>
              </div>
              <div class="col-md-6 mb-2">
                <label for="new_watermark_opacity" class="form-label">{{ __('Opacity') }}</label>
                <input type="range" class="form-range" id="new_watermark_opacity" name="new_watermark_opacity"
                       min="10" max="80" value="{{ (int)($currentOpacity * 100) }}">
                <small class="text-muted"><span id="opacity-value">{{ (int)($currentOpacity * 100) }}</span>%</small>
              </div>
            </div>

            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="new_watermark_global" name="new_watermark_global" value="1">
              <label class="form-check-label" for="new_watermark_global">
                Make available globally (for all records)
              </label>
            </div>
          </div>
        </div>

        {{-- Regenerate Button --}}
        @if(isset($resource) && ($resource->id ?? null))
        <div class="mb-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="regenerate_derivatives" name="regenerate_derivatives" value="1">
            <label class="form-check-label" for="regenerate_derivatives">
              <strong>{{ __('Regenerate derivatives with new watermark') }}</strong>
            </label>
          </div>
          <small class="text-muted">{{ __('Check this to apply the new watermark to existing images. This may take a moment.') }}</small>
        </div>
        @endif

        <div class="alert alert-info py-2 mb-0">
          <small><i class="fas fa-info-circle me-1"></i>
          Security classification watermarks have the highest priority and will override custom watermarks.
          </small>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Security classification toggle
    var classSelect = document.getElementById('security_classification_id');
    var classDetails = document.getElementById('classification-details');

    if (classSelect && classDetails) {
        classSelect.addEventListener('change', function() {
            classDetails.style.display = this.value ? 'block' : 'none';
        });
    }

    // Watermark enabled toggle
    var wmEnabled = document.getElementById('watermark_enabled');
    var wmOptions = document.getElementById('watermark-options');

    if (wmEnabled && wmOptions) {
        wmEnabled.addEventListener('change', function() {
            wmOptions.style.display = this.checked ? 'block' : 'none';
        });
    }

    // Custom watermark clears system watermark selection
    var customSelect = document.getElementById('custom_watermark_id');
    var systemSelect = document.getElementById('watermark_type_id');

    if (customSelect && systemSelect) {
        customSelect.addEventListener('change', function() {
            if (this.value) {
                systemSelect.value = '';
            }
        });
        systemSelect.addEventListener('change', function() {
            if (this.value && customSelect) {
                customSelect.value = '';
            }
        });
    }

    // Opacity slider display
    var opacitySlider = document.getElementById('new_watermark_opacity');
    var opacityValue = document.getElementById('opacity-value');

    if (opacitySlider && opacityValue) {
        opacitySlider.addEventListener('input', function() {
            opacityValue.textContent = this.value;
        });
    }
});
</script>
