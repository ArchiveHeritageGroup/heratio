@php /**
 * Identifier Generator Component Template
 *
 * Shows auto-generated identifier with option to override.
 */
$info = $numberingInfo;
$isNew = empty($currentIdentifier); @endphp

@if($info['enabled'] && $info['auto_generate'])
<div class="identifier-generator mb-3" id="identifier-generator-@php echo $fieldName; @endphp">
  <div class="alert alert-info py-2 mb-2">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-magic me-2"></i>
        <strong>{{ __('Auto-generated identifier') }}:</strong>
        <code id="generated-identifier" class="ms-2">@php echo esc_entities($info['next_reference']); @endphp</code>
      </div>
      @if($info['allow_override'])
      <button type="button" class="btn btn-sm btn-outline-primary" id="use-generated-btn" title="{{ __('Use this identifier') }}">
        <i class="fas fa-check me-1"></i>{{ __('Use') }}
      </button>
      @endif
    </div>
    <small class="text-muted d-block mt-1">
      {{ __('Scheme') }}: @php echo esc_entities($info['scheme_name']); @endphp
      (@php echo esc_entities($info['pattern']); @endphp)
    </small>
  </div>

  @if(!$info['allow_override'])
  <input type="hidden" name="@php echo $fieldName; @endphp" id="@php echo $fieldName; @endphp-input" value="@php echo esc_entities($info['next_reference']); @endphp">
  <div class="form-control bg-light" readonly>@php echo esc_entities($info['next_reference']); @endphp</div>
  <small class="text-muted">{{ __('Identifier is auto-generated and cannot be changed.') }}</small>
  @endif
</div>

<script @php $n = sfConfig::get('csp_nonce', '');
echo $n ? preg_replace('/^nonce=/', 'nonce="', $n) . '"' : ''; @endphp>
document.addEventListener('DOMContentLoaded', function() {
  var useBtn = document.getElementById('use-generated-btn');
  var generatedId = document.getElementById('generated-identifier');

  // Find the identifier input field (rendered later in the form)
  var input = document.querySelector('input[name="@php echo $fieldName; @endphp"]') ||
              document.getElementById('@php echo $fieldName; @endphp') ||
              document.querySelector('[name="identifier"]');

  // Auto-fill for new records
  @if($isNew)
  if (input && !input.value && generatedId) {
    input.value = generatedId.textContent.trim();
    if (useBtn) {
      useBtn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Applied') }}';
      useBtn.classList.remove('atom-btn-outline-light');
      useBtn.classList.add('atom-btn-outline-success');
    }
  }
  @endif

  if (useBtn && generatedId) {
    useBtn.addEventListener('click', function() {
      if (input) {
        input.value = generatedId.textContent.trim();
        input.focus();

        // Visual feedback
        useBtn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Applied') }}';
        useBtn.classList.remove('atom-btn-outline-light');
        useBtn.classList.add('atom-btn-outline-success');

        setTimeout(function() {
          useBtn.innerHTML = '<i class="fas fa-check me-1"></i>{{ __('Use') }}';
          useBtn.classList.remove('atom-btn-outline-success');
          useBtn.classList.add('atom-btn-outline-light');
        }, 2000);
      }
    });
  }
});
</script>
@endif

@if(!$info['enabled'] || !$info['auto_generate'])
<!-- Numbering scheme not active for this sector -->
@endif
