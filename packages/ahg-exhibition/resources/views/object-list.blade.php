{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.

  Exhibition object list report: objects grouped by section with display
  location and insurance totals; printable, with CSV export.
--}}
@extends('theme::layouts.1col')

@section('title', __('Exhibition Object List'))

@section('content')
@php
  $exId = $exhibition->id;
  $objects = collect($objects ?? []);
  $sections = collect($sections ?? []);
  $currency = $exhibition->budget_currency ?: '';
  $totalValue = (float) $objects->sum(fn ($o) => (float) ($o->insurance_value ?? 0));
  $loanCount = $objects->filter(fn ($o) => !empty($o->requires_loan))->count();

  // Section groups in section order; objects with no (or a missing) section last.
  $groups = $sections->map(fn ($s) => ['section' => $s, 'objects' => $objects->where('section_id', $s->id)->values()])
    ->filter(fn ($g) => $g['objects']->isNotEmpty())->values();
  $unassigned = $objects->filter(fn ($o) => empty($o->section_id) || !$sections->contains('id', $o->section_id))->values();
  if ($unassigned->isNotEmpty()) {
    $groups->push(['section' => null, 'objects' => $unassigned]);
  }
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="d-print-none">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('exhibition.index') }}">{{ __('Exhibitions') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('exhibition.show', ['id' => $exId]) }}">{{ $exhibition->title }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('Object List') }}</li>
  </ol>
</nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 d-print-none">
  <div>
    <h1>{{ __('Exhibition Object List') }}</h1>
    <p class="text-muted mb-0">{{ $exhibition->title }}</p>
  </div>
  <div class="btn-group">
    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
      <i class="fas fa-print me-1"></i>{{ __('Print') }}
    </button>
    <a href="{{ route('exhibition.objectListCsv', ['id' => $exId]) }}" class="btn btn-outline-secondary">
      <i class="fas fa-download me-1"></i>{{ __('Export CSV') }}
    </a>
  </div>
</div>

{{-- Print header --}}
<div class="d-none d-print-block mb-4">
  <div class="row">
    <div class="col-8">
      <h2>{{ $exhibition->title }}</h2>
      @if(!empty($exhibition->subtitle))
        <p class="lead">{{ $exhibition->subtitle }}</p>
      @endif
    </div>
    <div class="col-4 text-end">
      <p class="mb-1"><strong>{{ __('Object Checklist') }}</strong></p>
      <p class="mb-1">{{ __('Generated: :date', ['date' => now()->format('d M Y')]) }}</p>
      <p class="mb-0">{{ __('Objects: :n', ['n' => $objects->count()]) }}</p>
    </div>
  </div>
</div>

{{-- Summary --}}
<div class="row g-3 mb-4 d-print-none">
  <div class="col-md-3 col-6">
    <div class="card bg-light h-100"><div class="card-body text-center">
      <h3 class="mb-0">{{ $objects->count() }}</h3><small class="text-muted">{{ __('Total Objects') }}</small>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card bg-light h-100"><div class="card-body text-center">
      <h3 class="mb-0">{{ $sections->count() }}</h3><small class="text-muted">{{ __('Sections') }}</small>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card bg-light h-100"><div class="card-body text-center">
      <h3 class="mb-0">{{ number_format($totalValue, 0) }} {{ $currency }}</h3><small class="text-muted">{{ __('Total Insurance Value') }}</small>
    </div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card bg-light h-100"><div class="card-body text-center">
      <h3 class="mb-0">{{ $loanCount }}</h3><small class="text-muted">{{ __('Loan Objects') }}</small>
    </div></div>
  </div>
</div>

@if($objects->isEmpty())
  <div class="card">
    <div class="card-body text-center py-5">
      <i class="fas fa-archive fa-3x text-muted mb-3"></i>
      <h5>{{ __('No objects in this exhibition') }}</h5>
      <p class="text-muted">{{ __('Add objects to generate the object list.') }}</p>
      <a href="{{ route('exhibition.objects', ['id' => $exId]) }}" class="btn btn-primary d-print-none">
        <i class="fas fa-plus me-1"></i>{{ __('Add Objects') }}
      </a>
    </div>
  </div>
