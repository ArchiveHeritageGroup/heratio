@extends('theme::layouts.1col')
@section('title', __('Add Impairment'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $indicators = [
    'physical_damage' => __('Physical damage'),
    'obsolescence' => __('Obsolescence'),
    'change_in_use' => __('Change in use'),
    'external_factors' => __('External factors'),
  ];
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Add Impairment') }}</h1>
    @include('ahg-heritage-manage::heritage-accounting._asset-header')

    <form method="post" action="{{ $formAction }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Impairment Details') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="assessmentDate">{{ __('Assessment Date') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="date" id="assessmentDate" name="assessment_date" class="form-control" value="{{ old('assessment_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-8">
              <label class="form-label" for="assessorName">{{ __('Assessed By') }}</label>
              <input type="text" id="assessorName" name="assessor_name" class="form-control" maxlength="255" value="{{ old('assessor_name') }}">
            </div>
          </div>

          <h6 class="mt-4">{{ __('Indicators of impairment') }}</h6>
          <div class="row g-3">
            @foreach($indicators as $key => $label)
              <div class="col-md-6">
                <div class="form-check">
                  <input type="checkbox" name="{{ $key }}" class="form-check-input" id="ind_{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
                  <label class="form-check-label" for="ind_{{ $key }}">{{ $label }}</label>
                </div>
                <input type="text" name="{{ $key }}_details" class="form-control form-control-sm mt-1" placeholder="{{ __('Details') }}" aria-label="{{ $label }}: {{ __('Details') }}" value="{{ old($key . '_details') }}">
              </div>
            @endforeach
          </div>

          <h6 class="mt-4">{{ __('Outcome') }}</h6>
          <div class="row g-3">
            <div class="col-md-4">
              <div class="form-check mt-2">
                <input type="checkbox" name="impairment_identified" class="form-check-input" id="impairmentIdentified" value="1" {{ old('impairment_identified') ? 'checked' : '' }}>
                <label class="form-check-label" for="impairmentIdentified">{{ __('Impairment identified') }}</label>
              </div>
              <div class="form-text">{{ __('Carrying amount now: :amt', ['amt' => number_format((float) $asset->current_carrying_amount, 2)]) }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="recoverableAmount">{{ __('Recoverable service amount') }}</label>
              <input type="number" step="0.01" min="0" id="recoverableAmount" name="recoverable_amount" class="form-control" value="{{ old('recoverable_amount') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="impairmentLoss">{{ __('Impairment loss') }}</label>
              <input type="number" step="0.01" min="0" id="impairmentLoss" name="impairment_loss" class="form-control" value="{{ old('impairment_loss') }}">
              <div class="form-text">{{ __('Left blank, it is the carrying amount less the recoverable amount.') }}</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="impairmentNotes">{{ __('Notes') }}</label>
              <textarea id="impairmentNotes" name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-warning"><i class="fas fa-save me-2"></i>{{ __('Save Impairment') }}</button>
        <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>

    @if(count($history))
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Previous assessments') }}</div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Identified') }}</th><th class="text-end">{{ __('Before') }}</th><th class="text-end">{{ __('Loss') }}</th><th class="text-end">{{ __('After') }}</th><th>{{ __('Assessor') }}</th></tr></thead>
          <tbody>
            @foreach($history as $imp)
              <tr>
                <td>{{ $imp->assessment_date }}</td>
                <td>{!! $imp->impairment_identified ? '<span class="badge bg-danger">' . e(__('Yes')) . '</span>' : '<span class="badge bg-success">' . e(__('No')) . '</span>' !!}</td>
                <td class="text-end">{{ number_format((float) $imp->carrying_amount_before, 2) }}</td>
                <td class="text-end text-danger">{{ $imp->impairment_loss !== null ? number_format((float) $imp->impairment_loss, 2) : '-' }}</td>
                <td class="text-end">{{ $imp->carrying_amount_after !== null ? number_format((float) $imp->carrying_amount_after, 2) : '-' }}</td>
                <td>{{ $imp->assessor_name ?: '-' }}</td>
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
