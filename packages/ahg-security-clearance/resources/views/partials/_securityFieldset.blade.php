{{-- Security classification fieldset for edit forms --}}
{{-- Usage: @include('ahg-security-clearance::partials._securityFieldset', ['objectId' => $id, 'currentClassification' => $classification, 'classifications' => $levels]) --}}
<div class="accordion mb-3" id="securityClassificationAccordion">
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#securityFieldsetBody">
        <i class="fas fa-shield-alt me-2"></i> Security Classification
        @if(!empty($currentClassification))
          <span class="badge ms-2" style="background-color: {{ $currentClassification->color ?? '#666' }}">{{ e($currentClassification->name ?? '') }}</span>
        @else
          <span class="badge bg-secondary ms-2">{{ __('Public') }}</span>
        @endif
      </button>
    </h2>
    <div id="securityFieldsetBody" class="accordion-collapse collapse">
      <div class="accordion-body">
        <div class="mb-3">
          <label class="form-label">{{ __('Classification Level') }}</label>
          <select name="security_classification_id" class="form-select">
            <option value="">{{ __('Public (no classification)') }}</option>
            @foreach($classifications ?? [] as $cl)
              <option value="{{ $cl->id }}" {{ ($currentClassification->id ?? 0) == $cl->id ? 'selected' : '' }}
                      style="color: {{ $cl->color ?? '#333' }}">
                {{ e($cl->name) }} (Level {{ $cl->level }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">{{ __('Classification Reason') }}</label>
          <textarea name="security_reason" class="form-control" rows="2">{{ $currentClassification->reason ?? '' }}</textarea>
        </div>
        @if(!empty($objectId))
          <a href="{{ route('security-clearance.classify', ['id' => $objectId]) }}" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-lock"></i> {{ __('Full Classification') }}
          </a>
        @endif
      </div>
    </div>
  </div>
</div>
