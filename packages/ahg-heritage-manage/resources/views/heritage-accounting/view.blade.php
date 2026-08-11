@extends('theme::layouts.1col')
@section('title', 'Heritage Asset - GRAP Accounting Record')
@section('body-class', 'admin heritage')

@php
    // GRAP 103 heritage-asset accounting record, grouped by lifecycle. Each row:
    // [label, field, type] where type in text|long|money|date|int|bool.
    $sections = [
        'Identification & classification' => [
            ['Asset class', 'asset_class_name', 'text'],
            ['Asset sub-class', 'asset_sub_class', 'text'],
            ['Accounting standard', 'standard_code', 'text'],
            ['Standard name', 'standard_name', 'text'],
            ['Heritage significance', 'heritage_significance', 'text'],
            ['Significance statement', 'significance_statement', 'long'],
        ],
        'Recognition' => [
            ['Recognition status', 'recognition_status', 'text'],
            ['Reason', 'recognition_status_reason', 'long'],
            ['Recognition date', 'recognition_date', 'date'],
            ['Measurement basis', 'measurement_basis', 'text'],
        ],
        'Acquisition & initial measurement' => [
            ['Acquisition method', 'acquisition_method', 'text'],
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
            ['Valuation method', 'valuation_method', 'text'],
            ['Valuer', 'valuer_name', 'text'],
            ['Valuer credentials', 'valuer_credentials', 'text'],
            ['Valuation report reference', 'valuation_report_reference', 'text'],
            ['Revaluation frequency', 'revaluation_frequency', 'text'],
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
            ['Condition rating', 'condition_rating', 'text'],
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
        switch ($type) {
            case 'money':
                return 'R&nbsp;' . number_format((float) $val, 2);
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
        <h1 class="h3 mb-1"><i class="fas fa-file-invoice-dollar me-2"></i>{{ __('Heritage Asset - GRAP Accounting Record') }}</h1>
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
      <div class="d-flex gap-2">
        @if(Route::has('heritage.accounting.add-valuation'))
          <a href="{{ route('heritage.accounting.add-valuation', $asset->id) }}" class="btn atom-btn-white btn-sm"><i class="fas fa-dollar-sign me-1"></i>{{ __('Add valuation') }}</a>
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
          <div class="h4 mb-0">R {{ number_format((float) $asset->current_carrying_amount, 2) }}</div>
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
          <div class="h5 mb-0">R {{ number_format((float) $asset->accumulated_depreciation, 2) }}</div>
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

  </div>
</div>
@endsection
