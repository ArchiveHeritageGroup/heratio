@extends('theme::layouts.1col')
@section('title', 'Integrity - Retention Events')
@section('body-class', 'admin integrity retention-events')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-clock me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Retention Events') }}</h1><span class="small text-muted">{{ __('Event-based retention triggers') }}</span></div>
  </div>
@endsection
@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="row mb-4">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Fire Retention Event') }}</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('integrity.retention-events.store') }}">
          @csrf
          <div class="mb-3">
            <label for="information_object_id" class="form-label">{{ __('Information Object ID') }}</label>
            <input type="number" class="form-control" id="information_object_id" name="information_object_id" required min="1" placeholder="{{ __('Enter IO ID') }}">
          </div>
          <div class="mb-3">
            <label for="event_type" class="form-label">{{ __('Event Type') }}</label>
            <select class="form-select" id="event_type" name="event_type" required>
              <option value="">-- Select or type --</option>
              @foreach($eventTypes as $type)
              <option value="{{ $type }}">{{ $type }}</option>
              @endforeach
              <option value="case_closed">case_closed</option>
              <option value="retention_review">retention_review</option>
              <option value="disposal_approved">disposal_approved</option>
              <option value="transfer_complete">transfer_complete</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="2000"></textarea>
          </div>
          <button type="submit" class="btn atom-btn-white"><i class="fas fa-bolt me-1"></i>{{ __('Fire Event') }}</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Retention Policies') }}</h5></div>
      <div class="card-body p-0">
        @if(count($policies) > 0)
        <table class="table table-sm table-striped mb-0">
          <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Period') }}</th><th>{{ __('Trigger') }}</th><th>{{ __('Enabled') }}</th></tr></thead>
          <tbody>
            @foreach($policies as $p)
            <tr>
              <td>{{ $p->name }}</td>
              <td>{{ $p->retention_period_days }} days</td>
              <td><span class="badge bg-info">{{ $p->trigger_type }}</span></td>
              <td>{!! $p->is_enabled ? '<span class="badge bg-success">' . e(__('Yes')) . '</span>' : '<span class="badge bg-secondary">' . e(__('No')) . '</span>' !!}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <div class="text-center py-3 text-muted">No retention policies configured.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Retention Trigger Events <span class="badge bg-light text-dark ms-2">{{ $total }}</span></h5>
  </div>
  <div class="card-body p-0">
    @if(count($events) > 0)
    <table class="table table-striped table-hover mb-0">
      <thead>
        <tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
          <th>{{ __('ID') }}</th><th>{{ __('IO ID') }}</th><th>{{ __('IO Title') }}</th><th>{{ __('Event Type') }}</th><th>{{ __('Event Date') }}</th><th>{{ __('Triggered By') }}</th><th>{{ __('Notes') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($events as $evt)
        <tr>
          <td>{{ $evt->id }}</td>
          <td><a href="{{ url('/informationobject/show/' . $evt->information_object_id) }}">#{{ $evt->information_object_id }}</a></td>
          <td>{{ $evt->io_title ?? '-' }}</td>
          <td><span class="badge bg-info">{{ $evt->event_type }}</span></td>
          <td>{{ $evt->event_date }}</td>
          <td>{{ $evt->triggered_by ?? '-' }}</td>
          <td>{{ \Illuminate\Support\Str::limit($evt->notes ?? '', 60) }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    @if($total > $perPage)
    <nav class="d-flex justify-content-center py-3">
      <ul class="pagination mb-0">
        @for($i = 1; $i <= ceil($total / $perPage); $i++)
        <li class="page-item {{ $i == $page ? 'active' : '' }}"><a class="page-link" href="?page={{ $i }}">{{ $i }}</a></li>
        @endfor
      </ul>
    </nav>
    @endif
    @else
    <div class="text-center py-4 text-muted">No retention trigger events recorded.</div>
    @endif
  </div>
</div>

<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a></div>
@endsection
