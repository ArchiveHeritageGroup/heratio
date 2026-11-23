@extends('theme::layouts.1col')
@section('title', __('Opening Hours and Closures'))
@section('body-class', 'admin library')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>{{ __('Opening Hours and Closures') }}</h1>
  <a href="{{ route('library.loan-rules') }}" class="btn btn-sm atom-btn-white">
    <i class="fas fa-gavel me-1"></i>{{ __('Loan Rules') }}
  </a>
</div>

<p class="text-muted">
  {{ __('A loan that would fall due on a closed day moves to the next open day, and closed days are not charged as overdue. Opening times are shown for information; loans are due by date.') }}
</p>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@unless($ready)
  <div class="alert alert-warning">{{ __('The calendar tables are not installed yet. Every day counts as open until they are.') }}</div>
@endunless
@unless($canManage)
  <div class="alert alert-info">{{ __('You are assigned to another branch, so this calendar is read-only for you.') }}</div>
@endunless

@if($branchAware && count($branchLabels))
  <form method="get" action="{{ route('library.calendar') }}" class="row g-2 align-items-end mb-3">
    <div class="col-md-4">
      <label class="form-label" for="cal_branch">{{ __('Branch') }}</label>
      <select name="branch" id="cal_branch" class="form-select">
        <option value="0">{{ __('All branches') }}</option>
        @foreach($branchLabels as $bid => $bname)
          <option value="{{ $bid }}" @selected($branch === (int) $bid)>{{ $bname }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-auto"><button class="btn atom-btn-white">{{ __('Show') }}</button></div>
  </form>
@endif

<div class="card mb-4">
  <div class="card-header"><i class="fas fa-clock me-2"></i>{{ __('Weekly opening hours') }}</div>
  <div class="card-body">
    <form method="post" action="{{ route('library.calendar.hours') }}">
      @csrf
      @method('PUT')
      <input type="hidden" name="branch_id" value="{{ $branch }}">
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>{{ __('Day') }}</th><th>{{ __('Open') }}</th><th>{{ __('Opens') }}</th><th>{{ __('Closes') }}</th>
              @if($branch > 0)<th>{{ __('Source') }}</th>@endif
            </tr>
          </thead>
          <tbody>
            @foreach($week as $weekday => $day)
              <tr>
                <td>{{ \Illuminate\Support\Carbon::create(2024, 1, $weekday)->translatedFormat('l') }}</td>
                <td>
                  <input type="hidden" name="days[{{ $weekday }}][is_open]" value="0">
                  <input type="checkbox" class="form-check-input" name="days[{{ $weekday }}][is_open]" value="1"
                         aria-label="{{ __('Open') }}" @checked((bool) old("days.$weekday.is_open", $day->is_open)) @disabled(! $canManage)>
                </td>
                <td><input type="time" class="form-control form-control-sm" name="days[{{ $weekday }}][opens_at]"
                           aria-label="{{ __('Opens') }}" value="{{ old("days.$weekday.opens_at", $day->opens_at) }}" @disabled(! $canManage)></td>
                <td><input type="time" class="form-control form-control-sm" name="days[{{ $weekday }}][closes_at]"
                           aria-label="{{ __('Closes') }}" value="{{ old("days.$weekday.closes_at", $day->closes_at) }}" @disabled(! $canManage)></td>
                @if($branch > 0)
                  <td class="text-muted small">{{ $day->inherited ? __('All branches') : __('This branch') }}</td>
                @endif
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if($canManage && $ready)
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save hours') }}</button>
      @endif
    </form>
    @if($canManage && $ready && $branch > 0)
      <form method="post" action="{{ route('library.calendar.hours.reset') }}" class="mt-2"
            onsubmit="return confirm('{{ __('Use the all-branches hours for this branch?') }}');">
        @csrf
        <input type="hidden" name="branch_id" value="{{ $branch }}">
        <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Use all-branches hours') }}</button>
      </form>
    @endif
  </div>
</div>

<div class="card mb-4">
  <div class="card-header"><i class="fas fa-ban me-2"></i>{{ __('Closed days and public holidays') }}</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('From') }}</th><th>{{ __('To') }}</th>
            <th>{{ __('Yearly') }}</th><th>{{ __('Applies to') }}</th><th></th>
          </tr>
        </thead>
        <tbody>
          @forelse($closures as $c)
            <tr>
              <td>{{ $c->name }}</td>
              <td>{{ $closureTypes[$c->closure_type] ?? $c->closure_type }}</td>
              <td>{{ substr((string) $c->start_date, 0, 10) }}</td>
              <td>{{ substr((string) $c->end_date, 0, 10) }}</td>
              <td>{{ $c->recurs_annually ? __('Yes') : __('No') }}</td>
              <td>{{ \AhgLibrary\Support\LibraryBranch::ruleLabel((int) $c->branch_id) }}</td>
              <td class="text-end text-nowrap">
                @if(\AhgLibrary\Services\LibraryCalendarService::operatorMayManage((int) $c->branch_id))
                  <a href="{{ route('library.calendar.closures.edit', $c->id) }}" class="btn btn-sm atom-btn-white" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
                  <form method="post" action="{{ route('library.calendar.closures.delete', $c->id) }}" class="d-inline"
                        onsubmit="return confirm('{{ __('Delete this closure?') }}');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="text-muted text-center py-3">{{ __('No closures recorded.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@if($canManage && $ready)
