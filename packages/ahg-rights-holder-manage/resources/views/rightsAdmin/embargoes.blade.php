@extends('theme::layouts.2col')

@section('title', 'Rights Admin - Embargoes')
@section('body-class', 'admin rights-admin embargoes')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">Embargoes</h1>
@endsection

@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Embargo Records</h5>
  </div>
  <div class="card-body p-0">
    @if(isset($embargoes) && count($embargoes) > 0)
      <table class="table table-striped table-hover mb-0">
        <thead>
          <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
            <th>Object</th><th>Type</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          @foreach($embargoes as $e)
            <tr>
              <td>{{ $e->title ?? '#' . ($e->object_id ?? '') }}</td>
              <td><span class="badge bg-{{ ($e->embargo_type ?? 'full') === 'full' ? 'danger' : 'warning' }}">{{ ucfirst(str_replace('_', ' ', $e->embargo_type ?? 'full')) }}</span></td>
              <td>{{ $e->start_date ?? '-' }}</td>
              <td>{{ $e->end_date ?? 'Indefinite' }}</td>
              <td><span class="badge bg-{{ ($e->is_active ?? true) ? 'success' : 'secondary' }}">{{ ($e->is_active ?? true) ? 'Active' : 'Inactive' }}</span></td>
              <td><a href="{{ route('rights-admin.embargo-edit', $e->id) }}" class="btn btn-sm atom-btn-white">Edit</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @else
      <div class="text-center py-4 text-muted">No embargo records found.</div>
    @endif
  </div>
</div>
@endsection
