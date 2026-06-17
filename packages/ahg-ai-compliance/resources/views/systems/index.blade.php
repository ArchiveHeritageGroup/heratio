@extends('theme::layouts.2col')
@section('title', 'AI System Inventory')
@section('body-class', 'admin ai-compliance')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => []])
@endsection

@section('title-block')
  <h1>{{ __('AI System Inventory') }}</h1>
  <p class="text-muted small mb-0">{{ __('EU AI Act Art. 6 classification and Art. 52 transparency tiers - the register of AI systems you provide or deploy') }}</p>
@endsection

@section('content')

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif

@php
  $tierMeta = [
    'prohibited' => ['Prohibited', 'danger'],
    'high'       => ['High risk', 'warning'],
    'limited'    => ['Limited risk', 'info'],
    'minimal'    => ['Minimal risk', 'secondary'],
  ];
@endphp

<div class="row g-3 mb-4">
  @foreach ($tierMeta as $key => [$label, $colour])
    <div class="col-md-3">
      <div class="card text-center"><div class="card-body py-3">
        <div class="text-muted small">{{ __($label) }}</div>
        <div class="fs-3 fw-bold text-{{ $colour }}">{{ $tierCounts[$key] ?? 0 }}</div>
      </div></div>
    </div>
  @endforeach
</div>

@if ($reviewDue->isNotEmpty())
  <div class="alert alert-warning">
    <strong>{{ __('Review due') }}:</strong>
    {{ $reviewDue->count() }} {{ __('active system(s) need a review within 30 days (or are overdue).') }}
  </div>
@endif

<div class="d-flex justify-content-between align-items-end mb-3">
  <form method="get" class="row g-2">
    <div class="col-auto">
      <label class="form-label form-label-sm mb-0">{{ __('Risk tier') }}</label>
      <select name="risk" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">{{ __('All') }}</option>
        @foreach ($risks as $r)
          <option value="{{ $r }}" @selected($filterRisk === $r)>{{ ucfirst($r) }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto">
      <label class="form-label form-label-sm mb-0">{{ __('Lifecycle') }}</label>
      <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">{{ __('All') }}</option>
        @foreach ($statuses as $s)
          <option value="{{ $s }}" @selected($filterStatus === $s)>{{ ucfirst($s) }}</option>
        @endforeach
      </select>
    </div>
  </form>
  <a href="{{ route('ai-compliance.systems.create') }}" class="btn btn-primary btn-sm">
    <i class="fas fa-plus me-1"></i> {{ __('Add system') }}
  </a>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-hover align-middle">
    <thead>
      <tr>
        <th>{{ __('Name') }}</th>
        <th>{{ __('Role') }}</th>
        <th>{{ __('Risk tier') }}</th>
        <th>{{ __('Lifecycle') }}</th>
        <th>{{ __('Owner') }}</th>
        <th>{{ __('Next review') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($systems as $sys)
        <tr>
          <td>
            <a href="{{ route('ai-compliance.systems.edit', $sys->id) }}">{{ $sys->name }}</a>
            @unless ($sys->is_active)<span class="badge bg-secondary ms-1">{{ __('inactive') }}</span>@endunless
          </td>
          <td>{{ ucfirst($sys->role) }}</td>
          <td>
            <span class="badge bg-{{ $tierMeta[$sys->risk_classification][1] ?? 'secondary' }}">
              {{ $tierMeta[$sys->risk_classification][0] ?? ucfirst($sys->risk_classification) }}
            </span>
          </td>
          <td>{{ ucfirst($sys->lifecycle_status) }}</td>
          <td>{{ $sys->owner ?: '-' }}</td>
          <td>{{ $sys->next_review_date ? $sys->next_review_date->format('Y-m-d') : '-' }}</td>
          <td class="text-end">
            <a href="{{ route('ai-compliance.systems.edit', $sys->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
            <form method="post" action="{{ route('ai-compliance.systems.destroy', $sys->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Remove this AI system from the inventory?') }}');">
              @csrf @method('DELETE')
              <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No AI systems recorded yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
