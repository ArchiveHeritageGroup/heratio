@extends('theme::layouts.1col')
@section('title', 'Authority Record Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', [
      'action' => route('reports.authorities'),
      'extraFilters' => '
        <div class="mb-3">
          <label class="form-label">Entity type <span class="badge bg-secondary ms-1">Optional</span></label>
          <select name="entityType" class="form-select form-select-sm">
            <option value="">All types</option>' .
            $entityTypes->map(fn($t) => '<option value="' . $t->id . '"' . (($params['entityType'] ?? '') == $t->id ? ' selected' : '') . '>' . e($t->name) . '</option>')->implode('') .
          '</select>
        </div>',
    ])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-user me-2"></i>Authority Record Report</h1>
      <div>
        <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success ms-2"><i class="fas fa-file-csv me-1"></i>CSV</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr>
            <th>#</th><th>Name</th><th>Entity Type</th><th>Dates</th><th>Created</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td>{{ $row->authorized_form_of_name ?? '' }}</td>
              <td>{{ $row->entity_type_name ?? '' }}</td>
              <td>{{ $row->dates_of_existence ?? '' }}</td>
              <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d') : '' }}</td>
              <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('Y-m-d') : '' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-muted text-center">No results</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('ahg-reports::_pagination')
  </div>
</div>
@endsection
