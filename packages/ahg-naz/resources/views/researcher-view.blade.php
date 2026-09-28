{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  You should have received a copy of the GNU Affero General Public License
  along with Heratio. If not, see <https://www.gnu.org/licenses/>.
--}}
@extends('theme::layouts.1col')

@section('title', 'Researcher: ' . ($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? ''))

@section('content')
@php
    // The first five are researcher/permit states; the last three are the
    // derived VISIT states (see NazController::researcherView). One map, because
    // the visits table below shares this array.
    $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning', 'blacklisted' => 'danger', 'pending' => 'warning',
                     'completed' => 'success', 'in_progress' => 'info', 'scheduled' => 'secondary'];
    $typeColors   = ['local' => 'success', 'foreign' => 'info', 'institutional' => 'secondary'];
@endphp

<div class="container-fluid">
    {{-- Breadcrumb --}}
    <div class="row mb-3">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.researchers') }}">{{ __('Researchers') }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">
                        {{ $researcher->first_name ?? '' }} {{ $researcher->last_name ?? '' }}
                    </li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.researcher-edit', ['id' => $researcher->id]) }}"
               class="btn btn-outline-primary me-1">
                <i class="fas fa-edit me-1"></i> {{ __('Edit') }}
            </a>
            <a href="{{ route('ahgnaz.permits') }}?query={{ urlencode($researcher->last_name ?? '') }}"
               class="btn btn-outline-secondary">
                <i class="fas fa-id-card me-1"></i> {{ __('Permits') }}
            </a>
        </div>
    </div>

    <div class="row">
        {{-- Left column: researcher profile + contact details --}}
        <div class="col-lg-8">

            {{-- Personal Information --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user me-2"></i>{{ __('Personal Information') }}</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Full Name') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->title)){{ $researcher->title }} @endif
                            {{ $researcher->first_name ?? '' }} {{ $researcher->last_name ?? '' }}
                        </dd>

                        <dt class="col-sm-3">{{ __('Type') }}</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $typeColors[$researcher->researcher_type ?? ''] ?? 'secondary' }}">
                                {{ ucfirst($researcher->researcher_type ?? '') }}
                            </span>
                        </dd>

                        <dt class="col-sm-3">{{ __('Status') }}</dt>
                        <dd class="col-sm-9">
                            <span class="badge bg-{{ $statusColors[$researcher->status ?? ''] ?? 'secondary' }}">
                                {{ ucfirst($researcher->status ?? '') }}
                            </span>
                        </dd>

                        <dt class="col-sm-3">{{ __('Nationality') }}</dt>
                        <dd class="col-sm-9">{{ $researcher->nationality ?? '-' }}</dd>

                        <dt class="col-sm-3">{{ __('National ID') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->national_id))
                                <code class="text-dark">{{ $researcher->national_id }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Passport Number') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->passport_number))
                                <code class="text-dark">{{ $researcher->passport_number }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Contact Information --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>{{ __('Contact Information') }}</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Email') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->email))
                                <a href="mailto:{{ $researcher->email }}">{{ $researcher->email }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Phone') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->phone))
                                <a href="tel:{{ $researcher->phone }}">{{ $researcher->phone }}</a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('Address') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->address))
                                {!! nl2br(e($researcher->address)) !!}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">{{ __('City / Country') }}</dt>
                        <dd class="col-sm-9">
                            @if(!empty($researcher->city) || !empty($researcher->country))
                                {{ $researcher->city ?? '' }}{{ !empty($researcher->city) && !empty($researcher->country) ? ', ' : '' }}{{ $researcher->country ?? '' }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Affiliation --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-building me-2"></i>{{ __('Affiliation') }}</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">{{ __('Institution') }}</dt>
                        <dd class="col-sm-9">{{ $researcher->institution ?? '-' }}</dd>

                        <dt class="col-sm-3">{{ __('Position') }}</dt>
                        <dd class="col-sm-9">{{ $researcher->position ?? '-' }}</dd>

                        @if(!empty($researcher->research_interests))
                        <dt class="col-sm-3">{{ __('Research Interests') }}</dt>
                        <dd class="col-sm-9">{{ $researcher->research_interests }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Notes (encrypted, but decrypted for display here) --}}
            @if(!empty($researcher->notes))
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>{{ __('Notes') }}</h5>
                </div>
                <div class="card-body">
                    {!! nl2br(e($researcher->notes)) !!}
                </div>
            </div>
            @endif

            {{-- Recent visits --}}
            @if($visits->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Recent Visits ({{ $visits->count() }} total)</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Material Consulted') }}</th>
                                <th>{{ __('Purpose') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($visits->take(10) as $visit)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($visit->visit_date)->format('j M Y') }}</td>
                                <td>{{ $visit->material_consulted ?? '-' }}</td>
                                <td>{{ $visit->purpose ?? '-' }}</td>
                                <td><span class="badge bg-{{ $statusColors[$visit->status ?? 'scheduled'] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $visit->status ?? '')) }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        {{-- Right column: status + permits --}}
        <div class="col-lg-4">
            {{-- Status card --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Registration Details') }}</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>{{ __('Registered') }}</dt>
                        <dd>{{ !empty($researcher->registration_date) ? \Carbon\Carbon::parse($researcher->registration_date)->format('j F Y') : '-' }}</dd>

                        <dt>{{ __('Last Updated') }}</dt>
                        <dd>{{ !empty($researcher->updated_at) ? \Carbon\Carbon::parse($researcher->updated_at)->format('j F Y H:i') : '-' }}</dd>

                        @if(!empty($researcher->notes))
                        <dt>{{ __('Internal Notes') }}</dt>
                        <dd>
                            <span class="badge bg-info">{{ __('Present') }}</span>
                        </dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Permits --}}
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Permits ({{ $permits->count() }})</h5>
                    <a href="{{ route('ahgnaz.permit-create') }}" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-plus"></i> {{ __('New Permit') }}
                    </a>
                </div>
                <div class="card-body p-0">
                    @if($permits->isEmpty())
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-id-card fa-2x mb-2 d-block"></i>
                            {{ __('No permits on record.') }}
                        </div>
                    @else
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Number') }}</th>
                                    <th>{{ __('Topic') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($permits as $permit)
                                <tr class="clickable-row" style="cursor:pointer"
                                    onclick="window.location='{{ route('ahgnaz.permit-view', ['id' => $permit->id]) }}'">
                                    <td>
                                        <code>{{ $permit->permit_number ?? '' }}</code>
                                    </td>
                                    <td>{{ Str::limit($permit->research_topic ?? '', 30) }}</td>
                                    <td>
                                        <span class="badge bg-{{ $statusColors[$permit->status ?? ''] ?? 'secondary' }}">
                                            {{ ucfirst($permit->status ?? '') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>{{ __('Quick Actions') }}</h5>
                </div>
                <div class="card-body d-grid gap-2">
                    <a href="{{ route('ahgnaz.permit-create') }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus me-1"></i> {{ __('Issue Permit') }}
                    </a>
                    <a href="{{ route('ahgnaz.reports', ['type' => 'permits']) }}"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-chart-bar me-1"></i> {{ __('View Reports') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection