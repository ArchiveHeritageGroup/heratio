@extends('theme::layouts.1col')

@section('title', 'Publish Readiness Check')
@section('body-class', 'admin workflow')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-clipboard-check"></i> Publish Readiness</h1>
    <a href="{{ route('workflow.dashboard') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left"></i> {{ __('Dashboard') }}</a>
  </div>

  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">Object: {{ $evaluation['object']->title ?? 'Untitled' }} (#{{ $objectId }})</h5>
    </div>
    <div class="card-body">
      {{-- Summary --}}
      <div class="row text-center mb-4">
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h3 class="text-success mb-0">{{ $evaluation['summary']['pass'] }}</h3>
            <small class="text-muted">{{ __('Passed') }}</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h3 class="text-danger mb-0">{{ $evaluation['summary']['fail'] }}</h3>
            <small class="text-muted">{{ __('Failed') }}</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="border rounded p-3">
            <h3 class="text-warning mb-0">{{ $evaluation['summary']['warning'] }}</h3>
            <small class="text-muted">{{ __('Warnings') }}</small>
          </div>
        </div>
      </div>

      {{-- Overall verdict --}}
      @if($evaluation['summary']['fail'] === 0)
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i> <strong>{{ __('Ready to publish.') }}</strong> All gate rules passed.
          @if($evaluation['summary']['warning'] > 0)
            There {{ $evaluation['summary']['warning'] === 1 ? 'is' : 'are' }} {{ $evaluation['summary']['warning'] }} warning(s) to review.
          @endif
        </div>
      @else
        <div class="alert alert-danger">
          <i class="fas fa-times-circle"></i> <strong>{{ __('Not ready to publish.') }}</strong>
          {{ $evaluation['summary']['fail'] }} blocker(s) must be resolved before publishing.
        </div>
      @endif

      {{-- Results Table --}}
      @if(count($evaluation['results']) === 0)
        <p class="text-muted">No gate rules are configured.</p>
      @else
        <div class="table-responsive">
          <table class="table table-bordered table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Rule') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Severity') }}</th>
                <th>{{ __('Details') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($evaluation['results'] as $result)
                <tr>
                  <td>
                    @if($result->status === 'pass')
                      <span class="badge bg-success"><i class="fas fa-check"></i> {{ __('Pass') }}</span>
                    @elseif($result->status === 'fail')
                      <span class="badge bg-danger"><i class="fas fa-times"></i> {{ __('Fail') }}</span>
                    @else
                      <span class="badge bg-warning text-dark"><i class="fas fa-exclamation"></i> {{ __('Warning') }}</span>
                    @endif
                  </td>
                  <td>{{ $result->rule_name }}</td>
                  <td><span class="badge bg-secondary">{{ str_replace('_', ' ', ucfirst($result->rule_type)) }}</span></td>
                  <td>
                    @if($result->severity === 'blocker')
                      <span class="badge bg-danger">{{ __('Blocker') }}</span>
                    @else
                      <span class="badge bg-warning text-dark">{{ __('Warning') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($result->details)
                      <small class="text-muted">{{ $result->details }}</small>
                    @else
                      -
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
