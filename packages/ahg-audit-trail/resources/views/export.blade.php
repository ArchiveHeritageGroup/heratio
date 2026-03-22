@extends('theme::layouts.1col')
@section('title', 'Export')
@section('body-class', 'browse audit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Export</h1></div>
  </div>
  @if(isset($rows) && count($rows))
    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
      <thead><tr><th>#</th><th>Details</th><th>User</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>@foreach($rows as $i => $row)<tr><td>{{ $i + 1 }}</td><td>{{ $row->action ?? $row->name ?? '-' }}</td><td>{{ $row->username ?? '-' }}</td><td>{{ $row->created_at ?? '-' }}</td><td><a href="#" class="btn btn-sm atom-btn-white"><i class="fas fa-eye"></i></a></td></tr>@endforeach</tbody>
    </table></div>
  @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>
  @endif
  <div class="mt-3"><a href="{{ route('audit.browse') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i> Back to Audit Trail</a></div>
@endsection
