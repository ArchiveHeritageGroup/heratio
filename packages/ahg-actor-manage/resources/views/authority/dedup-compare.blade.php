@extends('theme::layouts.1col')

@section('title', 'Compare Authority Records')
@section('body-class', 'authority dedup-compare')

@section('content')

@php
  $primary = isset($comparison['primary']) ? (object) $comparison['primary'] : null;
  $secondary = isset($comparison['secondary']) ? (object) $comparison['secondary'] : null;
  $fields = $comparison['comparison'] ?? [];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dedup') }}">Deduplication</a>
    </li>
    <li class="breadcrumb-item active">Compare</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-columns me-2"></i>Compare Authority Records</h1>

@if (!$primary || !$secondary)
  <div class="alert alert-warning">Could not load both records for comparison.</div>
@else

  <div class="card mb-3">
    <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
      <i class="fas fa-columns me-1"></i>Field Comparison
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:20%">{{ __('Field') }}</th>
            <th style="width:38%">Primary: {{ e($primary->authorized_form_of_name ?? '') }}</th>
            <th style="width:38%">Secondary: {{ e($secondary->authorized_form_of_name ?? '') }}</th>
            <th style="width:4%"></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($fields as $fieldName => $info)
            @php $info = (array) $info; @endphp
            <tr>
              <td><strong>{{ str_replace('_', ' ', ucfirst($fieldName)) }}</strong></td>
              <td>
                <small>{!! nl2br(e(mb_substr($info['primary'] ?? '', 0, 300))) !!}</small>
              </td>
              <td>
                <small>{!! nl2br(e(mb_substr($info['secondary'] ?? '', 0, 300))) !!}</small>
              </td>
              <td>
                @if ($info['match'] ?? false)
                  <i class="fas fa-check text-success"></i>
                @else
                  <i class="fas fa-times text-danger"></i>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  {{-- Counts --}}
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <small class="text-muted">{{ __('Primary Relations') }}</small>
          <h5 class="mb-0">{{ $comparison['primary_relations'] ?? 0 }}</h5>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <small class="text-muted">{{ __('Secondary Relations') }}</small>
          <h5 class="mb-0">{{ $comparison['secondary_relations'] ?? 0 }}</h5>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <small class="text-muted">{{ __('Primary Resources') }}</small>
          <h5 class="mb-0">{{ $comparison['primary_resources'] ?? 0 }}</h5>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-center">
        <div class="card-body py-2">
          <small class="text-muted">{{ __('Secondary Resources') }}</small>
          <h5 class="mb-0">{{ $comparison['secondary_resources'] ?? 0 }}</h5>
        </div>
      </div>
    </div>
  </div>

  {{-- Actions --}}
  <div class="d-flex gap-2">
    <a href="{{ route('actor.merge', ['id' => $primary->id ?? 0]) }}" class="btn btn-warning">
      <i class="fas fa-compress-arrows-alt me-1"></i>{{ __('Merge into Primary') }}
    </a>
    <a href="{{ route('actor.dedup') }}" class="btn atom-btn-white">
      Back to Dedup
    </a>
  </div>

@endif

@endsection
