@extends('theme::layouts.2col')
@section('title', 'AI Risk Register (Article 9)')
@section('body-class', 'admin ai-compliance')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => []])
@endsection

@section('title-block')
  <h1>{{ __('AI Risk Register') }}</h1>
  <p class="text-muted small mb-0">{{ __('EU AI Act Article 9 - continuous risk management for high-risk AI systems') }}</p>
@endsection

@section('content')

@if (session('status'))
  <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if (session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="text-muted small">{{ __('Active risks') }}</div>
      <div class="fs-3 fw-bold">{{ $risks->where('status', 'active')->count() }}</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="text-muted small">{{ __('Open incidents') }}</div>
      <div class="fs-3 fw-bold">{{ $digest['open_incidents'] ?? 0 }}</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="text-muted small">{{ __('Overdue reviews') }}</div>
      <div class="fs-3 fw-bold {{ ($digest['overdue_reviews'] ?? 0) > 0 ? 'text-warning' : '' }}">{{ $digest['overdue_reviews'] ?? 0 }}</div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card text-center"><div class="card-body py-3">
      <div class="text-muted small">{{ __('Inferences (7d)') }}</div>
      <div class="fs-3 fw-bold">{{ array_sum((array) ($digest['inferences'] ?? [])) }}</div>
    </div></div>
  </div>
</div>

<form method="get" action="{{ route('ai-compliance.risk.index') }}" class="row g-2 mb-3">
  <div class="col-md-3">
    <select name="service" class="form-select form-select-sm">
      <option value="">{{ __('All services') }}</option>
      @foreach ($services as $svc)
        <option value="{{ $svc }}" @selected($filterService === $svc)>{{ strtoupper($svc) }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3">
    <select name="status" class="form-select form-select-sm">
      @foreach ($statuses as $st)
        <option value="{{ $st }}" @selected($filterStatus === $st)>{{ __(ucfirst($st)) }}</option>
      @endforeach
      <option value="*" @selected($filterStatus === '*')>{{ __('All') }}</option>
    </select>
  </div>
  <div class="col-md-2">
    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-funnel"></i> {{ __('Filter') }}</button>
  </div>
  <div class="col-md-4 text-end">
    <a href="{{ route('ai-compliance.risk.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add risk') }}</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-striped table-sm align-middle">
    <thead>
      <tr>
        <th>{{ __('Service') }}</th>
        <th>{{ __('Risk') }}</th>
        <th>{{ __('Severity') }}</th>
        <th>{{ __('Likelihood') }}</th>
        <th>{{ __('Affected group') }}</th>
        <th>{{ __('Residual') }}</th>
        <th>{{ __('Last reviewed') }}</th>
        <th class="text-end">{{ __('Actions') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($risks as $r)
        <tr>
          <td><span class="badge bg-secondary">{{ strtoupper($r->service) }}</span></td>
          <td>
            <div>{{ $r->risk_description }}</div>
            @if ($r->mitigation)
              <small class="text-muted d-block">{{ __('Mitigation') }}: {{ \Illuminate\Support\Str::limit($r->mitigation, 140) }}</small>
            @endif
            @if ($r->intended_or_misuse === 'misuse')
              <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> {{ __('Misuse-pathway risk') }}</small>
            @endif
          </td>
          <td>
            <span class="badge bg-{{ ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'][$r->severity] ?? 'secondary' }}">
              {{ __(ucfirst($r->severity)) }}
            </span>
          </td>
          <td><small>{{ __(ucfirst($r->likelihood)) }}</small></td>
          <td><small>{{ $r->affected_group ? $r->affected_group : '-' }}</small></td>
          <td>
            <span class="badge bg-{{ ['low' => 'success', 'medium' => 'info', 'high' => 'warning', 'critical' => 'danger'][$r->residual_risk] ?? 'secondary' }}">
              {{ __(ucfirst($r->residual_risk)) }}
            </span>
          </td>
          <td>
            @if ($r->last_reviewed_at)
              <small>{{ $r->last_reviewed_at->format('Y-m-d') }}</small>
            @else
              <small class="text-warning"><i class="bi bi-exclamation-circle"></i> {{ __('Never') }}</small>
            @endif
          </td>
          <td class="text-end text-nowrap">
            <a href="{{ route('ai-compliance.risk.edit', $r->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>

            <form method="post" action="{{ route('ai-compliance.risk.sign-off', $r->id) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('Sign-off review (writes receipt to inference chain)') }}">
                <i class="bi bi-check2-circle"></i>
              </button>
            </form>

            @if ($r->status === 'active')
              <form method="post" action="{{ route('ai-compliance.risk.archive', $r->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Archive this risk?') }}')">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ __('Archive') }}"><i class="bi bi-archive"></i></button>
              </form>
            @endif

            <button class="btn btn-sm btn-outline-info" data-bs-toggle="collapse" data-bs-target="#incident-form-{{ $r->id }}" title="{{ __('Report incident') }}"><i class="bi bi-flag"></i></button>
          </td>
        </tr>
        <tr id="incident-form-{{ $r->id }}" class="collapse">
          <td colspan="8">
            <form method="post" action="{{ route('ai-compliance.risk.incident', $r->id) }}" class="row g-2">
              @csrf
              <div class="col-md-7">
                <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="{{ __('What happened?') }}" required></textarea>
              </div>
              <div class="col-md-3">
                <select name="severity_observed" class="form-select form-select-sm" required>
                  <option value="low">{{ __('Low') }}</option>
                  <option value="medium" selected>{{ __('Medium') }}</option>
                  <option value="high">{{ __('High') }}</option>
                  <option value="critical">{{ __('Critical') }}</option>
                </select>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-warning w-100">{{ __('Record') }}</button>
              </div>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted">{{ __('No risks recorded.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

@endsection
