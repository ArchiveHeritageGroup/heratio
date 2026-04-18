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

@section('title', 'Export Permits')

@section('content')
@php
  $statusColors = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'expired' => 'secondary'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">Export Permits</li>
        </ol>
      </nav>
      <h1><i class="fas fa-file-export me-2"></i>Export Permits</h1>
      <p class="text-muted">Export permit applications</p>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.permit.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> New Application
      </a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="pending" @selected($currentStatus === 'pending')>Pending</option>
            <option value="approved" @selected($currentStatus === 'approved')>Approved</option>
            <option value="rejected" @selected($currentStatus === 'rejected')>Rejected</option>
            <option value="expired" @selected($currentStatus === 'expired')>Expired</option>
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
      @if($permits->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-file-export fa-3x mb-3"></i>
          <p>No export permits found.</p>
          <a href="{{ route('nmmz.permit.create') }}" class="btn btn-primary">Create Application</a>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Permit #</th>
              <th>Applicant</th>
              <th>Object</th>
              <th>Destination</th>
              <th>Purpose</th>
              <th>Status</th>
              <th>Applied</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($permits as $p)
              <tr>
                <td><a href="{{ route('nmmz.permit.view', $p->id) }}">{{ $p->permit_number ?? 'EXP-'.$p->id }}</a></td>
                <td>{{ \Illuminate\Support\Str::limit($p->applicant_name ?? '', 25) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($p->object_description ?? '', 30) }}</td>
                <td>{{ $p->destination_country ?? '-' }}</td>
                <td>{{ ucfirst($p->export_purpose ?? '-') }}</td>
                <td><span class="badge bg-{{ $statusColors[$p->status] ?? 'secondary' }}">{{ ucfirst($p->status ?? 'unknown') }}</span></td>
                <td>{{ $p->created_at ? \Carbon\Carbon::parse($p->created_at)->format('Y-m-d') : '-' }}</td>
                <td>
                  <a href="{{ route('nmmz.permit.view', $p->id) }}" class="btn btn-sm btn-outline-primary">
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
