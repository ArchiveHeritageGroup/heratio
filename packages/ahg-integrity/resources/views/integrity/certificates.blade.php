@extends('theme::layouts.1col')
@section('title', 'Integrity - Destruction Certificates')
@section('body-class', 'admin integrity certificates')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Destruction Certificates</h1><span class="small text-muted">Digital object integrity management</span></div>
  </div>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">Destruction Certificates</h5></div>
  <div class="card-body p-0">
    @if(count($certificates) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
        <th>Certificate #</th><th>IO</th><th>Title</th><th>Method</th><th>Authorized By</th><th>Date</th><th>Actions</th>
      </tr></thead>
      <tbody>
        @foreach($certificates as $c)
        @php $cert = is_array($c) ? (object) $c : $c; @endphp
        <tr>
          <td><code>{{ $cert->certificate_number }}</code></td>
          <td>#{{ $cert->information_object_id }}</td>
          <td>{{ $cert->io_title ?? '-' }}</td>
          <td>{{ $cert->destruction_method }}</td>
          <td>{{ $cert->authorized_by }}</td>
          <td>{{ $cert->destruction_date }}</td>
          <td>
            <a href="{{ route('integrity.certificates.view', ['id' => $cert->id]) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="fas fa-eye"></i></a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <div class="text-center py-4 text-muted">No destruction certificates found.</div>
    @endif
  </div>
</div>

@if($total > $perPage)
<nav class="mt-3">
  <ul class="pagination justify-content-center">
    @for($p = 1; $p <= ceil($total / $perPage); $p++)
    <li class="page-item {{ $p == $page ? 'active' : '' }}"><a class="page-link" href="{{ route('integrity.certificates', ['page' => $p]) }}">{{ $p }}</a></li>
    @endfor
  </ul>
</nav>
@endif

<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a></div>
@endsection
