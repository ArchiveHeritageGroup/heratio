{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plansailingisystems

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Antiquities Register')

@section('content')
@php
  $conditionColors = ['excellent' => 'success', 'good' => 'info', 'fair' => 'warning', 'poor' => 'danger', 'fragmentary' => 'dark'];
  $statusColors = ['in_collection' => 'success', 'on_loan' => 'info', 'missing' => 'danger', 'exported' => 'warning'];
@endphp
<div class="container-fluid">
  <div class="row mb-4">
    <div class="col">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="{{ route('nmmz.index') }}">NMMZ</a></li>
          <li class="breadcrumb-item active">Antiquities</li>
        </ol>
      </nav>
      <h1><i class="fas fa-vase me-2"></i>Antiquities Register</h1>
      <p class="text-muted">Objects over 100 years old</p>
    </div>
    <div class="col-auto">
      <a href="{{ route('nmmz.antiquity.create') }}" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i> Register Antiquity
      </a>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-body">
      <form method="get" class="row g-3">
        <div class="col-md-3">
          <select name="type" class="form-select">
            <option value="">All Types</option>
            @foreach(['ceramic' => 'Ceramic','stone' => 'Stone','metal' => 'Metal','bone' => 'Bone/Ivory','textile' => 'Textile','wooden' => 'Wooden','document' => 'Document','other' => 'Other'] as $k => $v)
              <option value="{{ $k }}" @selected(($filters['object_type'] ?? null) === $k)>{{ $v }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="in_collection" @selected(($filters['status'] ?? null) === 'in_collection')>In Collection</option>
            <option value="on_loan" @selected(($filters['status'] ?? null) === 'on_loan')>On Loan</option>
            <option value="missing" @selected(($filters['status'] ?? null) === 'missing')>Missing</option>
            <option value="exported" @selected(($filters['status'] ?? null) === 'exported')>Exported</option>
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" name="q" class="form-control" placeholder="Search..." value="{{ $filters['search'] ?? '' }}">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($antiquities->isEmpty())
        <div class="p-4 text-center text-muted">
          <i class="fas fa-vase fa-3x mb-3"></i>
          <p>No antiquities found.</p>
          <a href="{{ route('nmmz.antiquity.create') }}" class="btn btn-primary">Register First Antiquity</a>
        </div>
      @else
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Accession #</th>
              <th>Name</th>
              <th>Type</th>
              <th>Material</th>
              <th>Estimated Age</th>
              <th>Condition</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($antiquities as $a)
              <tr>
                <td><a href="{{ route('nmmz.antiquity.view', $a->id) }}">{{ $a->accession_number ?? 'ANT-'.$a->id }}</a></td>
                <td>{{ \Illuminate\Support\Str::limit($a->name ?? '', 35) }}</td>
                <td>{{ ucfirst($a->object_type ?? '-') }}</td>
                <td>{{ $a->material ?? '-' }}</td>
                <td>{{ $a->estimated_age_years ? $a->estimated_age_years.' years' : '-' }}</td>
                <td><span class="badge bg-{{ $conditionColors[$a->condition_rating] ?? 'secondary' }}">{{ ucfirst($a->condition_rating ?? 'unknown') }}</span></td>
                <td><span class="badge bg-{{ $statusColors[$a->status] ?? 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $a->status ?? 'unknown')) }}</span></td>
                <td>
                  <a href="{{ route('nmmz.antiquity.view', $a->id) }}" class="btn btn-sm btn-outline-primary">
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
