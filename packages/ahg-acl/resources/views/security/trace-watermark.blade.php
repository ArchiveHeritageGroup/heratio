{{-- Watermark Trace - Migrated from AtoM: ahgSecurityClearancePlugin/modules/securityClearance/templates/traceWatermarkSuccess.php --}}
@extends('theme::layouts.1col')

@section('title', 'Trace Watermark')

@section('content')

<h1><i class="fas fa-search"></i> Trace Watermark</h1>

<div class="card mb-4">
  <div class="card-body">
    <form method="get" action="{{ route('acl.trace-watermark') }}">
      <div class="row">
        <div class="col-md-8">
          <input type="text" name="code" class="form-control"
                 placeholder="Enter watermark code (12 characters)"
                 value="{{ e($searchCode ?? '') }}" pattern="[A-Z0-9]{12}">
        </div>
        <div class="col-md-4">
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search"></i> Trace
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

@if(isset($watermark))
  @if($watermark)
  <div class="card">
    <div class="card-header bg-success text-white">
      <h5 class="mb-0"><i class="fas fa-check-circle"></i> Watermark Found</h5>
    </div>
    <div class="card-body">
      <table class="table table-borderless">
        <tr>
          <th width="25%">Watermark Code</th>
          <td><code>{{ e($watermark->watermark_code) }}</code></td>
        </tr>
        <tr>
          <th>Downloaded By</th>
          <td>
            <a href="{{ route('acl.user-security', ['id' => $watermark->user_id]) }}">
              {{ e($watermark->username) }}
            </a>
            ({{ e($watermark->email) }})
          </td>
        </tr>
        <tr>
          <th>Object</th>
          <td>
            @if($watermark->object_id)
            <a href="{{ route('acl.object-view', ['id' => $watermark->object_id]) }}">
              {{ e($watermark->object_title ?? 'ID: ' . $watermark->object_id) }}
            </a>
            @else
            -
            @endif
          </td>
        </tr>
        <tr>
          <th>Download Date</th>
          <td>{{ date('Y-m-d H:i:s', strtotime($watermark->created_at)) }}</td>
        </tr>
        <tr>
          <th>IP Address</th>
          <td>{{ e($watermark->ip_address ?? '-') }}</td>
        </tr>
        <tr>
          <th>Watermark Text</th>
          <td><code>{{ e($watermark->watermark_text) }}</code></td>
        </tr>
        @if($watermark->file_hash ?? null)
        <tr>
          <th>File Hash</th>
          <td><code>{{ e($watermark->file_hash) }}</code></td>
        </tr>
        @endif
      </table>
    </div>
  </div>
  @else
  <div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    No watermark found with code: <code>{{ e($searchCode) }}</code>
  </div>
  @endif
@endif

@endsection
