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

@section('title', __('Impairment Assessments'))

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ipsas.index') }}">IPSAS</a></li>
                    <li class="breadcrumb-item active">{{ __('Impairments') }}</li>
                </ol>
            </nav>
            <h1><i class="fas fa-exclamation-triangle me-2"></i>{{ __('Impairment Assessments') }}</h1>
            <p class="text-muted">{{ __('Track asset impairments under IPSAS 21/26') }}</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-center">
                <div class="col-auto">
                    <div class="form-check">
                        <input type="checkbox" name="recognized_only" value="1" id="recognizedOnly" class="form-check-input" {{ $recognizedOnly ? 'checked' : '' }}>
                        <label class="form-check-label" for="recognizedOnly">{{ __('Recognised impairments only') }}</label>
                    </div>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-primary">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0">{{ __('Assessments') }} <span class="badge bg-light text-dark ms-1">{{ $impairments->count() }}</span></h5>
        </div>
        <div class="card-body p-0">
            @if($impairments->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                    <p>{{ __('No impairment assessments found.') }}</p>
                </div>
            @else
                <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Asset') }}</th>
                            <th>{{ __('Indicators') }}</th>
                            <th class="text-end">{{ __('Carrying Amount') }}</th>
                            <th class="text-end">{{ __('Recoverable Amount') }}</th>
                            <th class="text-end">{{ __('Impairment Loss') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($impairments as $imp)
                            @php $cur = $imp->currency ?: $currency; @endphp
                            <tr>
                                <td>{{ $imp->assessment_date ? date('j M Y', strtotime($imp->assessment_date)) : '-' }}</td>
                                <td>
                                    <a href="{{ route('ipsas.asset.view', $imp->asset_id) }}">{{ $imp->asset_number }}</a>
                                    <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($imp->asset_title ?? '', 50) }}</small>
                                </td>
                                <td>
                                    @forelse(\AhgIpsas\Services\IpsasService::impairmentIndicators($imp) as $ind)
                                        <span class="badge bg-secondary">{{ $ind }}</span>
                                    @empty
                                        -
                                    @endforelse
                                    @if($imp->indicator_description)
                                        <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($imp->indicator_description, 80) }}</small>
                                    @endif
                                </td>
                                <td class="text-end">{{ $cur }} {{ number_format((float) $imp->carrying_amount, 2) }}</td>
                                <td class="text-end">{{ $cur }} {{ number_format((float) $imp->recoverable_amount, 2) }}</td>
                                <td class="text-end text-danger">{{ $cur }} {{ number_format((float) $imp->impairment_loss, 2) }}</td>
                                <td>
                                    <span class="badge bg-{{ $imp->impairment_recognized ? 'success' : 'warning' }}">
                                        {{ $imp->impairment_recognized ? __('Recognised') : __('Pending') }}
                                    </span>
                                    @if($imp->is_reversal)
                                        <span class="badge bg-info">{{ __('Reversal') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
