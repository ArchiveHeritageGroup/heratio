{{--
  Records Management — Compliance assessments index (P2.8)
  @copyright Johan Pieterse / Plain Sailing Information Systems
  @license   AGPL-3.0-or-later
--}}
@extends('theme::layouts.1col')
@section('title', 'Compliance Assessments')
@section('body-class', 'admin records compliance')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-clipboard-check me-2"></i> Compliance Assessments</h1>
  <div>
    <a href="{{ route('records.compliance.create') }}" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>New assessment</a>
    <a href="{{ route('records.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Records dashboard</a>
  </div>
</div>

<p class="text-muted small">
  Run automated checks against the live RM data plane and produce a scored, signed-off compliance report. Frameworks: ISO 15489, ISO 16175, MoReq2010, DoD 5015.2, ISO 30300, ISO 23081.
</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="GET" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label small mb-0">Framework</label>
    <select name="framework" class="form-select form-select-sm">
      <option value="">All</option>
      @foreach($frameworks as $f)<option value="{{ $f->code }}" @selected($filters['framework']===$f->code)>{{ $f->label }}</option>@endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">Status</label>
    <select name="status" class="form-select form-select-sm">
      <option value="">All</option>
      <option value="in_progress" @selected($filters['status']==='in_progress')>In progress</option>
      <option value="finalised"   @selected($filters['status']==='finalised')>Finalised</option>
    </select>
  </div>
  <div class="col-md-2"><button type="submit" class="btn btn-sm btn-primary">Filter</button></div>
</form>

<div class="card">
  <table class="table table-hover table-sm mb-0">
    <thead class="table-light">
      <tr><th>Ref</th><th>Framework</th><th>Title</th><th>Period</th><th>Score</th><th>Status</th><th class="text-end"></th></tr>
    </thead>
    <tbody>
    @forelse($rows as $a)
      @php
        $pct = ($a->score_max && (float) $a->score_max > 0) ? round(((float) $a->score_total / (float) $a->score_max) * 100) : null;
        $bandClass = $pct === null ? 'secondary' : ($pct >= 80 ? 'success' : ($pct >= 50 ? 'warning text-dark' : 'danger'));
      @endphp
      <tr>
        <td><code>{{ $a->assessment_ref }}</code></td>
        <td><small>{{ $a->framework }}</small></td>
        <td>{{ $a->title }}</td>
        <td><small>{{ $a->period_start ? $a->period_start : '—' }} → {{ $a->period_end ?: '—' }}</small></td>
        <td>
          @if($pct !== null)
            <span class="badge bg-{{ $bandClass }}">{{ $pct }}%</span>
            <small class="text-muted">{{ rtrim(rtrim((string) $a->score_total, '0'), '.') }}/{{ rtrim(rtrim((string) $a->score_max, '0'), '.') }}</small>
          @else
            <span class="text-muted small">not run</span>
          @endif
        </td>
        <td><span class="badge bg-{{ $a->status === 'finalised' ? 'success' : 'warning text-dark' }}">{{ $a->status }}</span></td>
        <td class="text-end"><a href="{{ route('records.compliance.show', $a->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-right"></i></a></td>
      </tr>
    @empty
      <tr><td colspan="7" class="text-center text-muted py-4">No assessments yet. Click <strong>New assessment</strong> to run the first one.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>
@endsection
