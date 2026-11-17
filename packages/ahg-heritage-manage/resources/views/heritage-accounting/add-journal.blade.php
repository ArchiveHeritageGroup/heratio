@extends('theme::layouts.1col')
@section('title', __('Add Journal Entry'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-book me-2"></i>{{ __('Add Journal Entry') }}</h1>
    @include('ahg-heritage-manage::heritage-accounting._asset-header')

    <form method="post" action="{{ $formAction }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Journal Entry Details') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label" for="journalDate">{{ __('Journal Date') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="date" id="journalDate" name="journal_date" class="form-control" value="{{ old('journal_date', date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="journalType">{{ __('Journal Type') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <select id="journalType" name="journal_type" class="form-select" required>
                <option value="">{{ __('-- Select --') }}</option>
                @foreach($journalTypes as $code => $label)
                  <option value="{{ $code }}" {{ old('journal_type') === $code ? 'selected' : '' }}>{{ __($label) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="journalNumber">{{ __('Journal Number') }}</label>
              <input type="text" id="journalNumber" name="journal_number" class="form-control" maxlength="50" value="{{ old('journal_number') }}">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="debitAccount">{{ __('Debit Account') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" id="debitAccount" name="debit_account" class="form-control" maxlength="50" value="{{ old('debit_account') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="creditAccount">{{ __('Credit Account') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="text" id="creditAccount" name="credit_account" class="form-control" maxlength="50" value="{{ old('credit_account') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="debitAmount">{{ __('Debit Amount') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="number" step="0.01" min="0" id="debitAmount" name="debit_amount" class="form-control" value="{{ old('debit_amount') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="creditAmount">{{ __('Credit Amount') }} <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
              <input type="number" step="0.01" min="0" id="creditAmount" name="credit_amount" class="form-control" value="{{ old('credit_amount') }}" required>
              <div class="form-text">{{ __('Must equal the debit amount.') }}</div>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fiscalYear">{{ __('Fiscal Year') }}</label>
              <input type="number" id="fiscalYear" name="fiscal_year" class="form-control" min="1900" max="2999" value="{{ old('fiscal_year', date('Y')) }}">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="fiscalPeriod">{{ __('Fiscal Period') }}</label>
              <input type="number" id="fiscalPeriod" name="fiscal_period" class="form-control" min="1" max="13" value="{{ old('fiscal_period') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="referenceDocument">{{ __('Reference Document') }}</label>
              <input type="text" id="referenceDocument" name="reference_document" class="form-control" maxlength="255" value="{{ old('reference_document') }}">
            </div>
            <div class="col-12">
              <label class="form-label" for="journalDescription">{{ __('Description') }}</label>
              <textarea id="journalDescription" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>{{ __('Save Journal Entry') }}</button>
        <a href="{{ route('heritage.accounting.view', $asset->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
      </div>
    </form>

    @if(count($history))
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Previous journal entries') }}</div>
      <div class="card-body p-0">
        <table class="table table-bordered table-sm mb-0">
          <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Type') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @foreach($history as $j)
              <tr>
                <td>{{ $j->journal_date }}</td>
                <td>{{ $j->journal_number ?: '-' }}</td>
                <td><span class="badge bg-secondary">{{ $journalTypes[$j->journal_type] ?? $j->journal_type }}</span></td>
                <td>{{ $j->debit_account }}</td>
                <td>{{ $j->credit_account }}</td>
                <td class="text-end">{{ number_format((float) $j->debit_amount, 2) }}</td>
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
