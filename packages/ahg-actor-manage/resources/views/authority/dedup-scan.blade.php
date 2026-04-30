@extends('theme::layouts.1col')

@section('title', 'Dedup Scan Results')
@section('body-class', 'authority dedup-scan')

@section('content')

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dashboard') }}">Authority Dashboard</a>
    </li>
    <li class="breadcrumb-item">
      <a href="{{ route('actor.dedup') }}">Deduplication</a>
    </li>
    <li class="breadcrumb-item active">Scan Results</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-clone me-2"></i>Dedup Scan Results</h1>

@if (session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
  <div class="card-header d-flex justify-content-between" style="background: var(--ahg-primary); color: #fff;">
    <span>{{ count($pairs) }} potential duplicate pair(s)</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>{{ __('Actor A') }}</th>
          <th>{{ __('Actor B') }}</th>
          <th class="text-center">{{ __('Score') }}</th>
          <th>{{ __('Match') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @if (empty($pairs))
          <tr><td colspan="5" class="text-center text-muted py-4">No duplicates found above the threshold.</td></tr>
        @else
          @foreach ($pairs as $pair)
            @php
              $matchColors = ['exact' => 'danger', 'strong' => 'warning', 'possible' => 'info', 'weak' => 'secondary'];
              $color = $matchColors[$pair['match_type']] ?? 'secondary';
            @endphp
            <tr>
              <td>{{ e($pair['actor_a_name']) }}</td>
              <td>{{ e($pair['actor_b_name']) }}</td>
              <td class="text-center">
                <strong>{{ number_format($pair['score'] * 100, 1) }}%</strong>
              </td>
              <td>
                <span class="badge bg-{{ $color }}">{{ ucfirst($pair['match_type']) }}</span>
              </td>
              <td>
                <a href="{{ route('actor.dedup.compare', ['id' => $pair['actor_a_id'], 'secondary_id' => $pair['actor_b_id']]) }}"
                   class="btn btn-sm atom-btn-white">
                  <i class="fas fa-columns me-1"></i>{{ __('Compare') }}
                </a>
              </td>
            </tr>
          @endforeach
        @endif
      </tbody>
    </table>
  </div>
</div>

@endsection
