{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Heratio is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU Affero General Public License for more details.
--}}
@extends('theme::layouts.1col')

@section('title', ($asset->asset_number ?? __('Asset')) . ' - ' . __('Heritage Asset'))

@section('content')
@php
    $hdr = 'background:var(--ahg-primary);color:#fff';
    $label = fn ($list, $code) => $code === null || $code === '' ? '-' : ($opts[$list][$code] ?? ucfirst(str_replace('_', ' ', $code)));
    $money = fn ($cur, $val) => ($cur ?: $currency) . ' ' . number_format((float) $val, 2);
    $date = fn ($d, $fmt = 'j M Y') => $d ? date($fmt, strtotime($d)) : '-';
    $statusColors = ['active' => 'success', 'on_loan' => 'info', 'in_storage' => 'secondary', 'under_conservation' => 'warning', 'disposed' => 'danger', 'lost' => 'dark', 'destroyed' => 'dark'];
    $conditionColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger', 'critical' => 'dark'];
    $riskColors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.assets') }}">{{ __('Assets') }}</a></li>
                    <li class="breadcrumb-item active">{{ $asset->asset_number ?? __('Asset') }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-archive me-2"></i>{{ $asset->title }}</h1>
        </div>
        <div class="col-auto">
            <a href="{{ route('ipsas.asset.edit', $asset->id) }}" class="btn btn-primary">
                <i class="fas fa-edit me-1"></i> {{ __('Edit') }}
            </a>
            <a href="{{ route('ipsas.valuation.create', ['asset_id' => $asset->id]) }}" class="btn btn-outline-primary">
                <i class="fas fa-calculator me-1"></i> {{ __('Add Valuation') }}
            </a>
            <a href="{{ route('ipsas.assets') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Asset Details') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Asset Number') }}</dt>
                        <dd class="col-sm-8">{{ $asset->asset_number ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Description') }}</dt>
                        <dd class="col-sm-8">{!! nl2br(e($asset->description ?: '-')) !!}</dd>
                        <dt class="col-sm-4">{{ __('Category') }}</dt>
                        <dd class="col-sm-8">{{ $asset->category_name ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Location') }}</dt>
                        <dd class="col-sm-8">{{ $asset->location ?: '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Acquisition') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Acquisition Date') }}</dt>
                        <dd class="col-sm-8">{{ $date($asset->acquisition_date, 'j F Y') }}</dd>
                        <dt class="col-sm-4">{{ __('Method') }}</dt>
                        <dd class="col-sm-8">{{ $label('acquisition_method', $asset->acquisition_method) }}</dd>
                        <dt class="col-sm-4">{{ __('Source') }}</dt>
                        <dd class="col-sm-8">{{ $asset->acquisition_source ?: '-' }}</dd>
                        <dt class="col-sm-4">{{ __('Cost') }}</dt>
                        <dd class="col-sm-8">{{ $asset->acquisition_cost ? $money($asset->acquisition_currency, $asset->acquisition_cost) : '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Financial') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('Valuation Basis') }}</dt>
                        <dd class="col-sm-8">{{ $label('valuation_basis', $asset->valuation_basis) }}</dd>
                        <dt class="col-sm-4">{{ __('Current Value') }}</dt>
                        <dd class="col-sm-8">
                            {{ $money($asset->current_value_currency, $asset->current_value ?? 0) }}
                            @if($asset->current_value_date)<small class="text-muted ms-1">({{ __('as at :d', ['d' => $date($asset->current_value_date)]) }})</small>@endif
                        </dd>
                        <dt class="col-sm-4">{{ __('Depreciation Policy') }}</dt>
                        <dd class="col-sm-8">{{ $label('depreciation_policy', $asset->depreciation_policy) }}</dd>
                        @if($asset->useful_life_years)
                        <dt class="col-sm-4">{{ __('Useful Life') }}</dt>
                        <dd class="col-sm-8">{{ __(':n years', ['n' => $asset->useful_life_years]) }}</dd>
                        @endif
                        @if((float) $asset->accumulated_depreciation > 0)
                        <dt class="col-sm-4">{{ __('Accumulated Depreciation') }}</dt>
                        <dd class="col-sm-8">{{ $money($asset->current_value_currency, $asset->accumulated_depreciation) }}</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('Insured Value') }}</dt>
                        <dd class="col-sm-8">
                            @if($asset->insured_value)
                                {{ $money($asset->current_value_currency, $asset->insured_value) }}
                                @if($asset->insurance_policy)<small class="text-muted ms-1">{{ __('Policy :p', ['p' => $asset->insurance_policy]) }}@if($asset->insurance_expiry), {{ __('expires :d', ['d' => $date($asset->insurance_expiry)]) }}@endif</small>@endif
                            @else
                                {{ __('Not insured') }}
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            @if($valuations->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Valuation History') }}</h5></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Basis') }}</th><th class="text-end">{{ __('Value') }}</th><th class="text-end">{{ __('Change') }}</th><th>{{ __('Valuer') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach($valuations as $v)
                            <tr>
                                <td>{{ $date($v->valuation_date) }}</td>
                                <td>{{ $label('valuation_type', $v->valuation_type) }}</td>
                                <td>{{ $label('valuation_basis', $v->valuation_basis) }}</td>
                                <td class="text-end">{{ $money($v->currency, $v->new_value) }}</td>
                                <td class="text-end {{ (float) $v->change_amount >= 0 ? 'text-success' : 'text-danger' }}">{{ (float) $v->change_amount >= 0 ? '+' : '' }}{{ number_format((float) $v->change_amount, 2) }}</td>
                                <td>{{ $v->valuer_name ?: '-' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($impairments->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Impairments') }}</h5></div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr><th>{{ __('Date') }}</th><th>{{ __('Indicators') }}</th><th class="text-end">{{ __('Loss') }}</th><th>{{ __('Recognised') }}</th></tr>
                        </thead>
                        <tbody>
                            @foreach($impairments as $imp)
                            <tr>
                                <td>{{ $date($imp->assessment_date) }}</td>
                                <td>{{ implode(', ', \AhgIpsas\Services\IpsasService::impairmentIndicators($imp)) ?: '-' }}</td>
                                <td class="text-end">{{ $money($imp->currency, $imp->impairment_loss ?? 0) }}</td>
                                <td>
                                    <span class="badge bg-{{ $imp->impairment_recognized ? 'success' : 'warning' }}">
                                        {{ $imp->impairment_recognized ? __('Yes') : __('Pending') }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Status') }}</h5></div>
                <div class="card-body text-center">
                    <span class="badge bg-{{ $statusColors[$asset->status] ?? 'secondary' }} fs-5 px-4 py-2">
                        {{ $label('status', $asset->status) }}
                    </span>
                    <p class="mt-3 mb-1"><strong>{{ __('Condition:') }}</strong></p>
                    <span class="badge bg-{{ $conditionColors[$asset->condition_rating] ?? 'secondary' }}">
                        {{ $asset->condition_rating ? $label('condition_rating', $asset->condition_rating) : __('Unknown') }}
                    </span>
                </div>
            </div>

            @if($asset->risk_level)
            <div class="card mb-4">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Risk Assessment') }}</h5></div>
                <div class="card-body">
                    <p class="mb-2"><strong>{{ __('Risk Level:') }}</strong>
                        <span class="badge bg-{{ $riskColors[$asset->risk_level] ?? 'secondary' }}">{{ $label('risk_level', $asset->risk_level) }}</span>
                    </p>
                    @if($asset->risk_notes)
                    <p class="mb-0 small">{!! nl2br(e($asset->risk_notes)) !!}</p>
                    @endif
                </div>
            </div>
            @endif

            <div class="card">
                <div class="card-header" style="{{ $hdr }}"><h5 class="mb-0">{{ __('Record') }}</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ __('Created:') }}</strong> {{ $date($asset->created_at) }}</p>
                    @if($asset->updated_at)
                    <p class="mb-0"><strong>{{ __('Updated:') }}</strong> {{ $date($asset->updated_at) }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
