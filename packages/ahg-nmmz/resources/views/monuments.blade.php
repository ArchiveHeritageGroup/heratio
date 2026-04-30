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

@section('title', 'National Monuments')

@section('content')
@php
  $legalColors = ['gazetted' => 'success', 'provisional' => 'info', 'proposed' => 'warning', 'delisted' => 'danger'];
  $conditionColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger', 'critical' => 'dark'];
  $statusColors = ['active' => 'success', 'at_risk' => 'warning', 'destroyed' => 'danger'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">National Monuments</li>
        </ol>
      </nav>
      <h1><i class="fas fa-monument me-2"></i>National Monuments</h1>
      <p class="text-muted">Protected heritage sites (jurisdiction-specific module)</p>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.monument.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> {{ __('Register Monument') }}
      </a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <select name="category" class="form-select">
            <option value="">{{ __('All Categories') }}</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" @selected(($filters['category_id'] ?? null) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('Active') }}</option>
            <option value="at_risk" @selected(($filters['status'] ?? null) === 'at_risk')>{{ __('At Risk') }}</option>
            <option value="destroyed" @selected(($filters['status'] ?? null) === 'destroyed')>{{ __('Destroyed') }}</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="province" class="form-select">
            <option value="">{{ __('All Provinces') }}</option>
            @foreach(['Bulawayo','Harare','Manicaland','Mashonaland Central','Mashonaland East','Mashonaland West','Masvingo','Matabeleland North','Matabeleland South','Midlands'] as $p)
              <option value="{{ $p }}" @selected(($filters['province'] ?? null) === $p)>{{ $p }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" name="q" class="form-control" placeholder="{{ __('Search...') }}" value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">{{ __('Filter') }}</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($monuments->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-monument fa-3x mb-3"></i>
          <p>No monuments found.</p>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Monument #') }}</th>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Category') }}</th>
              <th>{{ __('Province') }}</th>
              <th>{{ __('Legal Status') }}</th>
              <th>{{ __('Condition') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($monuments as $m)
              <tr>
                <td><a href="{{ route('nmmz.monument.view', $m->id) }}">{{ $m->monument_number ?? 'MON-'.$m->id }}</a></td>
                <td>{{ \Illuminate\Support\Str::limit($m->name ?? '', 40) }}</td>
                <td>{{ $m->category_name ?? '-' }}</td>
                <td>{{ $m->province ?? '-' }}</td>
                <td><span class="badge bg-{{ $legalColors[$m->legal_status] ?? 'secondary' }}">{{ ucfirst($m->legal_status ?? 'unknown') }}</span></td>
                <td><span class="badge bg-{{ $conditionColors[$m->condition_rating] ?? 'secondary' }}">{{ ucfirst($m->condition_rating ?? 'unknown') }}</span></td>
                <td><span class="badge bg-{{ $statusColors[$m->status ?? ''] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $m->status ?? 'unknown')) }}</span></td>
                <td>
                  <a href="{{ route('nmmz.monument.view', $m->id) }}" class="btn btn-sm btn-outline-primary">
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
