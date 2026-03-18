@extends('theme::layouts.1col')

@section('title', 'Condition Checks — ' . ($io->title ?? 'Untitled'))

@section('content')
<div class="container-fluid py-4">

  {{-- Breadcrumb --}}
  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('informationobject.show', ['slug' => $io->slug ?? $io->id]) }}">{{ $io->title ?? 'Untitled' }}</a></li>
      <li class="breadcrumb-item active" aria-current="page">Condition Checks</li>
    </ol>
  </nav>

  {{-- Action bar --}}
  <div class="d-flex justify-content-between align-items-center mb-4">
    <a href="{{ route('informationobject.show', ['slug' => $io->slug ?? $io->id]) }}" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
    @auth
      <a href="{{ route('io.condition.create', ['slug' => $io->slug ?? $io->id]) }}" class="btn btn-success">
        <i class="fas fa-plus me-1"></i> New Condition Check
      </a>
    @endauth
  </div>

  @if($checks->isNotEmpty())
    @php
      $latestCondition = $checks->first();
    @endphp

    {{-- Latest Condition card --}}
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="fas fa-clipboard-check me-2"></i> Latest Condition</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-3">
            <strong>Date</strong>
            <p>{{ $latestCondition->check_date ?? '—' }}</p>
          </div>
          <div class="col-md-3">
            <strong>Status</strong>
            <p>
              @php
                $status = strtolower(trim($latestCondition->condition_rating ?? ''));
                if (in_array($status, ['good', 'excellent'])) {
                    $badgeClass = 'bg-success';
                } elseif ($status === 'fair') {
                    $badgeClass = 'bg-warning text-dark';
                } elseif (in_array($status, ['poor', 'critical'])) {
                    $badgeClass = 'bg-danger';
                } else {
                    $badgeClass = 'bg-secondary';
                }
              @endphp
              <span class="badge {{ $badgeClass }}">{{ $latestCondition->condition_rating ?? '—' }}</span>
            </p>
          </div>
          <div class="col-md-3">
            <strong>Assessor</strong>
            <p>{{ $latestCondition->assessor ?? '—' }}</p>
          </div>
          <div class="col-md-3">
            <strong>Photos</strong>
            <p>
              <a href="{{ route('io.condition.show', ['id' => $latestCondition->id ?? 0]) }}#photos" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-camera me-1"></i> View Photos
              </a>
            </p>
          </div>
        </div>
        @if($latestCondition->notes ?? false)
          <div class="row mt-2">
            <div class="col-12">
              <strong>Notes</strong>
              <p>{{ $latestCondition->notes }}</p>
            </div>
          </div>
        @endif
      </div>
    </div>

    {{-- Condition History card --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-history me-2"></i> Condition History</h5>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Status</th>
                <th>Type</th>
                <th>Assessor</th>
                <th>Notes</th>
                <th class="text-end">Photos</th>
              </tr>
            </thead>
            <tbody>
              @foreach($checks as $check)
                <tr>
                  <td>{{ $check->check_date ?? '—' }}</td>
                  <td>
                    @php
                      $checkStatus = strtolower(trim($check->condition_rating ?? ''));
                      if (in_array($checkStatus, ['good', 'excellent'])) {
                          $checkBadgeClass = 'bg-success';
                      } elseif ($checkStatus === 'fair') {
                          $checkBadgeClass = 'bg-warning text-dark';
                      } elseif (in_array($checkStatus, ['poor', 'critical'])) {
                          $checkBadgeClass = 'bg-danger';
                      } else {
                          $checkBadgeClass = 'bg-secondary';
                      }
                    @endphp
                    <span class="badge {{ $checkBadgeClass }}">{{ $check->condition_rating ?? '—' }}</span>
                  </td>
                  <td>{{ $check->check_type ?? '—' }}</td>
                  <td>{{ $check->assessor ?? '—' }}</td>
                  <td>{{ \Illuminate\Support\Str::limit($check->notes ?? '', 80) }}</td>
                  <td class="text-end">
                    <a href="{{ route('io.condition.show', ['id' => $check->id ?? 0]) }}#photos" class="btn btn-sm btn-outline-primary" title="View photos">
                      <i class="fas fa-camera"></i>
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

  @else

    {{-- Empty state --}}
    <div class="text-center py-5">
      <div class="mb-4">
        <i class="fas fa-clipboard-check fa-4x text-muted"></i>
      </div>
      <h4 class="text-muted">No Condition Checks Recorded</h4>
      <p class="text-muted mb-4">
        No condition assessments have been recorded for this resource yet.
      </p>
      @auth
        <a href="{{ route('io.condition.create', ['slug' => $io->slug ?? $io->id]) }}" class="btn btn-success btn-lg">
          <i class="fas fa-plus me-1"></i> Create First Condition Check
        </a>
      @endauth
    </div>

  @endif

</div>
@endsection
