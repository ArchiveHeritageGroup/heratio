@extends('theme::layouts.1col')
@section('title', __('Heritage Asset - Accounting Record'))
@section('body-class', 'admin heritage')

@php
    // Heritage-asset accounting record, grouped by lifecycle. Each row:
    // [label, field, type] where type in text|long|money|date|int|bool.
    $sections = [
        'Identification & classification' => [
            ['Asset class', 'asset_class_name', 'text'],
            ['Asset sub-class', 'asset_sub_class', 'text'],
            ['Accounting standard', 'standard_code', 'text'],
            ['Standard name', 'standard_name', 'text'],
            ['Heritage significance', 'heritage_significance', 'dd:heritage_significance'],
            ['Significance statement', 'significance_statement', 'long'],
        ],
        'Recognition' => [
            ['Recognition status', 'recognition_status', 'dd:recognition_status'],
            ['Reason', 'recognition_status_reason', 'long'],
            ['Recognition date', 'recognition_date', 'date'],
            ['Measurement basis', 'measurement_basis', 'dd:measurement_basis'],
        ],
        'Acquisition & initial measurement' => [
            ['Acquisition method', 'acquisition_method', 'dd:acquisition_method'],
            ['Acquisition date', 'acquisition_date', 'date'],
            ['Acquisition cost', 'acquisition_cost', 'money'],
            ['Fair value at acquisition', 'fair_value_at_acquisition', 'money'],
            ['Nominal value', 'nominal_value', 'money'],
            ['Donor name', 'donor_name', 'text'],
            ['Donor restrictions', 'donor_restrictions', 'long'],
            ['Initial carrying amount', 'initial_carrying_amount', 'money'],
        ],
        'Carrying amount & valuation' => [
            ['Current carrying amount', 'current_carrying_amount', 'money'],
            ['Last valuation date', 'last_valuation_date', 'date'],
            ['Last valuation amount', 'last_valuation_amount', 'money'],
            ['Valuation method', 'valuation_method', 'dd:valuation_method'],
            ['Valuer', 'valuer_name', 'text'],
            ['Valuer credentials', 'valuer_credentials', 'text'],
            ['Valuation report reference', 'valuation_report_reference', 'text'],
            ['Revaluation frequency', 'revaluation_frequency', 'dd:revaluation_frequency'],
            ['Revaluation surplus', 'revaluation_surplus', 'money'],
        ],
        'Depreciation' => [
            ['Depreciation policy', 'depreciation_policy', 'long'],
            ['Useful life (years)', 'useful_life_years', 'int'],
            ['Residual value', 'residual_value', 'money'],
            ['Annual depreciation', 'annual_depreciation', 'money'],
            ['Accumulated depreciation', 'accumulated_depreciation', 'money'],
        ],
        'Impairment' => [
            ['Last impairment assessment', 'last_impairment_date', 'date'],
            ['Impairment indicators', 'impairment_indicators', 'text'],
            ['Indicator details', 'impairment_indicators_details', 'long'],
            ['Impairment loss', 'impairment_loss', 'money'],
            ['Recoverable amount', 'recoverable_amount', 'money'],
        ],
        'Derecognition' => [
            ['Derecognition date', 'derecognition_date', 'date'],
            ['Reason', 'derecognition_reason', 'long'],
            ['Proceeds', 'derecognition_proceeds', 'money'],
            ['Gain / loss on derecognition', 'gain_loss_on_derecognition', 'money'],
        ],
        'Restrictions & conservation' => [
            ['Restrictions on use', 'restrictions_on_use', 'long'],
            ['Restrictions on disposal', 'restrictions_on_disposal', 'long'],
            ['Conservation requirements', 'conservation_requirements', 'long'],
        ],
        'Insurance' => [
            ['Insurance required', 'insurance_required', 'bool'],
            ['Insured value', 'insurance_value', 'money'],
            ['Policy number', 'insurance_policy_number', 'text'],
            ['Provider', 'insurance_provider', 'text'],
            ['Policy expiry', 'insurance_expiry_date', 'date'],
        ],
        'Location & condition' => [
            ['Current location', 'current_location', 'text'],
            ['Storage conditions', 'storage_conditions', 'long'],
            ['Condition rating', 'condition_rating', 'dd:condition_rating'],
            ['Last condition assessment', 'last_condition_assessment', 'date'],
        ],
        'Audit' => [
            ['Notes', 'notes', 'long'],
            ['Approved by', 'approved_by', 'text'],
            ['Approved date', 'approved_date', 'date'],
            ['Created', 'created_at', 'date'],
            ['Last updated', 'updated_at', 'date'],
        ],
    ];

    $fmt = function ($val, $type) {
        if ($val === null || $val === '') {
            return '<span class="text-muted">-</span>';
        }
        if (str_starts_with($type, 'dd:')) {
            $labels = \AhgCore\Support\DropdownOptions::resolve(substr($type, 3), []);
            return e(__($labels[$val] ?? ucwords(str_replace('_', ' ', (string) $val))));
        }
        switch ($type) {
            case 'money':
                return number_format((float) $val, 2);
            case 'date':
                try { return e(\Illuminate\Support\Carbon::parse($val)->format('d M Y')); }
                catch (\Throwable $e) { return e((string) $val); }
            case 'int':
                return e((string) (int) $val);
            case 'bool':
                return ((int) $val === 1 || $val === true || $val === 'yes')
                    ? '<span class="badge bg-success">Yes</span>'
                    : '<span class="badge bg-secondary">No</span>';
            case 'long':
                return nl2br(e((string) $val));
            default:
                return e((string) $val);
        }
    };
