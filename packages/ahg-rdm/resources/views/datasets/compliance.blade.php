@extends('theme::layouts.1col')

@section('title', __('RDM Compliance Scoreboard'))
@section('body-class', 'rdm compliance')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h1 class="h4 mb-0"><i class="fas fa-clipboard-check me-2"></i>{{ __('RDM Compliance Scoreboard') }}</h1>
  <a href="{{ route('rdm.datasets.index') }}" class="btn btn-outline-secondary btn-sm">{{ __('All datasets') }}</a>
</div>

{{-- Summary strip --}}
<div class="row g-2 mb-3">
  @foreach ([
    ['total','Datasets','#0d6efd'], ['flagged','POPIA-flagged','#dc3545'],
    ['restricted','Restricted/embargoed','#fd7e14'], ['open','Open (published)','#198754'],
    ['unreviewed','Awaiting review','#6c757d'], ['dmp_linked','DMP-linked','#0dcaf0'],
  ] as [$k,$label,$color])
    <div class="col">
      <div class="card text-center"><div class="card-body py-2">
        <div class="h4 mb-0" style="color:{{ $color }}">{{ $summary[$k] }}</div>
        <div class="small text-muted">{{ __($label) }}</div>
      </div></div>
    </div>
  @endforeach
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('rdm.datasets.compliance') }}" class="row g-2 align-items-end mb-3">
  <div class="col-md-4">
    <label class="form-label small mb-0">{{ __('Faculty / institution') }}</label>
    <select name="institution" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      @foreach ($institutions as $inst)
        <option value="{{ $inst }}" @selected(($filters['institution'] ?? '') === $inst)>{{ $inst }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('POPIA verdict') }}</label>
    <select name="verdict" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      @foreach (['CLEAR','PERSONAL','SPECIAL_CATEGORY'] as $v)
        <option value="{{ $v }}" @selected(($filters['verdict'] ?? '') === $v)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label small mb-0">{{ __('Disposition') }}</label>
    <select name="disposition" class="form-select form-select-sm">
      <option value="">{{ __('All') }}</option>
      @foreach (['restrict','embargo','de-identify','release'] as $v)
        <option value="{{ $v }}" @selected(($filters['disposition'] ?? '') === $v)>{{ $v }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">{{ __('Filter') }}</button></div>
</form>

<div class="card">
  <div class="card-body p-0">
    <table class="table table-sm mb-0 align-middle">
      <thead><tr>
        <th>{{ __('Dataset') }}</th><th>{{ __('Faculty') }}</th><th>{{ __('POPIA') }}</th>
        <th>{{ __('Findings') }}</th><th>{{ __('Access') }}</th><th>{{ __('DOI') }}</th><th>{{ __('DMP/Project') }}</th>
      </tr></thead>
      <tbody>
        @php
          $verdictColor = ['CLEAR'=>'success','PERSONAL'=>'warning','SPECIAL_CATEGORY'=>'danger'];
          $accessColor = ['release'=>'success','embargo'=>'warning','restrict'=>'danger','de-identify'=>'info'];
        @endphp
        @forelse ($rows as $r)
          <tr>
            <td><a href="{{ route('rdm.datasets.show', $r->id) }}">{{ $r->title }}</a></td>
            <td class="small text-muted">{{ $r->institution ?? '—' }}</td>
            <td>
              @if ($r->verdict)
                <span class="badge bg-{{ $verdictColor[$r->verdict] ?? 'secondary' }}">{{ $r->verdict }}</span>
              @else <span class="text-muted small">{{ __('not scanned') }}</span>@endif
            </td>
            <td class="small">
              {{ (int) $r->findings }}
              @if ((int) $r->pending > 0) <span class="text-warning">({{ (int) $r->pending }} {{ __('pending') }})</span>
              @elseif ((int) $r->confirmed > 0) <span class="text-danger">({{ (int) $r->confirmed }} {{ __('confirmed') }})</span>@endif
            </td>
            <td>
              @if ($r->disposition)
                <span class="badge bg-{{ $accessColor[$r->disposition] ?? 'secondary' }}">{{ $r->disposition }}</span>
              @else <span class="badge bg-light text-dark">{{ $r->status }}</span>@endif
            </td>
            <td class="small">@if ($r->doi)<a href="https://doi.org/{{ $r->doi }}" target="_blank"><code>{{ $r->doi }}</code></a>@else <span class="text-muted">—</span>@endif</td>
            <td class="small">
              @if (! empty($r->dmp_id))
                <span class="badge bg-info text-dark" title="{{ $r->dmp_title }}"><i class="fas fa-clipboard-list me-1"></i>{{ __('DMP') }}: {{ $r->dmp_status }}</span>
                @if ($r->project_title)<div class="text-muted">{{ \Illuminate\Support\Str::limit($r->project_title, 24) }}</div>@endif
              @elseif ($r->project_title)
                {{ \Illuminate\Support\Str::limit($r->project_title, 28) }}
                <div><span class="badge bg-light text-dark border">{{ __('no DMP') }}</span></div>
              @else
                <span class="text-muted">{{ __('unlinked') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No datasets match.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
<p class="small text-muted mt-2">{{ __('DMP linkage (Feature 1): a dataset can be governed by a machine-actionable Data Management Plan authored in the research portal; the badge shows the plan status.') }}</p>
@endsection
