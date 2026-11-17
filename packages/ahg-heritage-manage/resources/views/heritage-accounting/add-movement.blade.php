@extends('theme::layouts.1col')
@section('title', __('Add Movement'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-exchange-alt me-2"></i>{{ __('Add Movement') }}</h1>
    @include('ahg-heritage-manage::heritage-accounting._asset-header')

    <form method="post" action="{{ $formAction }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Movement Details') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="movementDate">{{ __('Movement Date') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="date" id="movementDate" name="movement_date" class="form-control" value="{{ old('movement_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="movementType">{{ __('Movement Type') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select id="movementType" name="movement_type" class="form-select" required>
                <option value="">{{ __('-- Select --') }}</option>
                @foreach($movementTypes as $code => $label)
                  <option value="{{ $code }}" {{ old('movement_type') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="expectedReturn">{{ __('Expected Return Date') }}</label>
              <input type="date" id="expectedReturn" name="expected_return_date" class="form-control" value="{{ old('expected_return_date') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="fromLocation">{{ __('From Location') }}</label>
              <input type="text" id="fromLocation" name="from_location" class="form-control" maxlength="255" value="{{ old('from_location', $asset->current_location) }}">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="toLocation">{{ __('To Location') }}</label>
              <input type="text" id="toLocation" name="to_location" class="form-control" maxlength="255" value="{{ old('to_location') }}">
              <div class="form-text">{{ __('Becomes the asset\'s current location.') }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="authorizedBy">{{ __('Authorized By') }}</label>
              <input type="text" id="authorizedBy" name="authorized_by" class="form-control" maxlength="255" value="{{ old('authorized_by') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="authorizationDate">{{ __('Authorization Date') }}</label>
              <input type="date" id="authorizationDate" name="authorization_date" class="form-control" value="{{ old('authorization_date') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="conditionDeparture">{{ __('Condition on Departure') }}</label>
              <select id="conditionDeparture" name="condition_on_departure" class="form-select">
                <option value="">{{ __('-- Select --') }}</option>
                @foreach($conditions as $code => $label)
                  <option value="{{ $code }}" {{ old('condition_on_departure') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="insuranceValue">{{ __('Insurance Value') }}</label>
              <input type="number" step="0.01" min="0" id="insuranceValue" name="insurance_value" class="form-control" value="{{ old('insurance_value') }}">
            </div>
            <div class="col-md-4">
              <div class="form-check mt-4 pt-2">
                <input type="checkbox" id="insuranceConfirmed" name="insurance_confirmed" class="form-check-input" value="1" {{ old('insurance_confirmed') ? 'checked' : '' }}>
                <label class="form-check-label" for="insuranceConfirmed">{{ __('Insurance confirmed') }}</label>
              </div>
            </div>
            <div class="col-12">
              <label class="form-label" for="movementReason">{{ __('Reason') }}</label>
              <textarea id="movementReason" name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>{{ __('Save Movement') }}</button>
        <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>

    @if(count($history))
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Previous movements') }}</div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Authorized By') }}</th></tr></thead>
          <tbody>
            @foreach($history as $m)
              <tr>
                <td>{{ $m->movement_date }}</td>
                <td><span class="badge bg-info">{{ $movementTypes[$m->movement_type] ?? ucfirst(str_replace('_', ' ', $m->movement_type)) }}</span></td>
                <td>{{ $m->from_location ?: '-' }}</td>
                <td>{{ $m->to_location ?: '-' }}</td>
                <td>{{ $conditions[$m->condition_on_departure] ?? ($m->condition_on_departure ?: '-') }}</td>
                <td>{{ $m->authorized_by ?: '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