@else
  @foreach($groups as $group)
    @php $section = $group['section']; $rows = $group['objects']; @endphp
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">{{ $section ? $section->title : __('Unassigned Objects') }}</h5>
          @if($section && !empty($section->gallery_name))
            <small class="text-muted">{{ $section->gallery_name }}</small>
          @endif
        </div>
        <span class="badge bg-primary">{{ trans_choice(':n object|:n objects', $rows->count(), ['n' => $rows->count()]) }}</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead>
              <tr>
                <th style="width: 30px;">#</th>
                <th>{{ __('Object Number') }}</th>
                <th>{{ __('Title/Description') }}</th>
                <th>{{ __('Display Location') }}</th>
                <th class="text-end">{{ __('Insurance Value') }}</th>
                <th class="d-print-none"><span class="visually-hidden">{{ __('View') }}</span></th>
              </tr>
            </thead>
            <tbody>
              @foreach($rows as $i => $obj)
                <tr>
                  <td class="text-center">{{ $i + 1 }}</td>
                  <td>{{ $obj->identifier ?: '-' }}</td>
                  <td>
                    {{ $obj->object_title ?: __('Untitled') }}
                    @if(!empty($obj->requires_loan))
                      <span class="badge bg-warning text-dark">{{ __('Loan') }}</span>
                      @if(!empty($obj->lender_institution))<small class="text-muted">{{ $obj->lender_institution }}</small>@endif
                    @endif
                  </td>
                  <td>{{ $obj->display_position ?: '-' }}</td>
                  <td class="text-end">
                    @if(!empty($obj->insurance_value))
                      {{ number_format((float) $obj->insurance_value, 2) }} {{ $currency }}
                    @else
                      -
                    @endif
                  </td>
                  <td class="d-print-none">
                    @if(!empty($obj->slug))
                      <a href="{{ url('/'.$obj->slug) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View record') }}"><i class="fas fa-eye"></i></a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
            @if($section)
              <tfoot>
                <tr class="table-light">
                  <td colspan="4" class="text-end"><strong>{{ __('Section Total:') }}</strong></td>
                  <td class="text-end"><strong>{{ number_format((float) $rows->sum(fn ($o) => (float) ($o->insurance_value ?? 0)), 2) }} {{ $currency }}</strong></td>
                  <td class="d-print-none"></td>
                </tr>
              </tfoot>
            @endif
          </table>
        </div>
      </div>
    </div>
  @endforeach

  <div class="card bg-light">
    <div class="card-body">
      <div class="row">
        <div class="col-md-8"><h5 class="mb-0">{{ __('Grand Total') }}</h5></div>
        <div class="col-md-4 text-md-end">
          <h4 class="mb-0">{{ number_format($totalValue, 2) }} {{ $currency }}</h4>
          <small class="text-muted">{{ __(':n objects', ['n' => $objects->count()]) }}</small>
        </div>
      </div>
    </div>
  </div>
@endif

{{-- Print footer --}}
<div class="d-none d-print-block mt-4">
  <div class="row">
    <div class="col-6">
      <p class="small text-muted mb-0">
        {{ __('Exhibition: :title', ['title' => $exhibition->title]) }}<br>
        @if(!empty($exhibition->opening_date))
          {{ __('Dates:') }} {{ $exhibition->opening_date }}@if(!empty($exhibition->closing_date)) - {{ $exhibition->closing_date }}@endif
        @endif
      </p>
    </div>
    <div class="col-6 text-end">
      <p class="small text-muted mb-0">{{ __('Generated: :date', ['date' => now()->format('d M Y H:i')]) }}</p>
    </div>
  </div>
</div>

@push('css')
<style>
@media print {
  .card { border: 1px solid #ddd !important; box-shadow: none !important; break-inside: avoid; }
  .card-header { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .badge { border: 1px solid #999; }
  table { font-size: 11px; }
  @page { margin: 1.5cm; }
}
</style>
@endpush
@endsection
