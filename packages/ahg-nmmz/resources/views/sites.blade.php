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

@section('title', 'Archaeological Sites')

@section('content')
@php
  $statusColors = ['protected' => 'success', 'proposed' => 'info', 'at_risk' => 'warning', 'destroyed' => 'danger'];
  $provinces = ['Bulawayo','Harare','Manicaland','Mashonaland Central','Mashonaland East','Mashonaland West','Masvingo','Matabeleland North','Matabeleland South','Midlands'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">Archaeological Sites</li>
        </ol>
      </nav>
      <h1><i class="fas fa-map-marker-alt me-2"></i>Archaeological Sites</h1>
      <p class="text-muted">Protected archaeological sites</p>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.site.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Register Site
      </a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <select name="province" class="form-select">
            <option value="">All Provinces</option>
            @foreach($provinces as $p)
              <option value="{{ $p }}" @selected(request('province') === $p)>{{ $p }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="protected" @selected($currentStatus === 'protected')>Protected</option>
            <option value="proposed" @selected($currentStatus === 'proposed')>Proposed</option>
            <option value="at_risk" @selected($currentStatus === 'at_risk')>At Risk</option>
            <option value="destroyed" @selected($currentStatus === 'destroyed')>Destroyed</option>
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($sites->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-map-marker-alt fa-3x mb-3"></i>
          <p>No archaeological sites found.</p>
          <a href="{{ route('nmmz.site.create') }}" class="btn btn-primary">Register First Site</a>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Site ID</th>
              <th>Name</th>
              <th>Type</th>
              <th>Province</th>
              <th>Period</th>
              <th>Protection Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sites as $site)
              <tr>
                <td><a href="{{ route('nmmz.site.view', $site->id) }}">{{ $site->site_number ?? 'SITE-'.$site->id }}</a></td>
                <td>{{ \Illuminate\Support\Str::limit($site->name ?? '', 40) }}</td>
                <td>{{ ucfirst($site->site_type ?? '-') }}</td>
                <td>{{ $site->province ?? '-' }}</td>
                <td>{{ $site->period ?? '-' }}</td>
                <td><span class="badge bg-{{ $statusColors[$site->protection_status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $site->protection_status ?? 'unknown')) }}</span></td>
                <td>
                  <a href="{{ route('nmmz.site.view', $site->id) }}" class="btn btn-sm btn-outline-primary">
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
