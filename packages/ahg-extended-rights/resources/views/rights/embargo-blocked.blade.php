@extends('ahg-theme-b5::layout')

@section('title', 'Access Restricted')

@section('content')
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card border-warning">
        <div class="card-header bg-warning text-dark text-center">
          <h3 class="mb-0"><i class="fas fa-ban"></i> Access Restricted — Embargo</h3>
        </div>
        <div class="card-body">
          <p class="lead text-center">This record is currently under embargo and cannot be accessed.</p>

          @if(!empty($embargo))
            <table class="table table-borderless">
              @if(!empty($embargo->embargo_type))
              <tr>
                <th width="35%">Embargo Type</th>
                <td>
                  <span class="badge bg-{{ $embargo->embargo_type === 'full' ? 'danger' : 'warning' }}">
                    {{ ucfirst(str_replace('_', ' ', $embargo->embargo_type)) }}
                  </span>
                </td>
              </tr>
              @endif
              @if(!empty($embargo->public_message))
              <tr><th>Notice</th><td>{{ e($embargo->public_message) }}</td></tr>
              @endif
              @if(!empty($embargo->end_date))
              <tr><th>Available From</th><td>{{ $embargo->end_date }}</td></tr>
              @endif
              @if(!empty($objectTitle))
              <tr><th>Record</th><td>{{ e($objectTitle) }}</td></tr>
              @endif
            </table>
          @endif

          <hr>

          <div class="text-center">
            @auth
              @if(!empty($canRequestAccess))
              <form method="POST" action="{{ route('ext-rights.request-access') }}" class="d-inline">
                @csrf
                <input type="hidden" name="object_id" value="{{ $objectId ?? '' }}">
                <button class="btn btn-primary"><i class="fas fa-key"></i> Request Access</button>
              </form>
              @endif
            @endauth
            <a href="{{ url()->previous() }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Go Back</a>
            <a href="/" class="btn btn-outline-secondary"><i class="fas fa-home"></i> Home</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
