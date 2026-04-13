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

@section('title', 'Exhibitions')

@section('content')
@php
  $exhibitions = $exhibitions ?? collect();
  $statuses = $statuses ?? [];
  $types = $types ?? [];
  $stats = $stats ?? [];
  $filters = $filters ?? [];
  $page = $page ?? 1;
  $pages = $pages ?? 1;
@endphp

<div class="row">
  <div class="col-md-9">
    <h1>Exhibitions</h1>

    <div class="card mb-4">
      <div class="card-header">
        <form method="get" action="{{ route('exhibition.index') }}" class="row g-2 align-items-center">
          <div class="col-auto">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ $filters['search'] ?? '' }}">
          </div>
          <div class="col-auto">
            <select name="status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              @foreach($statuses as $key => $status)
                <option value="{{ $key }}" {{ ($filters['status'] ?? '') == $key ? 'selected' : '' }}>
                  {{ is_array($status) ? ($status['label'] ?? $key) : $status }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <select name="type" class="form-select form-select-sm">
              <option value="">All Types</option>
              @foreach($types as $key => $label)
                <option value="{{ $key }}" {{ ($filters['exhibition_type'] ?? '') == $key ? 'selected' : '' }}>
                  {{ is_array($label) ? ($label['label'] ?? $key) : $label }}
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('exhibition.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
          </div>
          <div class="col-auto ms-auto">
            <a href="{{ route('exhibition.add') }}" class="btn btn-success btn-sm">
              <i class="fas fa-plus"></i> New Exhibition
            </a>
          </div>
        </form>
      </div>

      <div class="card-body p-0">
        @if(empty($exhibitions) || (is_object($exhibitions) && $exhibitions->isEmpty()))
          <div class="p-4 text-center text-muted">
            <i class="fas fa-image fa-3x mb-3"></i>
            <p>No exhibitions found</p>
          </div>
        @else
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Title</th>
                <th>Type</th>
                <th>Status</th>
                <th>Opens</th>
                <th>Closes</th>
                <th>Venue</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($exhibitions as $exhibition)
                @php $ex = (object) $exhibition; @endphp
                <tr>
                  <td>
                    <a href="{{ route('exhibition.show', ['id' => $ex->id]) }}">
                      <strong>{{ $ex->title ?? '' }}</strong>
                    </a>
                    @if(!empty($ex->subtitle))
                      <br><small class="text-muted">{{ $ex->subtitle }}</small>
                    @endif
                  </td>
                  <td>{{ $ex->exhibition_type ?? '' }}</td>
                  <td>
                    <span class="badge bg-secondary">
                      {{ $ex->status ?? '' }}
                    </span>
                  </td>
                  <td>{{ $ex->start_date ?? '-' }}</td>
                  <td>{{ $ex->end_date ?? '-' }}</td>
                  <td>{{ $ex->venue ?? '-' }}</td>
                  <td class="text-end">
                    <div class="btn-group btn-group-sm">
                      <a href="{{ route('exhibition.show', ['id' => $ex->id]) }}" class="btn btn-outline-primary" title="View">
                        <i class="fas fa-eye"></i>
                      </a>
                      <a href="{{ route('exhibition.edit', ['id' => $ex->id]) }}" class="btn btn-outline-secondary" title="Edit">
                        <i class="fas fa-edit"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>

      @if($pages > 1)
        <div class="card-footer">
          <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
              @if($page > 1)
                <li class="page-item">
                  <a class="page-link" href="?page={{ $page - 1 }}">&laquo;</a>
                </li>
              @endif

              @for($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++)
                <li class="page-item {{ $i == $page ? 'active' : '' }}">
                  <a class="page-link" href="?page={{ $i }}">{{ $i }}</a>
                </li>
              @endfor

              @if($page < $pages)
                <li class="page-item">
                  <a class="page-link" href="?page={{ $page + 1 }}">&raquo;</a>
                </li>
              @endif
            </ul>
          </nav>
        </div>
      @endif
    </div>
  </div>

  <div class="col-md-3">
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0">Statistics</h5>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="d-flex justify-content-between mb-2">
            <span>Total Exhibitions</span>
            <strong>{{ $stats['total_exhibitions'] ?? ($stats['total'] ?? 0) }}</strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span>Currently Open</span>
            <strong class="text-success">{{ $stats['current_exhibitions'] ?? ($stats['active'] ?? 0) }}</strong>
          </li>
          <li class="d-flex justify-content-between mb-2">
            <span>Upcoming</span>
            <strong class="text-info">{{ $stats['upcoming_exhibitions'] ?? ($stats['upcoming'] ?? 0) }}</strong>
          </li>
          <li class="d-flex justify-content-between">
            <span>Objects on Display</span>
            <strong>{{ $stats['total_objects_on_display'] ?? 0 }}</strong>
          </li>
        </ul>
      </div>
    </div>

    @if(!empty($stats['by_status']))
      <div class="card mb-3">
        <div class="card-header">
          <h5 class="mb-0">By Status</h5>
        </div>
        <div class="card-body">
          @foreach($stats['by_status'] as $status => $count)
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge bg-secondary">{{ $status }}</span>
              <span>{{ $count }}</span>
            </div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Quick Actions</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('exhibition.dashboard') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-dashboard me-2"></i> Exhibition Dashboard
        </a>
        <a href="{{ route('exhibition.add') }}" class="list-group-item list-group-item-action">
          <i class="fas fa-plus me-2"></i> Create Exhibition
        </a>
      </div>
    </div>
  </div>
</div>
@endsection
