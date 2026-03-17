@extends('theme::layouts.1col')
@section('title', 'Taxonomy Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', [
      'action' => route('reports.taxonomy'),
      'extraFilters' => '
        <div class="mb-3">
          <label class="form-label">Sort</label>
          <select name="sort" class="form-select form-select-sm">
            <option value="nameUp"' . (($params['sort'] ?? 'nameUp') === 'nameUp' ? ' selected' : '') . '>Name A-Z</option>
            <option value="nameDown"' . (($params['sort'] ?? '') === 'nameDown' ? ' selected' : '') . '>Name Z-A</option>
            <option value="updatedUp"' . (($params['sort'] ?? '') === 'updatedUp' ? ' selected' : '') . '>Updated oldest</option>
            <option value="updatedDown"' . (($params['sort'] ?? '') === 'updatedDown' ? ' selected' : '') . '>Updated newest</option>
          </select>
        </div>',
    ])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-tags me-2"></i>Taxonomy Report</h1>
      <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead><tr><th>ID</th><th>Name</th><th>Usage</th><th>Terms</th><th>Created</th><th>Updated</th></tr></thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td>{{ $row->name ?? '' }}</td>
              <td><code>{{ $row->usage ?? '' }}</code></td>
              <td><span class="badge bg-info">{{ $row->term_count ?? 0 }}</span></td>
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
