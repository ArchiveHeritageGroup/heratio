<div class="card mb-3">
  <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-filter me-2"></i>{{ __('Filters') }}</div>
  <div class="card-body">
    <form method="get" action="{{ $action }}">
      @if(isset($cultures))
      <div class="mb-3">
        <label class="form-label">Culture <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <select name="culture" class="form-select form-select-sm">
          @foreach($cultures as $c)
            <option value="{{ $c }}" {{ ($params['culture'] ?? 'en') === $c ? 'selected' : '' }}>{{ $c }}</option>
          @endforeach
        </select>
      </div>
      @endif
      <div class="mb-3">
        <label class="form-label">Date start <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <input type="date" name="dateStart" class="form-control form-control-sm" value="{{ $params['dateStart'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Date end <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <input type="date" name="dateEnd" class="form-control form-control-sm" value="{{ $params['dateEnd'] ?? '' }}">
      </div>
      <div class="mb-3">
        <label class="form-label">Date of <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <select name="dateOf" class="form-select form-select-sm">
          <option value="created_at" {{ ($params['dateOf'] ?? '') === 'created_at' ? 'selected' : '' }}>{{ __('Created') }}</option>
          <option value="updated_at" {{ ($params['dateOf'] ?? '') === 'updated_at' ? 'selected' : '' }}>{{ __('Updated') }}</option>
        </select>
      </div>
      @if(isset($extraFilters))
        {!! $extraFilters !!}
      @endif
      <div class="mb-3">
        <label class="form-label">Results per page <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
        <select name="limit" class="form-select form-select-sm">
          @foreach([10, 20, 50, 100] as $l)
            <option value="{{ $l }}" {{ ($params['limit'] ?? 20) == $l ? 'selected' : '' }}>{{ $l }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn atom-btn-outline-light btn-sm w-100"><i class="fas fa-search me-1"></i>{{ __('Filter') }}</button>
    </form>
  </div>
</div>
