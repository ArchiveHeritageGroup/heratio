@extends('theme::layouts.1col')
@section('title', __('Compliance Rules'))
@section('body-class', 'admin heritage')

@section('content')
@php
  $categoryColors = ['recognition' => 'primary', 'measurement' => 'success', 'disclosure' => 'info'];
  $severityColors = ['error' => 'danger', 'warning' => 'warning', 'info' => 'secondary'];
@endphp
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="d-flex align-items-center">
        <a href="{{ route('heritage.hadmin.standard-list') }}" class="btn btn-outline-secondary btn-sm me-2" aria-label="{{ __('Back to standards') }}"><i class="fas fa-arrow-left"></i></a>
        <h1 class="h3 mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('Compliance Rules') }}</h1>
      </div>
      <a href="{{ route('heritage.hadmin.rule-add', array_filter(['standard_id' => $standardId])) }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Rule') }}</a>
    </div>

    <form method="get" class="card mb-3">
      <div class="card-body py-2 row g-2 align-items-center">
        <div class="col-auto"><label class="form-label mb-0" for="ruleStd">{{ __('Filter by Standard:') }}</label></div>
        <div class="col-md-5">
          <select id="ruleStd" name="standard_id" class="form-select form-select-sm">
            <option value="">{{ __('All Standards') }}</option>
            @foreach($standards as $s)
              <option value="{{ $s->id }}" {{ (int) $standardId === (int) $s->id ? 'selected' : '' }}>{{ $s->code }} - {{ $s->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-auto"><button type="submit" class="btn btn-sm atom-btn-white">{{ __('Apply') }}</button></div>
        <div class="col-auto ms-auto small text-muted">{{ __(':n rules', ['n' => count($rules)]) }}</div>
      </div>
    </form>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>{{ __('Standard') }}</th>
              <th>{{ __('Category') }}</th>
              <th>{{ __('Code') }}</th>
              <th>{{ __('Rule Name') }}</th>
              <th>{{ __('Field') }}</th>
              <th>{{ __('Severity') }}</th>
              <th class="text-center">{{ __('Status') }}</th>
              <th style="width:130px"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rules as $r)
              <tr class="{{ $r->is_active ? '' : 'table-secondary' }}">
                <td><code>{{ $r->standard_code }}</code></td>
                <td><span class="badge bg-{{ $categoryColors[$r->category] ?? 'secondary' }}">{{ $categories[$r->category] ?? ucfirst($r->category) }}</span></td>
                <td><code>{{ $r->code }}</code></td>
                <td>
                  <strong>{{ $r->name }}</strong>
                  @if($r->reference)<br><small class="text-muted">{{ $r->reference }}</small>@endif
                </td>
                <td><code class="text-muted">{{ $r->field_name }}</code></td>
                <td><span class="badge bg-{{ $severityColors[$r->severity] ?? 'secondary' }}">{{ $severities[$r->severity] ?? ucfirst((string) $r->severity) }}</span></td>
                <td class="text-center">
                  @if($r->is_active)<i class="fas fa-check-circle text-success" title="{{ __('Active') }}"></i>@else<i class="fas fa-times-circle text-danger" title="{{ __('Inactive') }}"></i>@endif
                </td>
                <td class="text-end text-nowrap">
                  <a href="{{ route('heritage.hadmin.rule-edit', $r->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-edit"></i></a>
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.rule-toggle', $r->id),
                    'class' => 'btn btn-sm btn-outline-' . ($r->is_active ? 'warning' : 'success'),
                    'icon' => 'fas fa-' . ($r->is_active ? 'toggle-on' : 'toggle-off'),
                    'title' => $r->is_active ? __('Deactivate') : __('Activate'),
                  ])
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.rule-delete', $r->id), 'method' => 'DELETE',
                    'class' => 'btn btn-sm btn-outline-danger', 'icon' => 'fas fa-trash', 'title' => __('Delete'),
                    'confirm' => __('Delete this rule?'),
                  ])
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No rules found') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>{{ __('About Compliance Rules') }}</div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-4">
            <h6>{{ __('Categories') }}</h6>
            <ul class="list-unstyled mb-0">
              <li><span class="badge bg-primary">{{ __('Recognition') }}</span> - {{ __('Asset identification requirements') }}</li>
              <li><span class="badge bg-success">{{ __('Measurement') }}</span> - {{ __('Valuation requirements') }}</li>
              <li><span class="badge bg-info">{{ __('Disclosure') }}</span> - {{ __('Documentation requirements') }}</li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6>{{ __('Severity Levels') }}</h6>
            <ul class="list-unstyled mb-0">
              <li><span class="badge bg-danger">{{ __('Error') }}</span> - {{ __('Must fix for compliance') }}</li>
              <li><span class="badge bg-warning text-dark">{{ __('Warning') }}</span> - {{ __('Should be addressed') }}</li>
              <li><span class="badge bg-secondary">{{ __('Info') }}</span> - {{ __('Best practice recommendation') }}</li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6>{{ __('Check Types') }}</h6>
            <ul class="list-unstyled mb-0">
              <li><code>required_field</code> - {{ __('Field must have a value') }}</li>
              <li><code>value_check</code> - {{ __('Numeric comparison (>0, >=1)') }}</li>
              <li><code>date_check</code> - {{ __('Valid date required') }}</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
