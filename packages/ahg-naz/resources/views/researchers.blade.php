{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Researcher Registry')

@section('content')
@php
    $researchers = $rows ?? collect();
    $currentType = request('type');
    $search = request('q', '');
    $typeColors = ['local' => 'success', 'foreign' => 'info', 'institutional' => 'secondary'];
    $statusColors = ['active' => 'success', 'inactive' => 'secondary', 'suspended' => 'warning', 'blacklisted' => 'danger'];
@endphp
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgnaz.index') }}">NAZ</a></li>
                    <li class="breadcrumb-item active">Researcher Registry</li>
                </ol>
            </nav>
            <h1><i class="fas fa-users me-2"></i>{{ __('Researcher Registry') }}</h1>
            <p class="text-muted">Registered researchers and their permit history</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgnaz.researcher-create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> {{ __('Register Researcher') }}
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="btn-group">
                        <a href="{{ route('ahgnaz.researchers') }}"
                           class="btn btn-{{ !$currentType ? 'primary' : 'outline-primary' }}">All</a>
                        <a href="{{ route('ahgnaz.researchers', ['type' => 'local']) }}"
                           class="btn btn-{{ $currentType === 'local' ? 'success' : 'outline-success' }}">Local</a>
                        <a href="{{ route('ahgnaz.researchers', ['type' => 'foreign']) }}"
                           class="btn btn-{{ $currentType === 'foreign' ? 'info' : 'outline-info' }}">Foreign</a>
                        <a href="{{ route('ahgnaz.researchers', ['type' => 'institutional']) }}"
                           class="btn btn-{{ $currentType === 'institutional' ? 'secondary' : 'outline-secondary' }}">Institutional</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <form method="get" class="d-flex">
                        <input type="text" name="q" class="form-control me-2" placeholder="{{ __('Search researchers...') }}"
                               value="{{ $search }}">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Researchers Table --}}
    <div class="card">
        <div class="card-body p-0">
            @if(empty($researchers) || (is_countable($researchers) && count($researchers) === 0))
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-users fa-3x mb-3"></i>
                    <p>No researchers found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Institution') }}</th>
                            <th>{{ __('Nationality') }}</th>
                            <th>{{ __('Registered') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($researchers as $researcher)
                            <tr>
                                <td>
                                    <a href="{{ route('ahgnaz.researcher-view', ['id' => $researcher->id]) }}">
                                        @if(!empty($researcher->title)){{ $researcher->title }} @endif{{ $researcher->first_name }} {{ $researcher->last_name }}
                                    </a>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $typeColors[$researcher->researcher_type ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst($researcher->researcher_type ?? '') }}
                                    </span>
                                </td>
                                <td>{{ $researcher->email ?? '' }}</td>
                                <td>{{ $researcher->institution ?? '-' }}</td>
                                <td>{{ $researcher->nationality ?? '-' }}</td>
                                <td>{{ $researcher->registration_date ?? '' }}</td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$researcher->status ?? ''] ?? 'secondary' }}">
                                        {{ ucfirst($researcher->status ?? '') }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgnaz.researcher-view', ['id' => $researcher->id]) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>
@endsection
