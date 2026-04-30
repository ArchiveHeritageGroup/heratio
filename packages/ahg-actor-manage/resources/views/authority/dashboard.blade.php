@extends('theme::layouts.1col')

@section('title', 'Authority Records Dashboard')
@section('body-class', 'authority dashboard')

@section('content')

@php
  $byLevel = $stats['by_level'] ?? [];
  $totalActors = $stats['total_actors'] ?? 0;
  $totalScored = $stats['total_scored'] ?? 0;
  $avgScore = $stats['avg_score'] ?? 0;
  $unscored = $stats['unscored'] ?? 0;
  $levelColors = ['stub' => 'danger', 'minimal' => 'warning', 'partial' => 'info', 'full' => 'success'];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item active">Authority Dashboard</li>
  </ol>
</nav>

<h1 class="mb-4"><i class="fas fa-id-card me-2"></i>Authority Records Dashboard</h1>

{{-- KPI Cards --}}
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center border-primary">
      <div class="card-body">
        <h3 class="mb-0">{{ number_format($totalActors) }}</h3>
        <small class="text-muted">{{ __('Total Actors') }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-info">
      <div class="card-body">
        <h3 class="mb-0">{{ number_format($totalScored) }}</h3>
        <small class="text-muted">{{ __('Scored') }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-warning">
      <div class="card-body">
        <h3 class="mb-0">{{ number_format($unscored) }}</h3>
        <small class="text-muted">{{ __('Unscored') }}</small>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center border-success">
      <div class="card-body">
        <h3 class="mb-0">{{ $avgScore }}%</h3>
        <small class="text-muted">{{ __('Average Score') }}</small>
      </div>
    </div>
  </div>
</div>

{{-- Completeness by Level + External Identifiers --}}
<div class="row g-3 mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
        <i class="fas fa-chart-pie me-1"></i>Completeness by Level
      </div>
      <div class="card-body">
        <canvas id="completenessChart" height="250"></canvas>
        <div class="mt-3">
          @foreach (['stub', 'minimal', 'partial', 'full'] as $level)
            @php $count = isset($byLevel[$level]) ? $byLevel[$level]->count : 0; @endphp
            <div class="d-flex justify-content-between mb-1">
              <span>
                <span class="badge bg-{{ $levelColors[$level] }}">{{ ucfirst($level) }}</span>
              </span>
              <strong>{{ number_format($count) }}</strong>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
        <i class="fas fa-link me-1"></i>External Identifiers
      </div>
      <div class="card-body">
        @if (empty($identifierStats))
          <p class="text-muted">No external identifiers recorded yet.</p>
        @else
          <table class="table table-sm">
            <thead>
              <tr>
                <th>{{ __('Source') }}</th>
                <th class="text-end">{{ __('Count') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($identifierStats as $stat)
                <tr>
                  <td>
                    <i class="fas fa-external-link-alt me-1 text-muted"></i>
                    {{ ucfirst($stat->identifier_type) }}
                  </td>
                  <td class="text-end">{{ number_format($stat->count) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Quick Actions --}}
<div class="card mb-3">
  <div class="card-header" style="background: var(--ahg-primary); color: #fff;">
    <i class="fas fa-bolt me-1"></i>Quick Actions
  </div>
  <div class="card-body">
    <div class="row g-2">
      <div class="col-auto">
        <a href="{{ route('actor.workqueue') }}" class="btn atom-btn-white">
          <i class="fas fa-tasks me-1"></i>{{ __('Workqueue') }}
        </a>
      </div>
      <div class="col-auto">
        <a href="{{ route('actor.dedup') }}" class="btn atom-btn-white">
          <i class="fas fa-clone me-1"></i>{{ __('Deduplication') }}
        </a>
      </div>
      <div class="col-auto">
        <a href="{{ route('actor.ner') }}" class="btn atom-btn-white">
          <i class="fas fa-robot me-1"></i>{{ __('NER Pipeline') }}
        </a>
      </div>
      <div class="col-auto">
        <a href="{{ route('actor.function.browse') }}" class="btn atom-btn-white">
          <i class="fas fa-sitemap me-1"></i>{{ __('Functions Browse') }}
        </a>
      </div>
      <div class="col-auto">
        <a href="{{ route('actor.config') }}" class="btn atom-btn-white">
          <i class="fas fa-cog me-1"></i>{{ __('Configuration') }}
        </a>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Chart !== 'undefined') {
    var ctx = document.getElementById('completenessChart');
    if (ctx) {
      new Chart(ctx.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Stub', 'Minimal', 'Partial', 'Full'],
          datasets: [{
            data: [
              {{ isset($byLevel['stub']) ? $byLevel['stub']->count : 0 }},
              {{ isset($byLevel['minimal']) ? $byLevel['minimal']->count : 0 }},
              {{ isset($byLevel['partial']) ? $byLevel['partial']->count : 0 }},
              {{ isset($byLevel['full']) ? $byLevel['full']->count : 0 }}
            ],
            backgroundColor: ['#dc3545', '#ffc107', '#0dcaf0', '#198754']
          }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
      });
    }
  }
});
</script>

@endsection
