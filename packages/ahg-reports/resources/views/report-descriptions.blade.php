@extends('theme::layouts.1col')
@section('title', 'Description Report')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
    @include('ahg-reports::_filters', [
      'action' => route('reports.descriptions'),
      'extraFilters' => '
        <div class="mb-3">
          <label class="form-label">Level of description</label>
          <select name="level" class="form-select form-select-sm">
            <option value="">All levels</option>' .
            $levels->map(fn($l) => '<option value="' . $l->id . '"' . (($params['level'] ?? '') == $l->id ? ' selected' : '') . '>' . e($l->name) . '</option>')->implode('') .
          '</select>
        </div>
        <div class="mb-3">
          <label class="form-label">Publication status</label>
          <select name="publicationStatus" class="form-select form-select-sm">
            <option value="">All</option>
            <option value="159"' . (($params['publicationStatus'] ?? '') == '159' ? ' selected' : '') . '>Draft</option>
            <option value="160"' . (($params['publicationStatus'] ?? '') == '160' ? ' selected' : '') . '>Published</option>
          </select>
        </div>',
    ])
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-file-alt me-2"></i>Description Report</h1>
      <div>
        <span class="badge bg-primary fs-6">{{ number_format($total) }} results</span>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-sm atom-btn-outline-success ms-2"><i class="fas fa-file-csv me-1"></i>CSV</a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-striped table-sm">
        <thead>
          <tr style="background:var(--ahg-primary);color:#fff">
            <th>#</th><th>Identifier</th><th>Title</th><th>Level</th><th>Status</th><th>Created</th><th>Updated</th>
          </tr>
        </thead>
        <tbody>
          @forelse($results as $row)
            <tr>
              <td>{{ $row->id }}</td>
              <td><code>{{ $row->identifier }}</code></td>
              <td>{{ Str::limit($row->title ?? '', 60) }}</td>
              <td>{{ $row->level_name ?? '' }}</td>
              <td>
                @if(($row->publication_status_id ?? null) == 160)
                  <span class="badge bg-success">Published</span>
                @else
                  <span class="badge bg-warning">Draft</span>
                @endif
              </td>
              <td>{{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('Y-m-d') : '' }}</td>
              <td>{{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('Y-m-d') : '' }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted text-center">No results</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @include('ahg-reports::_pagination')
  </div>
</div>
@endsection
