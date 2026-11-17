@extends('theme::layouts.1col')
@section('title', __('Batch Compliance Check'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-check-double me-2"></i>{{ __('Batch Compliance Check') }}</h1>
      <button type="button" class="btn atom-btn-white d-print-none" data-heritage-print><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
    </div>
    <p class="text-muted">{{ __('Every asset recorded under GRAP 103, checked against the recognition, measurement, disclosure and documentation checklist.') }}</p>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        {{ __('Checked :n GRAP assets', ['n' => count($results)]) }}
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Class') }}</th>
                <th class="text-center">{{ __('Score') }}</th>
                <th class="text-center">{{ __('Passed') }}</th>
                <th class="text-center">{{ __('Warnings') }}</th>
                <th class="text-center">{{ __('Failed') }}</th>
                <th class="text-center">{{ __('Status') }}</th>
                <th class="text-center d-print-none">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($results as $result)
                @php $asset = $result['asset']; $sum = $result['compliance']['summary']; @endphp
                <tr>
                  <td><a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->object_identifier ?: __('Asset #:id', ['id' => $asset->id]) }}</a></td>
                  <td>{{ $asset->object_title ?: '-' }}</td>
                  <td>{{ $asset->class_name ?: '-' }}</td>
                  <td class="text-center"><strong class="{{ $sum['score'] >= 80 ? 'text-success' : ($sum['score'] >= 50 ? 'text-warning' : 'text-danger') }}">{{ $sum['score'] }}%</strong></td>
                  <td class="text-center text-success">{{ $sum['passed'] }}</td>
                  <td class="text-center text-warning">{{ $sum['warnings'] }}</td>
                  <td class="text-center text-danger">{{ $sum['failed'] }}</td>
                  <td class="text-center">
                    @if($sum['status'] === 'compliant')
                      <span class="badge bg-success">{{ __('Compliant') }}</span>
                    @elseif($sum['status'] === 'partially_compliant')
                      <span class="badge bg-warning text-dark">{{ __('Partial') }}</span>
                    @else
                      <span class="badge bg-danger">{{ __('Non-Compliant') }}</span>
                    @endif
                  </td>
                  <td class="text-center d-print-none">
                    <a href="{{ route('heritage.grap.check', $asset->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View Details') }}"><i class="fas fa-eye"></i></a>
                  </td>
                </tr>
              @empty
                <tr><td colspan="9" class="text-center text-muted py-4">{{ __('No GRAP assets to check.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-4 d-print-none">
      <a href="{{ route('heritage.grap.dashboard') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to GRAP Dashboard') }}</a>
    </div>
  </div>
</div>
@include('ahg-heritage-manage::partials._print-script')
@endsection
