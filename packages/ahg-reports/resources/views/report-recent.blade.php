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
          <label class="form-label">Entity type</label>
          <select name="className" class="form-select form-select-sm">
            <option value="">All types</option>
            <option value="QubitInformationObject"' . (($params['className'] ?? '') === 'QubitInformationObject' ? ' selected' : '') . '>Descriptions</option>
            <option value="QubitActor"' . (($params['className'] ?? '') === 'QubitActor' ? ' selected' : '') . '>Authority records</option>
            <option value="QubitRepository"' . (($params['className'] ?? '') === 'QubitRepository' ? ' selected' : '') . '>Repositories</option>
            <option value="QubitAccession"' . (($params['className'] ?? '') === 'QubitAccession' ? ' selected' : '') . '>Accessions</option>
            <option value="QubitPhysicalObject"' . (($params['className'] ?? '') === 'QubitPhysicalObject' ? ' selected' : '') . '>Physical storage</option>
            <option value="QubitDonor"' . (($params['className'] ?? '') === 'QubitDonor' ? ' selected' : '') . '>Donors</option>
          </select>
        </div>',
    ])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-clock me-2"></i>Recent Updates</h1>
      <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>#</th><th>Type</th><th>Created</th><th>Updated</th>
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