@endphp

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h1 class="h3 mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>{{ __('Heritage Asset - Accounting Record') }}</h1>
        @if($io)
          <div class="text-muted">
            @if($io->slug)
              <a href="{{ url('/' . $io->slug) }}">{{ $io->title ?: $io->slug }}</a>
            @else
              {{ $io->title ?: ('#' . $asset->id) }}
            @endif
            @if($io->identifier)<span class="ms-2 badge bg-light text-dark">{{ $io->identifier }}</span>@endif
          </div>
        @endif
      </div>
      <div class="d-flex flex-wrap gap-2 justify-content-end">
        @if(Route::has('heritage.accounting.add-valuation'))
          <a href="{{ route('heritage.accounting.add-valuation', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-dollar-sign me-1"></i>{{ __('Add valuation') }}</a>
        @endif
        <a href="{{ route('heritage.accounting.add-impairment', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-exclamation-triangle me-1"></i>{{ __('Add impairment') }}</a>
        <a href="{{ route('heritage.accounting.add-movement', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-exchange-alt me-1"></i>{{ __('Add movement') }}</a>
        <a href="{{ route('heritage.accounting.add-journal', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-book me-1"></i>{{ __('Add journal') }}</a>
        @if($asset->standard_code === 'GRAP103')
          <a href="{{ route('heritage.grap.check', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-clipboard-check me-1"></i>{{ __('Compliance check') }}</a>
        @endif
        @if(Route::has('heritage.accounting.edit'))
          <a href="{{ route('heritage.accounting.edit', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-pen me-1"></i>{{ __('Edit') }}</a>
        @endif
        @if(Route::has('heritage.accounting.browse'))
          <a href="{{ route('heritage.accounting.browse') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-list me-1"></i>{{ __('All assets') }}</a>
        @endif
      </div>
    </div>

    {{-- Headline figures --}}
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <h6 class="text-muted mb-1">{{ __('Current carrying amount') }}</h6>
          <div class="h4 mb-0">{{ number_format((float) $asset->current_carrying_amount, 2) }} @if($currency)<small class="text-muted">{{ $currency }}</small>@endif</div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <h6 class="text-muted mb-1">{{ __('Recognition status') }}</h6>
          <div class="h5 mb-0">{{ ucwords(str_replace('_', ' ', (string) $asset->recognition_status)) ?: '-' }}</div>
        </div></div>
      </div>
      <div class="col-md-4">
        <div class="card h-100"><div class="card-body">
          <h6 class="text-muted mb-1">{{ __('Accumulated depreciation') }}</h6>
          <div class="h5 mb-0">{{ number_format((float) $asset->accumulated_depreciation, 2) }}</div>
        </div></div>
      </div>
    </div>

    @foreach($sections as $title => $rows)
      <div class="card mb-3">
        <div class="card-header" style="background:#10373E;color:#fff">{{ __($title) }}</div>
        <div class="card-body p-0">
          <table class="table table-bordered table-sm mb-0">
            <tbody>
              @foreach($rows as [$label, $field, $type])
                <tr>
                  <th style="width:34%;background:#f6f8f8">{{ __($label) }}</th>
                  <td>{!! $fmt($asset->{$field} ?? null, $type) !!}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endforeach

    {{-- Sub-ledgers --}}
    @php
      $ledgerTabs = [
        'valuations' => [__('Valuations'), count($valuations)],
        'impairments' => [__('Impairments'), count($impairments)],
        'movements' => [__('Movements'), count($movements)],
        'journals' => [__('Journal Entries'), count($journals)],
      ];
      $yn = fn ($v) => $v ? '<span class="badge bg-success">' . e(__('Yes')) . '</span>' : '<span class="badge bg-secondary">' . e(__('No')) . '</span>';
    @endphp
    <ul class="nav nav-tabs mt-4" role="tablist">
      @foreach($ledgerTabs as $key => [$label, $n])
        <li class="nav-item" role="presentation">
          <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#ledger-{{ $key }}" type="button" role="tab">{{ $label }} <span class="badge bg-secondary">{{ $n }}</span></button>
        </li>
      @endforeach
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3 mb-4">
      <div class="tab-pane fade show active" id="ledger-valuations" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ __('Valuation History') }}</h5>
          <a href="{{ route('heritage.accounting.add-valuation', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Valuation') }}</a>
        </div>
        @if(count($valuations))
          <table class="table table-bordered table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-end">{{ __('Previous') }}</th><th class="text-end">{{ __('New Value') }}</th><th class="text-end">{{ __('Change') }}</th><th>{{ __('Valuer') }}</th></tr></thead>
            <tbody>
              @foreach($valuations as $v)
                <tr>
                  <td>{{ $v->valuation_date }}</td>
                  <td>{{ $v->valuation_method ? ucfirst(str_replace('_', ' ', $v->valuation_method)) : '-' }}</td>
                  <td class="text-end">{{ number_format((float) $v->previous_value, 2) }}</td>
                  <td class="text-end fw-bold">{{ number_format((float) $v->new_value, 2) }}</td>
                  <td class="text-end {{ (float) $v->valuation_change >= 0 ? 'text-success' : 'text-danger' }}">{{ ((float) $v->valuation_change >= 0 ? '+' : '') . number_format((float) $v->valuation_change, 2) }}</td>
                  <td>{{ $v->valuer_name ?: '-' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted text-center py-3 mb-0">{{ __('No valuation history recorded.') }}</p>
        @endif
      </div>
      <div class="tab-pane fade" id="ledger-impairments" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ __('Impairment Assessments') }}</h5>
          <a href="{{ route('heritage.accounting.add-impairment', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Assessment') }}</a>
        </div>
        @if(count($impairments))
          <table class="table table-bordered table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Identified') }}</th><th class="text-end">{{ __('Before') }}</th><th class="text-end">{{ __('Loss') }}</th><th class="text-end">{{ __('After') }}</th><th>{{ __('Assessor') }}</th></tr></thead>
            <tbody>
              @foreach($impairments as $imp)
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
        @else
          <p class="text-muted text-center py-3 mb-0">{{ __('No impairment assessments recorded.') }}</p>
        @endif
      </div>
      <div class="tab-pane fade" id="ledger-movements" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ __('Movement Register') }}</h5>
          <a href="{{ route('heritage.accounting.add-movement', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Movement') }}</a>
        </div>
        @if(count($movements))
          <table class="table table-bordered table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th><th>{{ __('Condition') }}</th><th>{{ __('Authorized By') }}</th><th>{{ __('Insured') }}</th></tr></thead>
            <tbody>
              @foreach($movements as $m)
                <tr>
                  <td>{{ $m->movement_date }}</td>
                  <td><span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', $m->movement_type)) }}</span></td>
                  <td>{{ $m->from_location ?: '-' }}</td>
                  <td>{{ $m->to_location ?: '-' }}</td>
                  <td>{{ $m->condition_on_departure ? ucfirst($m->condition_on_departure) : '-' }}</td>
                  <td>{{ $m->authorized_by ?: '-' }}</td>
                  <td>{!! $yn($m->insurance_confirmed) !!}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted text-center py-3 mb-0">{{ __('No movements recorded.') }}</p>
        @endif
      </div>
      <div class="tab-pane fade" id="ledger-journals" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="mb-0">{{ __('Journal Entries') }}</h5>
          <a href="{{ route('heritage.accounting.add-journal', $asset->id) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Journal') }}</a>
        </div>
        @if(count($journals))
          <table class="table table-bordered table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Number') }}</th><th>{{ __('Type') }}</th><th>{{ __('Debit') }}</th><th>{{ __('Credit') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Posted') }}</th></tr></thead>
            <tbody>
              @foreach($journals as $j)
                <tr>
                  <td>{{ $j->journal_date }}</td>
                  <td>{{ $j->journal_number ?: '-' }}</td>
                  <td><span class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $j->journal_type)) }}</span></td>
                  <td>{{ $j->debit_account }}</td>
                  <td>{{ $j->credit_account }}</td>
                  <td class="text-end fw-bold">{{ number_format((float) $j->debit_amount, 2) }}</td>
                  <td>{!! $yn($j->posted) !!}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted text-center py-3 mb-0">{{ __('No journal entries recorded.') }}</p>
        @endif
      </div>
    </div>

  </div>
</div>
@endsection
