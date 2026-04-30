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
          <label class="form-label">Sort <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
          <select name="sort" class="form-select form-select-sm">
            <option value="nameUp"' . (($params['sort'] ?? 'nameUp') === 'nameUp' ? ' selected' : '') . '>{{ __('Name A-Z') }}</option>
            <option value="nameDown"' . (($params['sort'] ?? '') === 'nameDown' ? ' selected' : '') . '>{{ __('Name Z-A') }}</option>
            <option value="updatedUp"' . (($params['sort'] ?? '') === 'updatedUp' ? ' selected' : '') . '>{{ __('Updated oldest') }}</option>
            <option value="updatedDown"' . (($params['sort'] ?? '') === 'updatedDown' ? ' selected' : '') . '>{{ __('Updated newest') }}</option>
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
        <thead>
          <tr>
            <th>#</th><th>{{ __('Name') }}</th><th>{{ __('Usage') }}</th><th>{{ __('Terms') }}</th><th>{{ __('Created') }}</th><th>{{ __('Updated') }}</th>
          </tr>
        </thead>
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
