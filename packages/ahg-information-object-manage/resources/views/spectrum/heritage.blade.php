@extends('theme::layouts.1col')
@section('title', 'Heritage Asset — ' . ($io->title ?? ''))

@section('content')
@if(!$asset)
  {{-- No heritage asset data — show create button --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-info-circle fa-3x text-info mb-3"></i>
      <h4>{{ __('No Heritage Asset Data') }}</h4>
      <p class="text-muted mb-4">{{ __('No heritage asset accounting data has been recorded for this item yet.') }}</p>
      <a href="{{ route('heritage.accounting.add', ['io_id' => $io->id]) }}" class="btn btn-success btn-lg">
        <i class="fas fa-plus me-2"></i>{{ __('Create Heritage Asset Record') }}
      </a>
    </div>
  </div>

  <div class="mt-4">
    <a href="{{ url('/' . $io->slug) }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Record') }}
    </a>
  </div>
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
            <dd class="col-sm-7">{!! ($asset->insurance_required ?? false) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' !!}</dd>
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
        <a href="{{ route('heritage.accounting.add-valuation', $asset->id) }}" class="btn btn-success btn-sm">
          <i class="fas fa-plus me-1"></i>{{ __('Add Valuation') }}
        </a>
      </div>
      @if(count($valuations) > 0)
        <table class="table table-striped">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Previous') }}</th><th class="text-end">{{ __('New Value') }}</th><th class="text-end">{{ __('Change') }}</th><th>{{ __('Valuer') }}</th></tr></thead>
          <tbody>
            @foreach($valuations as $v)
              <tr>
                <td>{{ $v->valuation_date ?? '-' }}</td>
                <td>{{ ucfirst($v->valuation_method ?? '-') }}</td>
                <td class="text-end">{{ number_format($v->previous_value ?? 0, 2) }}</td>
                <td class="text-end fw-bold">{{ number_format($v->new_value ?? 0, 2) }}</td>
                <td class="text-end {{ ($v->valuation_change ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ (($v->valuation_change ?? 0) >= 0 ? '+' : '') . number_format($v->valuation_change ?? 0, 2) }}</td>
                <td>{{ $v->valuer_name ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted text-center py-4">{{ __('No valuation history recorded.') }}</p>
      @endif
    </div>

    {{-- Impairments Tab --}}
    <div class="tab-pane fade" id="impairments">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Impairment Assessments') }}</h5>
        <a href="{{ route('heritage.accounting.add-impairment', $asset->id) }}" class="btn btn-success btn-sm">
          <i class="fas fa-plus me-1"></i>{{ __('Add Assessment') }}
        </a>
      </div>
      @if(count($impairments) > 0)
        <table class="table table-striped">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Identified') }}</th><th class="text-end">{{ __('Before') }}</th><th class="text-end">{{ __('Loss') }}</th><th class="text-end">{{ __('After') }}</th><th>{{ __('Assessor') }}</th></tr></thead>
          <tbody>
            @foreach($impairments as $imp)
              <tr>
                <td>{{ $imp->assessment_date ?? '-' }}</td>
                <td>{!! ($imp->impairment_identified ?? false) ? '<span class="badge bg-danger">Yes</span>' : '<span class="badge bg-success">No</span>' !!}</td>
                <td class="text-end">{{ number_format($imp->carrying_amount_before ?? 0, 2) }}</td>
                <td class="text-end text-danger">{{ $imp->impairment_loss ? number_format($imp->impairment_loss, 2) : '-' }}</td>
                <td class="text-end">{{ $imp->carrying_amount_after ? number_format($imp->carrying_amount_after, 2) : '-' }}</td>
                <td>{{ $imp->assessor_name ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted text-center py-4">{{ __('No impairment assessments recorded.') }}</p>
      @endif
    </div>

    {{-- Movements Tab --}}
    <div class="tab-pane fade" id="movements">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Movement Register') }}</h5>
        <a href="{{ route('heritage.accounting.add-movement', $asset->id) }}" class="btn btn-success btn-sm">
          <i class="fas fa-plus me-1"></i>{{ __('Add Movement') }}
        </a>
      </div>
      @if(count($movements) > 0)
        <table class="table table-striped">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Authorized By') }}</th></tr></thead>
          <tbody>
            @foreach($movements as $m)
              <tr>
                <td>{{ $m->movement_date ?? '-' }}</td>
                <td><span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $m->movement_type ?? '-')) }}</span></td>
                <td>{{ $m->from_location ?? '-' }}</td>
                <td>{{ $m->to_location ?? '-' }}</td>
                <td>{{ ucfirst($m->condition_on_departure ?? '-') }}</td>
                <td>{{ $m->authorized_by ?? '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted text-center py-4">{{ __('No movements recorded.') }}</p>
      @endif
    </div>

    {{-- Journals Tab --}}
    <div class="tab-pane fade" id="journals">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">{{ __('Journal Entries') }}</h5>
        <a href="{{ route('heritage.accounting.add-journal', $asset->id) }}" class="btn btn-success btn-sm">
          <i class="fas fa-plus me-1"></i>{{ __('Add Journal') }}
        </a>
      </div>
      @if(count($journals) > 0)
        <table class="table table-striped">
          <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Type') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Posted') }}</th></tr></thead>
          <tbody>
            @foreach($journals as $j)
              <tr>
                <td>{{ $j->journal_date ?? '-' }}</td>
                <td>{{ $j->journal_number ?? '-' }}</td>
                <td><span class="badge bg-secondary">{{ ucfirst($j->journal_type ?? '-') }}</span></td>
                <td>{{ $j->debit_account ?? '' }}</td>
                <td>{{ $j->credit_account ?? '' }}</td>
                <td class="text-end fw-bold">{{ number_format($j->debit_amount ?? 0, 2) }}</td>
                <td>{!! ($j->posted ?? false) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning">No</span>' !!}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <p class="text-muted text-center py-4">{{ __('No journal entries recorded.') }}</p>
      @endif
    </div>
  </div>
@endif
@endsection
