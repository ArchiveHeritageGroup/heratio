@extends('theme::layouts.1col')
@section('title', __('Carrying Amount Roll-forward'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1 class="h3 mb-1"><i class="fas fa-stream me-2"></i>{{ __('Carrying Amount Roll-forward') }}</h1>
    <p class="text-muted">{{ __($profile->title()) }}@if($currency) - {{ __('Amounts in :c.', ['c' => $currency]) }}@endif</p>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <form method="get" class="row g-2 mb-3 d-print-none">
      <div class="col-md-3"><label class="form-label" for="rfFrom">{{ __('From') }}</label><input type="date" id="rfFrom" name="from" value="{{ $filters['from'] }}" class="form-control" required></div>
      <div class="col-md-3"><label class="form-label" for="rfTo">{{ __('To') }}</label><input type="date" id="rfTo" name="to" value="{{ $filters['to'] }}" class="form-control" required></div>
      <div class="col-md-2">
        <label class="form-label" for="rfStandard">{{ __('Standard') }}</label>
        <select id="rfStandard" name="standard_id" class="form-select">
          <option value="">{{ __('All') }}</option>
          @foreach($standards as $s)<option value="{{ $s->id }}" @selected($filters['standard_id'] == $s->id)>{{ $s->code }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="rfClass">{{ __('Class') }}</label>
        <select id="rfClass" name="class_id" class="form-select">
          <option value="">{{ __('All') }}</option>
          @foreach($classes as $c)<option value="{{ $c->id }}" @selected($filters['class_id'] == $c->id)>{{ $c->name }}</option>@endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn atom-btn-white w-100">{{ __('Run') }}</button></div>
    </form>

    @if($result['reconciled'])
      <div class="alert alert-success">{{ __('Every asset on the register agrees with its dated ledgers.') }}</div>
    @endif
    @if(count($result['unreconciled']) > 0)
      <div class="alert alert-warning">{{ __(':n asset(s) have a register amount the dated ledgers do not explain. Resolve these before relying on this reconciliation.', ['n' => count($result['unreconciled'])]) }}</div>
    @endif
    @if(count($result['ledger_only']) > 0)
      <div class="alert alert-warning">{{ __(':n movement(s) on the reserve ledger have no matching register entry and are not included below.', ['n' => count($result['ledger_only'])]) }}</div>
    @endif

    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ $result['period_start'] }} - {{ $result['period_end'] }}</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <tbody>
            @foreach($profile->lineLabels() as $key => $label)
              <tr class="{{ in_array($key, ['opening', 'closing']) ? 'fw-bold' : '' }}"><th scope="row" class="fw-normal">{{ __($label) }}</th><td class="text-end">{{ number_format($result['lines'][$key], 2) }}</td></tr>
            @endforeach
            <tr><th scope="row" class="fw-normal">{{ __('Recognised assets at period end') }}</th><td class="text-end">{{ number_format($result['asset_count']) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    @if(count($result['by_class']) > 0)
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('By asset class') }}</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light"><tr><th>{{ __('Class') }}</th><th class="text-end">{{ __('Opening') }}</th><th class="text-end">{{ __('Additions') }}</th><th class="text-end">{{ __('Revaluation') }}</th><th class="text-end">{{ __('Impairment (net)') }}</th><th class="text-end">{{ __('Disposals') }}</th><th class="text-end">{{ __('Closing') }}</th></tr></thead>
          <tbody>
            @foreach($result['by_class'] as $row)
              <tr><td>{{ $row['class'] }}</td><td class="text-end">{{ number_format($row['opening'], 2) }}</td><td class="text-end">{{ number_format($row['additions'], 2) }}</td><td class="text-end">{{ number_format($row['revaluation_increase'] + $row['revaluation_decrease'], 2) }}</td><td class="text-end">{{ number_format($row['impairment'] + $row['reversal'], 2) }}</td><td class="text-end">{{ number_format($row['disposals'], 2) }}</td><td class="text-end">{{ number_format($row['closing'], 2) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    @if(count($result['ledger_only']) > 0)
    <div class="card mb-3">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Reserve ledger movements without a register entry') }}</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light"><tr><th>{{ __('Asset') }}</th><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Amount') }}</th></tr></thead>
          <tbody>
            @foreach(array_slice($result['ledger_only'], 0, 200) as $m)
              <tr><td><a href="{{ route('heritage.accounting.view', $m['asset_id']) }}">#{{ $m['asset_id'] }}</a></td><td>{{ $m['date'] }}</td><td>{{ str_replace('_', ' ', $m['type']) }}</td><td class="text-end">{{ number_format($m['amount'], 2) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif

    @if(count($result['unreconciled']) > 0)
    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Unreconciled assets') }}</div>
      <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
          <thead class="table-light"><tr><th>{{ __('Asset') }}</th><th class="text-end">{{ __('Register') }}</th><th class="text-end">{{ __('Ledgers') }}</th><th class="text-end">{{ __('Difference') }}</th></tr></thead>
          <tbody>
            @foreach(array_slice($result['unreconciled'], 0, 200) as $u)
              <tr><td><a href="{{ route('heritage.accounting.view', $u['asset_id']) }}">#{{ $u['asset_id'] }}</a></td><td class="text-end">{{ number_format($u['register'], 2) }}</td><td class="text-end">{{ number_format($u['ledger'], 2) }}</td><td class="text-end">{{ number_format($u['difference'], 2) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @endif
  </div>
</div>
@endsection
