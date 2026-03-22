@extends('theme::layouts.1col')

@section('title', 'Access Restricted')
@section('body-class', 'embargo blocked')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-8">
    <div class="card border-warning">
      <div class="card-header bg-warning text-dark">
        <h4 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>{{ $embargoInfo['type_label'] ?? 'Access Restricted' }}</h4>
      </div>
      <div class="card-body text-center py-5">
        <i class="fas fa-lock fa-5x text-warning mb-4"></i>
        <h3 class="mb-3">This record is under embargo</h3>

        @if(!empty($embargoInfo['public_message']))
          <p class="lead">{{ $embargoInfo['public_message'] }}</p>
        @else
          <p class="lead">Access to this material is currently restricted and not available for public viewing.</p>
        @endif

        @if(!($embargoInfo['is_perpetual'] ?? false) && !empty($embargoInfo['end_date']))
          <div class="alert alert-info d-inline-block mt-3">
            <i class="fas fa-calendar-alt me-2"></i>
            This record will become available on {{ date('j F Y', strtotime($embargoInfo['end_date'])) }}
          </div>
        @elseif($embargoInfo['is_perpetual'] ?? false)
          <div class="alert alert-secondary d-inline-block mt-3">
            <i class="fas fa-ban me-2"></i>This restriction is indefinite
          </div>
        @endif

        <div class="mt-4">
          <a href="{{ route('homepage') }}" class="btn atom-btn-white"><i class="fas fa-home me-2"></i>Return to homepage</a>
          <a href="javascript:history.back()" class="btn atom-btn-white ms-2"><i class="fas fa-arrow-left me-2"></i>Go back</a>
        </div>

        @auth
          <hr class="my-4">
          <p class="text-muted small"><i class="fas fa-info-circle me-1"></i>If you believe you should have access to this record, please contact the repository administrator.</p>
        @endauth
      </div>
    </div>
  </div>
</div>
@endsection
