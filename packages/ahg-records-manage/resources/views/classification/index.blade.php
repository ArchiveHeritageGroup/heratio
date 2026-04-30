{{--
  RM Classification Rules — index (P4.2)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Classification Rules')
@section('body-class', 'admin records classification')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-magic me-2"></i> Classification Rules</h1>
  <div>
    <a href="{{ route('records.classification.create') }}" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>New rule</a>
    <form method="POST" action="{{ route('records.classification.run-batch') }}" class="d-inline" onsubmit="return confirm('Run classification across up to 1,000 unclassified records? This may take a while.');">
      @csrf
      <input type="hidden" name="limit" value="1000">
      <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-bolt me-1"></i>Batch run</button>
    </form>
    <a href="{{ route('records.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Records dashboard</a>
  </div>
</div>

<p class="text-muted small">
  Auto-classification rules. Higher priority evaluated first; first match wins. Each match writes to <code>rm_classification_log</code> for audit and (when the rule supplies a disposal class) creates an <code>rm_record_disposal_class</code> binding.
</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row g-2 mb-3">
  <div class="col"><div class="card border-secondary"><div class="card-body py-2"><small class="text-muted">Total rules</small><h4 class="mb-0">{{ $counts['total_rules'] }}</h4></div></div></div>
  <div class="col"><div class="card border-success"><div class="card-body py-2"><small class="text-muted">Active</small><h4 class="mb-0 text-success">{{ $counts['active_rules'] }}</h4></div></div></div>
  <div class="col"><div class="card border-info"><div class="card-body py-2"><small class="text-muted">Records classified</small><h4 class="mb-0 text-info">{{ $counts['classified_records'] }}</h4></div></div></div>
</div>

<form method="GET" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Type') }}</label>
    <select name="rule_type" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      @foreach($ruleTypes as $rt)<option value="{{ $rt->code }}" @selected($filters['rule_type']===$rt->code)>{{ $rt->label }}</option>@endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Active') }}</label>
    <select name="is_active" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      <option value="1" @selected($filters['is_active']==='1')>{{ __('Active') }}</option>
      <option value="0" @selected($filters['is_active']==='0')>{{ __('Inactive') }}</option>
    </select>
  </div>
  <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button></div>
</form>

<div class="card mb-3">
  <table class="table table-hover table-sm mb-0">
    <thead class="table-light">
      <tr><th>{{ __('Pri') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Pattern') }}</th><th>{{ __('File plan node') }}</th><th>{{ __('Apply on') }}</th><th>{{ __('Active') }}</th><th class="text-end"></th></tr>
    </thead>
    <tbody>
    @forelse($rules as $r)
      <tr>
        <td><small>{{ $r->priority }}</small></td>
        <td><strong>{{ $r->name }}</strong></td>
        <td><span class="badge bg-secondary">{{ $r->rule_type }}</span></td>
        <td><code class="small">{{ \Illuminate\Support\Str::limit($r->match_pattern, 60) }}</code></td>
        <td><small>{{ $r->fileplan_code ?? '?' }} — {{ $r->fileplan_title ?? '?' }}</small></td>
        <td><small>{{ $r->apply_on }}</small></td>
        <td>
          @if($r->is_active)<i class="fas fa-check-circle text-success"></i>@else<i class="fas fa-circle text-muted"></i>@endif
        </td>
        <td class="text-end">
          <a href="{{ route('records.classification.show', $r->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-right"></i></a>
        </td>
      </tr>
    @empty
      <tr><td colspan="8" class="text-muted text-center py-4">No classification rules yet. Use <strong>New rule</strong> to add the first one.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

@if(! empty($stats))
<div class="card">
  <div class="card-header bg-light"><i class="fas fa-fire me-1"></i> Top firing rules (last 50)</div>
  <table class="table table-sm mb-0">
    <thead class="table-light"><tr><th>{{ __('Rule') }}</th><th>{{ __('Type') }}</th><th class="text-end">{{ __('Fires') }}</th><th>{{ __('Last') }}</th></tr></thead>
    <tbody>
    @foreach($stats as $s)
      <tr>
        <td><a href="{{ route('records.classification.show', $s->id) }}">{{ $s->name }}</a></td>
        <td><small>{{ $s->rule_type }}</small></td>
        <td class="text-end"><span class="badge bg-info text-dark">{{ $s->fires }}</span></td>
        <td><small>{{ $s->last_fired }}</small></td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif
@endsection
