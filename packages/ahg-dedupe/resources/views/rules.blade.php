@extends('theme::layouts.1col')

@section('title', 'Duplicate Detection Rules')
@section('body-class', 'admin dedupe rules')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Detection Rules</h1>
      <span class="small text-muted">Duplicate Detection</span>
    </div>
    <div class="ms-auto">
      <a href="{{ route('dedupe.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i> Dashboard
      </a>
    </div>
  </div>

  @if($rules->isEmpty())
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-2"></i>
      No detection rules have been configured yet.
    </div>
  @else
    <div class="table-responsive">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th style="width: 100px;">Threshold</th>
            <th style="width: 100px;">Enabled</th>
            <th style="width: 100px;">Blocking</th>
            <th style="width: 80px;">Priority</th>
          </tr>
        </thead>
        <tbody>
          @foreach($rules as $rule)
            <tr>
              <td>{{ $rule->name }}</td>
              <td><span class="badge bg-light text-dark">{{ $rule->rule_type }}</span></td>
              <td class="text-center">{{ number_format($rule->threshold, 0) }}%</td>
              <td class="text-center">
                @if($rule->is_enabled)
                  <span class="badge bg-success">Enabled</span>
                @else
                  <span class="badge bg-secondary">Disabled</span>
                @endif
              </td>
              <td class="text-center">
                @if($rule->is_blocking)
                  <span class="badge bg-danger">Blocking</span>
                @else
                  <span class="badge bg-light text-dark">No</span>
                @endif
              </td>
              <td class="text-center">{{ $rule->priority }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
