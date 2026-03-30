@extends('theme::layouts.1col')
@section('title', 'Report Builder')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1><i class="fas fa-wrench me-2"></i>Report Builder</h1>
      <div>
        <a href="{{ route('reports.builder.create') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-plus me-1"></i>Create New Report</a>
        <a href="{{ route('reports.builder.templates') }}" class="btn atom-btn-white btn-sm ms-1"><i class="fas fa-copy me-1"></i>From Template</a>
      </div>
    </div>

{{-- Stats --}}
    <div class="row mb-4">
      <div class="col-md-3"><div class="card bg-primary text-white"><div class="card-body"><h6 class="mb-0 text-white-50">Total Reports</h6><h2 class="mb-0">{{ $statistics['total_reports'] ?? 0 }}</h2></div></div></div>
      @foreach(array_slice($statistics['by_source'] ?? [], 0, 3) as $source => $count)
      <div class="col-md-3"><div class="card bg-{{ ['success','info','warning'][$loop->index % 3] }} text-white"><div class="card-body"><h6 class="mb-0 text-white-50">{{ ucfirst($source) }}</h6><h2 class="mb-0">{{ $count }}</h2></div></div></div>
      @endforeach
    </div>

    {{-- Search --}}
    <div class="mb-3">
      <input type="text" class="form-control" id="searchReports" placeholder="Search reports...">
    </div>

    {{-- Reports Table --}}
    @forelse($groupedReports ?? [] as $category => $categoryReports)
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <span><i class="fas fa-folder me-2"></i><strong>{{ $category }}</strong></span>
        <span class="badge bg-secondary">{{ count($categoryReports) }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr>
            <th width="40"></th><th>Name</th><th>Source</th><th>Status</th><th>Visibility</th><th>Updated</th><th width="200">Actions</th>
          </tr></thead>
          <tbody>
            @foreach($categoryReports as $report)
            <tr class="report-row">
              <td class="text-center"><i class="fas fa-file-alt text-muted"></i></td>
              <td>
                <a href="{{ route('reports.builder.preview', $report->id) }}" class="fw-bold text-decoration-none">{{ $report->name }}</a>
                @if($report->description)<br><small class="text-muted">{{ Str::limit($report->description, 80) }}</small>@endif
              </td>
              <td><span class="badge bg-light text-dark">{{ ucfirst($report->data_source ?? '') }}</span></td>
              <td>
                @php $statusClass = ['draft'=>'bg-secondary','in_review'=>'bg-warning text-dark','approved'=>'bg-info','published'=>'bg-success','archived'=>'bg-dark'][$report->status ?? 'draft'] ?? 'bg-secondary'; @endphp
                <span class="badge {{ $statusClass }}">{{ ucfirst(str_replace('_',' ',$report->status ?? 'draft')) }}</span>
              </td>
              <td>
                @if($report->is_public ?? false) <span class="badge bg-success"><i class="fas fa-globe me-1"></i>Public</span>
                @elseif($report->is_shared ?? false) <span class="badge bg-info"><i class="fas fa-users me-1"></i>Shared</span>
                @else <span class="badge bg-secondary"><i class="fas fa-lock me-1"></i>Private</span> @endif
              </td>
              <td><small class="text-muted">{{ isset($report->updated_at) ? \Carbon\Carbon::parse($report->updated_at)->format('Y-m-d H:i') : '-' }}</small></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <a href="{{ route('reports.builder.preview', $report->id) }}" class="btn btn-outline-primary" title="View"><i class="fas fa-eye me-1"></i>View</a>
                  <a href="{{ route('reports.builder.edit', $report->id) }}" class="btn btn-outline-secondary" title="Edit"><i class="fas fa-pencil-alt me-1"></i>Edit</a>
                </div>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    @empty
    <div class="card"><div class="card-body text-center text-muted py-5">
      <i class="fas fa-inbox fa-3x mb-3 d-block"></i>No custom reports yet.
      <br><a href="{{ route('reports.builder.create') }}" class="btn atom-btn-white mt-3"><i class="fas fa-plus me-1"></i>Create Your First Report</a>
    </div></div>
    @endforelse
  </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchReports')?.addEventListener('input', function() {
        var term = this.value.toLowerCase();
        document.querySelectorAll('.report-row').forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
});
</script>
@endpush
@endsection