@extends('theme::layouts.1col')
@section('title', ($asset ? __('Heritage Asset') : __('Add Heritage Asset')) . ' — ' . ($io->title ?? ''))

@section('content')
@if(!$asset)
  {{-- No heritage asset — show the add form directly (cloned from AtoM addSuccess) --}}
  <div class="row mb-4">
    <div class="col-12">
      <h1 class="h3 mb-0"><i class="fas fa-plus me-2"></i>{{ __('Add Heritage Asset') }}</h1>
      <p class="text-muted">{{ $io->title ?? $io->slug }}</p>
    </div>
  </div>

  <form method="post" action="{{ route('heritage.accounting.store') }}">
    @csrf
    <input type="hidden" name="information_object_id" value="{{ $io->id }}">
    <div class="row">
      <div class="col-md-8">
        {{-- Basic Information --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Basic Information') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Linked Record') }}</label>
                <div class="form-control bg-light">{{ $io->title ?: 'Untitled' }}</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Accounting Standard') }}</label>
                <select name="accounting_standard_id" class="form-select">
                  <option value="">{{ __('-- Select Standard --') }}</option>
                  @foreach($standards ?? [] as $s)
                    <option value="{{ $s->id }}">{{ $s->code . ' - ' . $s->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Asset Class') }}</label>
                <select name="asset_class_id" class="form-select">
                  <option value="">{{ __('-- Select Class --') }}</option>
                  @foreach($classes ?? [] as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Sub-class') }}</label>
                <input type="text" name="asset_sub_class" class="form-control">
              </div>
            </div>
          </div>
        </div>

        {{-- Recognition --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Recognition') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ __('Recognition Status') }}</label>
                <select name="recognition_status" class="form-select">
                  <option value="pending" selected>{{ __('Pending') }}</option>
                  <option value="recognised">{{ __('Recognised') }}</option>
                  <option value="not_recognised">{{ __('Not Recognised') }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Recognition Date') }}</label>
                <input type="date" name="recognition_date" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Measurement Basis') }}</label>
                <select name="measurement_basis" class="form-select">
                  <option value="cost" selected>{{ __('Cost') }}</option>
                  <option value="fair_value">{{ __('Fair Value') }}</option>
                  <option value="nominal">{{ __('Nominal') }}</option>
                  <option value="not_practicable">{{ __('Not Practicable') }}</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">{{ __('Recognition Status Reason') }}</label>
                <textarea name="recognition_status_reason" class="form-control" rows="2"></textarea>
              </div>
            </div>
          </div>
        </div>

        {{-- Acquisition --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Acquisition') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">{{ __('Acquisition Method') }}</label>
                <select name="acquisition_method" class="form-select">
                  <option value="">{{ __('-- Select --') }}</option>
                  <option value="purchase">{{ __('Purchase') }}</option>
                  <option value="donation">{{ __('Donation') }}</option>
                  <option value="bequest">{{ __('Bequest') }}</option>
                  <option value="transfer">{{ __('Transfer') }}</option>
                  <option value="found">{{ __('Found') }}</option>
                  <option value="exchange">{{ __('Exchange') }}</option>
                  <option value="other">{{ __('Other') }}</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Acquisition Date') }}</label>
                <input type="date" name="acquisition_date" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Acquisition Cost') }}</label>
                <input type="number" step="0.01" name="acquisition_cost" class="form-control" value="0.00">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Fair Value at Acquisition') }}</label>
                <input type="number" step="0.01" name="fair_value_at_acquisition" class="form-control">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Nominal Value') }}</label>
                <input type="number" step="0.01" name="nominal_value" class="form-control" value="1.00">
              </div>
              <div class="col-md-4">
                <label class="form-label">{{ __('Donor Name') }}</label>
                <input type="text" name="donor_name" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label">{{ __('Donor Restrictions') }}</label>
                <textarea name="donor_restrictions" class="form-control" rows="2"></textarea>
              </div>
            </div>
          </div>
        </div>

        {{-- Carrying Amounts --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Carrying Amounts') }}</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">{{ __('Initial Carrying Amount') }}</label>
                <input type="number" step="0.01" name="initial_carrying_amount" class="form-control" value="0.00">
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Current Carrying Amount') }}</label>
                <input type="number" step="0.01" name="current_carrying_amount" class="form-control" value="0.00">
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-4">
        {{-- Heritage Information --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Heritage Information') }}</h5></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Significance') }}</label>
              <select name="heritage_significance" class="form-select">
                <option value="">{{ __('-- Select --') }}</option>
                <option value="exceptional">{{ __('Exceptional') }}</option>
                <option value="high">{{ __('High') }}</option>
                <option value="medium">{{ __('Medium') }}</option>
                <option value="low">{{ __('Low') }}</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Significance Statement') }}</label>
              <textarea name="significance_statement" class="form-control" rows="3"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Current Location') }}</label>
              <input type="text" name="current_location" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Condition') }}</label>
              <select name="condition_rating" class="form-select">
                <option value="">{{ __('-- Select --') }}</option>
                <option value="excellent">{{ __('Excellent') }}</option>
                <option value="good">{{ __('Good') }}</option>
                <option value="fair">{{ __('Fair') }}</option>
                <option value="poor">{{ __('Poor') }}</option>
                <option value="critical">{{ __('Critical') }}</option>
              </select>
            </div>
          </div>
        </div>

        {{-- Insurance --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Insurance') }}</h5></div>
          <div class="card-body">
            <div class="form-check mb-3">
              <input type="checkbox" name="insurance_required" class="form-check-input" value="1" checked>
              <label class="form-check-label">{{ __('Insurance Required') }}</label>
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Insurance Value') }}</label>
              <input type="number" step="0.01" name="insurance_value" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Policy Number') }}</label>
              <input type="text" name="insurance_policy_number" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Provider') }}</label>
              <input type="text" name="insurance_provider" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Expiry Date') }}</label>
              <input type="date" name="insurance_expiry_date" class="form-control">
            </div>
          </div>
        </div>

        {{-- Notes --}}
        <div class="card mb-4">
          <div class="card-header bg-dark text-white"><h5 class="mb-0">{{ __('Notes') }}</h5></div>
          <div class="card-body">
            <textarea name="notes" class="form-control" rows="4"></textarea>
          </div>
        </div>

        <div class="d-grid gap-2">
          <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-2"></i>{{ __('Save Asset') }}</button>
          <a href="{{ url('/' . $io->slug) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
        </div>
      </div>
    </div>
  </form>

@else
  {{-- Heritage asset exists — full view with tabs --}}
  <div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-1">{{ $io->identifier ?? 'N/A' }} - {{ $io->title ?? 'Untitled' }}</h1>
        <p class="text-muted mb-0">{{ $asset->standard_name ?? 'No Standard' }}</p>
      </div>
      <div class="btn-group">
        <a href="{{ url('/' . $io->slug) }}" class="btn btn-primary">
          <i class="fas fa-archive me-1"></i>{{ __('View Record') }}
        </a>
        <a href="{{ route('heritage.accounting.edit', $asset->id) }}" class="btn btn-warning">
          <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
        </a>
        <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}
        </a>
      </div>
    </div>
  </div>

  {{-- Summary Cards --}}
  <div class="row mb-4">
    <div class="col-md-3">
      <div class="card bg-primary text-white">
        <div class="card-body text-center">
          <h6 class="text-white-50">{{ __('Carrying Amount') }}</h6>
          <h3 class="mb-0">{{ number_format($asset->current_carrying_amount ?? 0, 2) }}</h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body text-center">
          <h6 class="text-muted">{{ __('Status') }}</h6>
          @php
          $statusColors = ['recognised' => 'success', 'not_recognised' => 'secondary', 'pending' => 'warning', 'derecognised' => 'danger'];
          $color = $statusColors[$asset->recognition_status ?? ''] ?? 'secondary';
          @endphp
          <h4><span class="badge bg-{{ $color }}">{{ ucfirst(str_replace('_', ' ', $asset->recognition_status ?? 'pending')) }}</span></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body text-center">
          <h6 class="text-muted">{{ __('Standard') }}</h6>
          <h4 class="mb-0">{{ $asset->standard_code ?? 'Not Set' }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card">
        <div class="card-body text-center">
          <h6 class="text-muted">{{ __('Asset Class') }}</h6>
          <h5 class="mb-0">{{ $asset->class_name ?? 'Unclassified' }}</h5>
        </div>
      </div>
    </div>
  </div>

  {{-- Tabs --}}
  <ul class="nav nav-tabs" id="assetTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#details">{{ __('Details') }}</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#valuations">{{ __('Valuations') }} <span class="badge bg-secondary">{{ count($valuations) }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#impairments">{{ __('Impairments') }} <span class="badge bg-secondary">{{ count($impairments) }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#movements">{{ __('Movements') }} <span class="badge bg-secondary">{{ count($movements) }}</span></a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#journals">{{ __('Journal Entries') }} <span class="badge bg-secondary">{{ count($journals) }}</span></a></li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom p-4 bg-white">
    {{-- Details Tab --}}
    <div class="tab-pane fade show active" id="details">
      <div class="row">
        <div class="col-md-6">
          <h5 class="border-bottom pb-2 mb-3">{{ __('Recognition & Measurement') }}</h5>
          <dl class="row">
            <dt class="col-sm-5">{{ __('Recognition Date') }}</dt>
            <dd class="col-sm-7">{{ $asset->recognition_date ?? '-' }}</dd>
            <dt class="col-sm-5">{{ __('Measurement Basis') }}</dt>
            <dd class="col-sm-7">{{ ucfirst($asset->measurement_basis ?? '-') }}</dd>
            <dt class="col-sm-5">{{ __('Acquisition Method') }}</dt>
            <dd class="col-sm-7">{{ ucfirst($asset->acquisition_method ?? '-') }}</dd>
            <dt class="col-sm-5">{{ __('Acquisition Date') }}</dt>
            <dd class="col-sm-7">{{ $asset->acquisition_date ?? '-' }}</dd>
            <dt class="col-sm-5">{{ __('Acquisition Cost') }}</dt>
            <dd class="col-sm-7">{{ number_format($asset->acquisition_cost ?? 0, 2) }}</dd>
            <dt class="col-sm-5">{{ __('Fair Value at Acquisition') }}</dt>
            <dd class="col-sm-7">{{ $asset->fair_value_at_acquisition ? number_format($asset->fair_value_at_acquisition, 2) : '-' }}</dd>
          </dl>

          <h5 class="border-bottom pb-2 mb-3 mt-4">{{ __('Current Values') }}</h5>
          <dl class="row">
            <dt class="col-sm-5">{{ __('Initial Carrying Amount') }}</dt>
            <dd class="col-sm-7">{{ number_format($asset->initial_carrying_amount ?? 0, 2) }}</dd>
            <dt class="col-sm-5">{{ __('Current Carrying Amount') }}</dt>
            <dd class="col-sm-7 fw-bold text-primary">{{ number_format($asset->current_carrying_amount ?? 0, 2) }}</dd>
            <dt class="col-sm-5">{{ __('Accumulated Depreciation') }}</dt>
            <dd class="col-sm-7">{{ number_format($asset->accumulated_depreciation ?? 0, 2) }}</dd>
            <dt class="col-sm-5">{{ __('Revaluation Surplus') }}</dt>
            <dd class="col-sm-7">{{ number_format($asset->revaluation_surplus ?? 0, 2) }}</dd>
            <dt class="col-sm-5">{{ __('Impairment Loss') }}</dt>
            <dd class="col-sm-7">{{ number_format($asset->impairment_loss ?? 0, 2) }}</dd>
          </dl>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2 mb-3">{{ __('Heritage Information') }}</h5>
          <dl class="row">
            <dt class="col-sm-5">{{ __('Significance') }}</dt>
            <dd class="col-sm-7">{{ ucfirst($asset->heritage_significance ?? '-') }}</dd>
            <dt class="col-sm-5">{{ __('Location') }}</dt>
            <dd class="col-sm-7">{{ $asset->current_location ?? '-' }}</dd>
            <dt class="col-sm-5">{{ __('Condition') }}</dt>
            <dd class="col-sm-7">{{ ucfirst($asset->condition_rating ?? '-') }}</dd>
            <dt class="col-sm-5">{{ __('Donor') }}</dt>
            <dd class="col-sm-7">{{ $asset->donor_name ?? '-' }}</dd>
          </dl>

          <h5 class="border-bottom pb-2 mb-3 mt-4">{{ __('Insurance') }}</h5>
          <dl class="row">
            <dt class="col-sm-5">{{ __('Insurance Required') }}</dt>
            <dd class="col-sm-7">{!! ($asset->insurance_required ?? false) ? '<span class="badge bg-success">{{ __('Yes') }}</span>' : '<span class="badge bg-secondary">No</span>' !!}</dd>
            <dt class="col-sm-5">{{ __('Insurance Value') }}</dt>
            <dd class="col-sm-7">{{ $asset->insurance_value ? number_format($asset->insurance_value, 2) : '-' }}</dd>
            <dt class="col-sm-5">{{ __('Policy Number') }}</dt>
            <dd class="col-sm-7">{{ $asset->insurance_policy_number ?? '-' }}</dd>
            <dt class="col-sm-5">{{ __('Provider') }}</dt>
            <dd class="col-sm-7">{{ $asset->insurance_provider ?? '-' }}</dd>
            <dt class="col-sm-5">{{ __('Expiry Date') }}</dt>
            <dd class="col-sm-7">{{ $asset->insurance_expiry_date ?? '-' }}</dd>
          </dl>
        </div>
      </div>
    </div>

    {{-- Valuations Tab --}}
    <div class="tab-pane fade" id="valuations">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Valuation History') }}</h5>
        <a href="{{ route('heritage.accounting.add-valuation', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Valuation') }}</a>
      </div>
      @if(count($valuations) > 0)
        <table class="table table-striped"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Previous') }}</th><th class="text-end">{{ __('New Value') }}</th><th class="text-end">{{ __('Change') }}</th><th>{{ __('Valuer') }}</th></tr></thead>
        <tbody>@foreach($valuations as $v)<tr><td>{{ $v->valuation_date ?? '-' }}</td><td>{{ ucfirst($v->valuation_method ?? '-') }}</td><td class="text-end">{{ number_format($v->previous_value ?? 0, 2) }}</td><td class="text-end fw-bold">{{ number_format($v->new_value ?? 0, 2) }}</td><td class="text-end {{ ($v->valuation_change ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ (($v->valuation_change ?? 0) >= 0 ? '+' : '') . number_format($v->valuation_change ?? 0, 2) }}</td><td>{{ $v->valuer_name ?? '-' }}</td></tr>@endforeach</tbody></table>
      @else
        <p class="text-muted text-center py-4">{{ __('No valuation history recorded.') }}</p>
      @endif
    </div>

    {{-- Impairments Tab --}}
    <div class="tab-pane fade" id="impairments">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Impairment Assessments') }}</h5>
        <a href="{{ route('heritage.accounting.add-impairment', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Assessment') }}</a>
      </div>
      @if(count($impairments) > 0)
        <table class="table table-striped"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Identified') }}</th><th class="text-end">{{ __('Before') }}</th><th class="text-end">{{ __('Loss') }}</th><th class="text-end">{{ __('After') }}</th><th>{{ __('Assessor') }}</th></tr></thead>
        <tbody>@foreach($impairments as $imp)<tr><td>{{ $imp->assessment_date ?? '-' }}</td><td>{!! ($imp->impairment_identified ?? false) ? '<span class="badge bg-danger">{{ __('Yes') }}</span>' : '<span class="badge bg-success">No</span>' !!}</td><td class="text-end">{{ number_format($imp->carrying_amount_before ?? 0, 2) }}</td><td class="text-end text-danger">{{ $imp->impairment_loss ? number_format($imp->impairment_loss, 2) : '-' }}</td><td class="text-end">{{ $imp->carrying_amount_after ? number_format($imp->carrying_amount_after, 2) : '-' }}</td><td>{{ $imp->assessor_name ?? '-' }}</td></tr>@endforeach</tbody></table>
      @else
        <p class="text-muted text-center py-4">{{ __('No impairment assessments recorded.') }}</p>
      @endif
    </div>

    {{-- Movements Tab --}}
    <div class="tab-pane fade" id="movements">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Movement Register') }}</h5>
        <a href="{{ route('heritage.accounting.add-movement', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Movement') }}</a>
      </div>
      @if(count($movements) > 0)
        <table class="table table-striped"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Authorized By') }}</th></tr></thead>
        <tbody>@foreach($movements as $m)<tr><td>{{ $m->movement_date ?? '-' }}</td><td><span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $m->movement_type ?? '-')) }}</span></td><td>{{ $m->from_location ?? '-' }}</td><td>{{ $m->to_location ?? '-' }}</td><td>{{ ucfirst($m->condition_on_departure ?? '-') }}</td><td>{{ $m->authorized_by ?? '-' }}</td></tr>@endforeach</tbody></table>
      @else
        <p class="text-muted text-center py-4">{{ __('No movements recorded.') }}</p>
      @endif
    </div>

    {{-- Journals Tab --}}
    <div class="tab-pane fade" id="journals">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Journal Entries') }}</h5>
        <a href="{{ route('heritage.accounting.add-journal', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Journal') }}</a>
      </div>
      @if(count($journals) > 0)
        <table class="table table-striped"><thead><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Type') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Posted') }}</th></tr></thead>
        <tbody>@foreach($journals as $j)<tr><td>{{ $j->journal_date ?? '-' }}</td><td>{{ $j->journal_number ?? '-' }}</td><td><span class="badge bg-secondary">{{ ucfirst($j->journal_type ?? '-') }}</span></td><td>{{ $j->debit_account ?? '' }}</td><td>{{ $j->credit_account ?? '' }}</td><td class="text-end fw-bold">{{ number_format($j->debit_amount ?? 0, 2) }}</td><td>{!! ($j->posted ?? false) ? '<span class="badge bg-success">{{ __('Yes') }}</span>' : '<span class="badge bg-warning">No</span>' !!}</td></tr>@endforeach</tbody></table>
      @else
        <p class="text-muted text-center py-4">{{ __('No journal entries recorded.') }}</p>
      @endif
    </div>
  </div>
@endif
@endsection
