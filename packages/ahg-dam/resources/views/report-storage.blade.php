@extends('theme::layouts.1col')
@section('title', 'Storage Report')
@section('body-class', 'browse')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-hdd me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Storage Report</h1></div>
  </div>
  @if(isset($rows) && count($rows))
    <div class="table-responsive"><table class="table table-bordered table-hover mb-0">
      <thead><tr><th>Repository</th><th>Objects</th><th>Files</th><th>Size (GB)</th></tr></thead>
      <tbody>@foreach($rows as $row)<tr>@foreach((array)$row as $v)<td>{{ $v }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
    @if(isset($pager))@include('ahg-core::components.pager', ['pager' => $pager])@endif
  @else
    <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No records found.</div>
  @endif
@endsection
