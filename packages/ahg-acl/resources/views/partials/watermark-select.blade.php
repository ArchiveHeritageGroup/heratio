{{-- Watermark Selection Component - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/_watermarkSelect.php --}}
@php
use Illuminate\Support\Facades\DB;

$watermarkTypes = $watermarkTypes ?? DB::table('watermark_type')->where('active', 1)->orderBy('sort_order')->get();

$currentWatermarkId = null;
$watermarkEnabled = true;
$customWatermarkId = null;
$position = 'center';
$opacity = 0.40;

// Get from object_watermark_setting
if (isset($resource) && ($resource->id ?? null)) {
    $setting = DB::table('object_watermark_setting')
        ->where('object_id', $resource->id)
        ->first();

    if ($setting) {
        $currentWatermarkId = $setting->watermark_type_id;
        $watermarkEnabled = (bool) $setting->watermark_enabled;
        $customWatermarkId = $setting->custom_watermark_id;
        $position = $setting->position ?? 'center';
        $opacity = $setting->opacity ?? 0.40;
    }
}
@endphp

<div class="watermark-settings mb-3">
    <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" id="watermark_enabled" name="watermark_enabled"
               value="1" {{ $watermarkEnabled ? 'checked' : '' }}>
        <label class="form-check-label" for="watermark_enabled">
            Enable Watermark
        </label>
    </div>

    <div class="mb-3">
        <label for="watermark_type_id" class="form-label">{{ __('Watermark Type') }}</label>
        <select class="form-select" id="watermark_type_id" name="watermark_type_id">
            <option value="">{{ __('Use default') }}</option>
            @foreach($watermarkTypes as $type)
                <option value="{{ $type->id }}"
                    {{ $currentWatermarkId == $type->id ? 'selected' : '' }}>
                    {{ e($type->name) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label for="watermark_position" class="form-label">{{ __('Position') }}</label>
        <select class="form-select" id="watermark_position" name="watermark_position">
            @php
            $positions = [
                'top-left' => 'Top Left',
                'top-center' => 'Top Center',
                'top-right' => 'Top Right',
                'center-left' => 'Center Left',
                'center' => 'Center',
                'center-right' => 'Center Right',
                'bottom-left' => 'Bottom Left',
                'bottom-center' => 'Bottom Center',
                'bottom-right' => 'Bottom Right',
                'repeat' => 'Repeat/Tile',
            ];
            @endphp
            @foreach($positions as $value => $label)
                <option value="{{ $value }}" {{ $position === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="mb-3">
        <label for="watermark_opacity" class="form-label">{{ __('Opacity') }}</label>
        <input type="range" class="form-range" id="watermark_opacity" name="watermark_opacity"
               min="10" max="100" step="5" value="{{ (int) ($opacity * 100) }}">
        <small class="text-muted"><span id="opacity_value">{{ (int) ($opacity * 100) }}</span>%</small>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var enabledCheckbox = document.getElementById('watermark_enabled');
    var typeSelect = document.getElementById('watermark_type_id');
    var positionSelect = document.getElementById('watermark_position');
    var opacityRange = document.getElementById('watermark_opacity');
    var opacityValue = document.getElementById('opacity_value');

    function toggleFields() {
        var enabled = enabledCheckbox.checked;
        typeSelect.disabled = !enabled;
        positionSelect.disabled = !enabled;
        opacityRange.disabled = !enabled;
    }

    enabledCheckbox.addEventListener('change', toggleFields);
    toggleFields();

    opacityRange.addEventListener('input', function() {
        opacityValue.textContent = this.value;
    });
});
</script>
