<div class="field mb-3">
  <h3 class="fs-6 fw-semibold text-body-secondary">{{ $label ?? __('Finding aid') }}</h3>
  <div class="findingAidLink">
    <a href="{{ asset($path ?? '') }}" target="_blank">{{ $filename ?? '' }}</a>
  </div>
</div>