<div class="card">
  <div class="card-header">
    <i class="fas fa-plus me-2"></i>{{ $editing ? __('Edit closure') : __('Add a closure') }}
  </div>
  <div class="card-body">
    <form method="post" class="row g-3"
          action="{{ $editing ? route('library.calendar.closures.update', $editing->id) : route('library.calendar.closures.store') }}">
      @csrf
      @if($editing) @method('PUT') @endif
      <input type="hidden" name="branch_id" value="{{ $branch }}">
      <div class="col-md-4">
        <label class="form-label" for="cl_name">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" id="cl_name" class="form-control" maxlength="150" required
               value="{{ old('name', $editing->name ?? '') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="cl_type">{{ __('Type') }} <span class="text-danger">*</span></label>
        <select name="closure_type" id="cl_type" class="form-select" required>
          @foreach($closureTypes as $code => $label)
            <option value="{{ $code }}" @selected(old('closure_type', $editing->closure_type ?? '') === (string) $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="cl_start">{{ __('From') }} <span class="text-danger">*</span></label>
        <input type="date" name="start_date" id="cl_start" class="form-control" required
               value="{{ old('start_date', isset($editing) ? substr((string) $editing->start_date, 0, 10) : '') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="cl_end">{{ __('To') }}</label>
        <input type="date" name="end_date" id="cl_end" class="form-control"
               value="{{ old('end_date', isset($editing) ? substr((string) $editing->end_date, 0, 10) : '') }}">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <div class="form-check">
          <input type="hidden" name="recurs_annually" value="0">
          <input type="checkbox" name="recurs_annually" id="cl_yearly" class="form-check-input" value="1"
                 @checked((bool) old('recurs_annually', $editing->recurs_annually ?? false))>
          <label class="form-check-label" for="cl_yearly">{{ __('Yearly') }}</label>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label" for="cl_notes">{{ __('Notes') }}</label>
        <textarea name="notes" id="cl_notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $editing->notes ?? '') }}</textarea>
        <div class="form-text">{{ __('Use Yearly for fixed-date holidays only. Enter holidays that move each year, such as those set by a lunar calendar, once per year.') }}</div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save closure') }}</button>
        @if($editing)<a href="{{ route('library.calendar', ['branch' => $branch]) }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>@endif
      </div>
    </form>
  </div>
</div>
@endif
@endsection
