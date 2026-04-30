@extends('theme::layouts.1col')
@section('title', 'Recent Updates Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', [
      'action' => route('reports.recent'),
      'extraFilters' => '
        <div class="mb-3">
          <label class="form-label">Entity type <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="className" class="form-select form-select-sm">
            <option value="">{{ __('All types') }}</option>
            <option value="QubitInformationObject"' . (($params['className'] ?? '') === 'QubitInformationObject' ? ' selected' : '') . '>{{ __('Descriptions') }}</option>
            <option value="QubitActor"' . (($params['className'] ?? '') === 'QubitActor' ? ' selected' : '') . '>{{ __('Authority records') }}</option>
            <option value="QubitRepository"' . (($params['className'] ?? '') === 'QubitRepository' ? ' selected' : '') . '>{{ __('Repositories') }}</option>
            <option value="QubitAccession"' . (($params['className'] ?? '') === 'QubitAccession' ? ' selected' : '') . '>{{ __('Accessions') }}</option>
            <option value="QubitPhysicalObject"' . (($params['className'] ?? '') === 'QubitPhysicalObject' ? ' selected' : '') . '>{{ __('Physical storage') }}</option>
            <option value="QubitDonor"' . (($params['className'] ?? '') === 'QubitDonor' ? ' selected' : '') . '>{{ __('Donors') }}</option>
          </select>
        </div>',
    ])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-clock me-2"></i>{{ __('Recent Updates') }}</h1>
      <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr>
            <th>#</th><th>{{ __('Type') }}</th><th>{{ __('Created') }}</th><th>{{ __('Updated') }}</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td>{{ str_replace('Qubit', '', $row->class_name) }}</td>
              <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d H:i') : '' }}</td>
              <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('Y-m-d H:i') : '' }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-muted text-center">No results</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('ahg-reports::_pagination')
  </div>
</div>
@endsection
