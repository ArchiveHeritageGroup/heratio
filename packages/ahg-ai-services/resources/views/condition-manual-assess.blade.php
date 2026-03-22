@extends('theme::layouts.1col')

@section('title', 'Condition Manual Assess')
@section('body-class', 'browse ai')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-robot me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Condition Manual Assess</h1></div>
  </div>

  @if(isset($rows) && count($rows))
    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
      <thead><tr><th>#</th><th>Details</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>@foreach($rows as $i => $row)<tr><td>{{ $i + 1 }}</td><td>{{ $row->name ?? $row->title ?? '-' }}</td><td><span class="badge bg-secondary">{{ $row->status ?? '-' }}</span></td><td>{{ $row->created_at ?? '-' }}</td><td><a href="#" class="btn btn-sm atom-btn-white"><i class="fas fa-eye"></i></a></td></tr>@endforeach</tbody>
    </table></div>
    @if(isset($pager))@include('ahg-core::components.pager', ['pager' => $pager])@endif
  @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>
  @endif
@endsection
