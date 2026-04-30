{{--
  Contract create/edit form.

  Vars:
    $contract  (?object)              — existing row (or null for create).
    $isNew     (bool)                  — convenience flag.
    $types     (iterable of objects)   — rows from ahg_contract_type (id, name).
    $vendors   (iterable of objects)   — rows from ahg_vendors (id, name) for the link select.
    $vendor    (?object)               — pre-selected vendor when arriving from vendor view.
    $action    (?string)               — form POST URL; defaults to current URL.

  Status, risk level, counterparty type, and currency all read live from
  ahg_dropdown (taxonomies: contract_status, risk_level, contract_counterparty_type,
  currency). Per CLAUDE.md, never hardcode enum values in views.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Schema;

    $contract = $contract ?? (object) [];
    $isNew = $isNew ?? empty($contract->id ?? null);
    $action = $action ?? url()->current();

    $dd = function (string $taxonomy): \Illuminate\Support\Collection {
        if (! Schema::hasTable('ahg_dropdown')) return collect();
        return DB::table('ahg_dropdown')
            ->where('taxonomy', $taxonomy)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['code', 'label']);
    };

    $statuses = $dd('contract_status');
    $risks = $dd('risk_level');
    $cpTypes = $dd('contract_counterparty_type');
    if ($cpTypes->isEmpty()) {
        // Until the seed lands, keep the form usable with the legacy keys.
        $cpTypes = collect([
            (object) ['code' => 'vendor',      'label' => __('Vendor/Supplier')],
            (object) ['code' => 'institution', 'label' => __('Institution')],
            (object) ['code' => 'individual',  'label' => __('Individual')],
            (object) ['code' => 'government',  'label' => __('Government')],
            (object) ['code' => 'other',       'label' => __('Other')],
        ]);
    }
    $currencies = $dd('currency');
    if ($currencies->isEmpty()) {
        $currencies = collect([
            (object) ['code' => 'ZAR', 'label' => 'ZAR'],
            (object) ['code' => 'USD', 'label' => 'USD'],
            (object) ['code' => 'EUR', 'label' => 'EUR'],
            (object) ['code' => 'GBP', 'label' => 'GBP'],
        ]);
    }
@endphp
<div class="container-xxl py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">{{ $isNew ? __('New Contract') : __('Edit Contract') }}</h1>
        @if (\Illuminate\Support\Facades\Route::has('contract.browse'))
            <a href="{{ route('contract.browse') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back to List') }}
            </a>
        @endif
    </div>

    <form method="post" action="{{ $action }}" enctype="multipart/form-data">
        @csrf

        {{-- Basic Information --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Contract Details') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Contract Type') }} <span class="text-danger">*</span></label>
                        <select name="contract[contract_type_id]" class="form-select" required>
                            <option value="">{{ __('Select type...') }}</option>
                            @foreach ($types ?? [] as $type)
                                <option value="{{ $type->id }}"
                                        @selected(($contract->contract_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Contract Number') }}</label>
                        <input type="text" name="contract[contract_number]" class="form-control"
                               value="{{ $contract->contract_number ?? '' }}"
                               placeholder="{{ __('Auto-generated if blank') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Status') }}</label>
                        <select name="contract[status]" class="form-select">
                            @foreach ($statuses as $s)
                                <option value="{{ $s->code }}"
                                        @selected(($contract->status ?? 'draft') === $s->code)>{{ $s->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                        <input type="text" name="contract[title]" class="form-control"
                               value="{{ $contract->title ?? '' }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="contract[description]" class="form-control" rows="3">{{ $contract->description ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Contract Logo') }}</label>
                        @if (! empty($contract->logo_path))
                            <div class="mb-2">
                                <img src="/uploads{{ $contract->logo_path }}" alt="{{ __('Logo') }}" class="img-thumbnail" style="max-height: 80px;">
                                <div class="form-check mt-1">
                                    <input type="checkbox" name="remove_logo" id="remove_logo" class="form-check-input" value="1">
                                    <label class="form-check-label text-danger" for="remove_logo">{{ __('Remove logo') }}</label>
                                </div>
                            </div>
                        @endif
                        <input type="file" name="contract_logo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="text-muted">{{ __('Upload organization logo for contract header (JPG, PNG, GIF, WebP)') }}</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Risk Level') }}</label>
                        <select name="contract[risk_level]" class="form-select">
                            @foreach ($risks as $r)
                                <option value="{{ $r->code }}"
                                        @selected(($contract->risk_level ?? 'low') === $r->code)>{{ $r->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="contract[is_template]" id="is_template" class="form-check-input" value="1"
                                   @checked(! empty($contract->is_template))>
                            <label class="form-check-label" for="is_template">{{ __('Save as Template') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Counterparty --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Counterparty') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Counterparty Name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="contract[counterparty_name]" class="form-control"
                               value="{{ $contract->counterparty_name ?? ($vendor->name ?? '') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Type') }}</label>
                        <select name="contract[counterparty_type]" class="form-select">
                            @foreach ($cpTypes as $t)
                                <option value="{{ $t->code }}"
                                        @selected(($contract->counterparty_type ?? 'vendor') === $t->code)>{{ $t->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Link to Vendor') }}</label>
                        <select name="contract[vendor_id]" class="form-select">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($vendors ?? [] as $v)
                                <option value="{{ $v->id }}"
                                        @selected(($contract->vendor_id ?? ($vendor->id ?? '')) == $v->id)>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Contact Information') }}</label>
                        <textarea name="contract[counterparty_contact]" class="form-control" rows="2"
                                  placeholder="{{ __('Address, phone, email...') }}">{{ $contract->counterparty_contact ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Representative Name') }}</label>
                        <input type="text" name="contract[counterparty_representative]" class="form-control"
                               value="{{ $contract->counterparty_representative ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Representative Title') }}</label>
                        <input type="text" name="contract[counterparty_representative_title]" class="form-control"
                               value="{{ $contract->counterparty_representative_title ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Our Details --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Our Organization') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Our Representative') }}</label>
                        <input type="text" name="contract[our_representative]" class="form-control"
                               value="{{ $contract->our_representative ?? '' }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Title/Position') }}</label>
                        <input type="text" name="contract[our_representative_title]" class="form-control"
                               value="{{ $contract->our_representative_title ?? '' }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Dates --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Dates') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Effective Date') }}</label>
                        <input type="date" name="contract[effective_date]" class="form-control" value="{{ $contract->effective_date ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Expiry Date') }}</label>
                        <input type="date" name="contract[expiry_date]" class="form-control" value="{{ $contract->expiry_date ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Review Date') }}</label>
                        <input type="date" name="contract[review_date]" class="form-control" value="{{ $contract->review_date ?? '' }}">
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input type="checkbox" name="contract[auto_renew]" id="auto_renew" class="form-check-input" value="1"
                                   @checked(! empty($contract->auto_renew))>
                            <label class="form-check-label" for="auto_renew">{{ __('Auto-renew') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Financial --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Financial Terms') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="form-check">
                            <input type="checkbox" name="contract[has_financial_terms]" id="has_financial" class="form-check-input" value="1"
                                   @checked(! empty($contract->has_financial_terms))>
                            <label class="form-check-label" for="has_financial">{{ __('Has financial terms') }}</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('Contract Value') }}</label>
                        <input type="number" step="0.01" name="contract[contract_value]" class="form-control"
                               value="{{ $contract->contract_value ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">{{ __('Currency') }}</label>
                        <select name="contract[currency]" class="form-select">
                            @foreach ($currencies as $c)
                                <option value="{{ $c->code }}"
                                        @selected(($contract->currency ?? 'ZAR') === $c->code)>{{ $c->label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">{{ __('Payment Terms') }}</label>
                        <input type="text" name="contract[payment_terms]" class="form-control"
                               value="{{ $contract->payment_terms ?? '' }}"
                               placeholder="{{ __('e.g., 30 days from invoice') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Terms --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Terms & Conditions') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach ([
                        'scope_of_work' => __('Scope of Work'),
                        'deliverables' => __('Deliverables'),
                        'general_terms' => __('General Terms'),
                        'special_conditions' => __('Special Conditions'),
                    ] as $field => $label)
                        <div class="col-12">
                            <label class="form-label">{{ $label }}</label>
                            <textarea name="contract[{{ $field }}]" class="form-control" rows="3">{{ $contract->{$field} ?? '' }}</textarea>
                        </div>
                    @endforeach
                    <div class="col-md-6">
                        <label class="form-label">{{ __('IP Terms') }}</label>
                        <textarea name="contract[ip_terms]" class="form-control" rows="3">{{ $contract->ip_terms ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Confidentiality Terms') }}</label>
                        <textarea name="contract[confidentiality_terms]" class="form-control" rows="3">{{ $contract->confidentiality_terms ?? '' }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Governing Law') }}</label>
                        <input type="text" name="contract[governing_law]" class="form-control"
                               value="{{ $contract->governing_law ?? '' }}"
                               placeholder="{{ __('e.g. South Africa, England & Wales') }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title mb-0">{{ __('Internal Notes') }}</h5></div>
            <div class="card-body">
                <textarea name="contract[internal_notes]" class="form-control" rows="3">{{ $contract->internal_notes ?? '' }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between">
            @if (\Illuminate\Support\Facades\Route::has('contract.browse'))
                <a href="{{ route('contract.browse') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i> {{ $isNew ? __('Create Contract') : __('Save Changes') }}
            </button>
        </div>
    </form>
</div>
