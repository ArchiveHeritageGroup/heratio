@extends('theme::layouts.1col')
@section('title', __('Accounting Standards'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-balance-scale me-2"></i>{{ __('Accounting Standards') }}</h1>
      <a href="{{ route('heritage.hadmin.standard-add') }}" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i>{{ __('Add Standard') }}</a>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:60px">{{ __('Order') }}</th>
              <th style="width:100px">{{ __('Code') }}</th>
              <th>{{ __('Name') }}</th>
              <th>{{ __('Country/Region') }}</th>
              <th style="width:90px">{{ __('Capital.') }}</th>
              <th style="width:90px">{{ __('Status') }}</th>
              <th style="width:170px"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($standards as $s)
              <tr class="{{ $s->is_active ? '' : 'table-secondary' }}">
                <td>{{ $s->sort_order }}</td>
                <td><code>{{ $s->code }}</code></td>
                <td>
                  <strong>{{ $s->name }}</strong>
                  @if($s->description)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($s->description, 80) }}</small>@endif
                </td>
                <td>{{ $s->country }}</td>
                <td class="text-center">
                  @if($s->capitalisation_required)<span class="badge bg-warning text-dark">{{ __('Required') }}</span>@else<span class="badge bg-secondary">{{ __('Optional') }}</span>@endif
                </td>
                <td class="text-center">
                  @if($s->is_active)<span class="badge bg-success">{{ __('Active') }}</span>@else<span class="badge bg-danger">{{ __('Disabled') }}</span>@endif
                </td>
                <td class="text-end text-nowrap">
                  <a href="{{ route('heritage.hadmin.rule-list', ['standard_id' => $s->id]) }}" class="btn btn-sm btn-outline-info" title="{{ __('Rules') }}" aria-label="{{ __('Rules') }}"><i class="fas fa-clipboard-check"></i></a>
                  <a href="{{ route('heritage.hadmin.standard-edit', $s->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('Edit') }}" aria-label="{{ __('Edit') }}"><i class="fas fa-edit"></i></a>
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.standard-toggle', $s->id),
                    'class' => 'btn btn-sm btn-outline-' . ($s->is_active ? 'warning' : 'success'),
                    'icon' => 'fas fa-' . ($s->is_active ? 'toggle-on' : 'toggle-off'),
                    'title' => $s->is_active ? __('Disable') : __('Enable'),
                  ])
                  @include('ahg-heritage-manage::heritage-admin._post-action', [
                    'url' => route('heritage.hadmin.standard-delete', $s->id), 'method' => 'DELETE',
                    'class' => 'btn btn-sm btn-outline-danger', 'icon' => 'fas fa-trash', 'title' => __('Delete'),
                    'confirm' => __('Delete this standard?'),
                  ])
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No accounting standards installed.') }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>{{ __('About Accounting Standards') }}</div>
      <div class="card-body">
        <p>{{ __('Heritage accounting standards define how cultural and heritage assets should be recognized, measured, and disclosed in financial statements.') }}</p>
        <ul class="mb-0">
          <li><strong>IPSAS 45</strong> - {{ __('International public sector (property, plant and equipment, including heritage)') }}</li>
          <li><strong>IAS 16</strong> - {{ __('International private sector') }}</li>
          <li><strong>FRS 102</strong> - {{ __('United Kingdom and Ireland heritage assets section') }}</li>
          <li><strong>GASB 34</strong> - {{ __('United States government entities') }}</li>
          <li><strong>FASB 958</strong> - {{ __('United States non-profit organizations') }}</li>
          <li><strong>PSAS 3150</strong> - {{ __('Canada public sector') }}</li>
          <li><strong>AASB 116</strong> - {{ __('Australia and New Zealand') }}</li>
          <li><strong>GRAP 103</strong> - {{ __('South Africa public sector (a heritage-specific standard)') }}</li>
        </ul>
      </div>
    </div>

    <div class="mt-3">
      <a href="{{ route('heritage.accounting.settings') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}</a>
    </div>
  </div>
</div>
@endsection
