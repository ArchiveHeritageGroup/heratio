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

@section('title', 'Heritage Impact Assessments')

@section('content')
@php
  $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'conditions' => 'info'];
  $impactColors = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'critical' => 'dark'];
  $provinces = ['Bulawayo','Harare','Manicaland','Mashonaland Central','Mashonaland East','Mashonaland West','Masvingo','Matabeleland North','Matabeleland South','Midlands'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="{{ __('breadcrumb') }}">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">Heritage Impact Assessments</li>
        </ol>
      </nav>
      <h1><i class="fas fa-clipboard-check me-2"></i>Heritage Impact Assessments</h1>
      <p class="text-muted">HIA submissions</p>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.hia.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Assessment
      </a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">{{ __('All Status') }}</option>
            <option value="pending" @selected($currentStatus === 'pending')>{{ __('Pending Review') }}</option>
            <option value="approved" @selected($currentStatus === 'approved')>{{ __('Approved') }}</option>
            <option value="rejected" @selected($currentStatus === 'rejected')>{{ __('Rejected') }}</option>
            <option value="conditions" @selected($currentStatus === 'conditions')>{{ __('Approved with Conditions') }}</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="province" class="form-select">
            <option value="">{{ __('All Provinces') }}</option>
            @foreach($provinces as $p)
              <option value="{{ $p }}" @selected(request('province') === $p)>{{ $p }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">{{ __('Filter') }}</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($hias->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-clipboard-check fa-3x mb-3"></i>
          <p>No heritage impact assessments found.</p>
          <a href="{{ route('nmmz.hia.create') }}" class="btn btn-primary">Submit Assessment</a>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>{{ __('Reference #') }}</th>
              <th>{{ __('Project Name') }}</th>
              <th>{{ __('Developer') }}</th>
              <th>{{ __('Province') }}</th>
              <th>{{ __('Impact Level') }}</th>
              <th>{{ __('Status') }}</th>
              <th>{{ __('Submitted') }}</th>
              <th>{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($hias as $hia)
              <tr>
                <td><strong>{{ $hia->reference_number ?? 'HIA-'.$hia->id }}</strong></td>
                <td>{{ \Illuminate\Support\Str::limit($hia->project_name ?? '', 30) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($hia->developer_name ?? '', 25) }}</td>
                <td>{{ $hia->province ?? '-' }}</td>
                <td><span class="badge bg-{{ $impactColors[$hia->impact_level] ?? 'secondary' }}">{{ ucfirst($hia->impact_level ?? 'unknown') }}</span></td>
                <td><span class="badge bg-{{ $statusColors[$hia->status] ?? 'secondary' }}">{{ ucfirst($hia->status ?? 'pending') }}</span></td>
                <td>{{ $hia->created_at ? \Carbon\Carbon::parse($hia->created_at)->format('Y-m-d') : '-' }}</td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" disabled>
                    <i class="fas fa-eye"></i>
                  </button>
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
