@extends('theme::layouts.2col')

@section('title', 'Orphan Works')
@section('body-class', 'admin rights-admin orphan-works')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">Orphan Works</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Orphan Work Designations</h5>
  </div>
  <div class="card-body p-0">
    @if(isset($orphanWorks) && count($orphanWorks) > 0)
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>Object</th><th>Designation Date</th><th>Search Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($orphanWorks as $ow)
            <tr>
              <td>{{ $ow->title ?? '#' . ($ow->object_id ?? '') }}</td>
              <td>{{ $ow->designation_date ?? '-' }}</td>
              <td><span class="badge bg-{{ ($ow->search_status ?? '') === 'diligent' ? 'success' : 'warning' }}">{{ ucfirst($ow->search_status ?? 'pending') }}</span></td>
              <td><a href="{{ route('rights-admin.orphan-work-edit', $ow->id) }}" class="btn btn-sm atom-btn-white">Edit</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-center py-4 text-muted">No orphan work designations found.</div>
    @endif
  </div>
</div>
@endsection
