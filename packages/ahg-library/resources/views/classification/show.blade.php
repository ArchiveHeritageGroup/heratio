@extends('theme::layouts.1col')
@section('title', $schedule->name)

@section('content')
<div class="container py-4">
  <div class="d-flex align-items-center mb-3">
    <h2 class="mb-0">{{ $schedule->name }} @if($schedule->edition)<small class="text-muted">{{ $schedule->edition }}</small>@endif</h2>
    <a href="{{ route('library.classification-index') }}" class="btn btn-outline-secondary btn-sm ms-auto">{{ __('All schedules') }}</a>
  </div>

  @include('ahg-library::classification._errors')

  <form method="GET" class="input-group mb-3" action="{{ route('library.classification-show', $schedule->id) }}">
    <input class="form-control" name="q" value="{{ $q }}" maxlength="255" placeholder="{{ __('Search notation or caption') }}" aria-label="{{ __('Search notation or caption') }}">
    <button class="btn btn-primary">{{ __('Search') }}</button>
  </form>

  <nav aria-label="{{ __('Hierarchy') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('library.classification-show', $schedule->id) }}">{{ __('Top') }}</a></li>
      @foreach($trail as $t)
        <li class="breadcrumb-item"><a href="{{ route('library.classification-show', [$schedule->id, 'parent' => $t->id]) }}">{{ $t->notation }} {{ $t->caption }}</a></li>
      @endforeach
    </ol>
  </nav>

  <div class="card mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>{{ __('Notation') }}</th><th>{{ __('Caption') }}</th><th>{{ __('Narrower') }}</th><th></th></tr></thead>
        <tbody>
          @forelse($entries as $e)
            <tr>
              <td><code>{{ $e->notation }}@if($e->notation_end) - {{ $e->notation_end }}@endif</code></td>
              <td>{{ $e->caption }}@if($e->note)<br><small class="text-muted">{{ $e->note }}</small>@endif</td>
              <td>
                @if($e->child_count)
                  <a href="{{ route('library.classification-show', [$schedule->id, 'parent' => $e->id]) }}">{{ $e->child_count }}</a>
                @else - @endif
              </td>
              <td class="text-end">
                <details class="d-inline-block text-start me-1">
                  <summary class="btn btn-sm btn-outline-secondary">{{ __('Edit') }}</summary>
                  <form method="POST" class="mt-2" style="min-width:260px" action="{{ route('library.classification-entry-update', $e->id) }}">
                    @csrf @method('PUT')
                    <input type="hidden" name="parent_id" value="{{ $e->parent_id }}">
                    <input class="form-control form-control-sm mb-1" name="notation" maxlength="100" required value="{{ $e->notation }}" aria-label="{{ __('Notation') }}">
                    <input class="form-control form-control-sm mb-1" name="notation_end" maxlength="100" value="{{ $e->notation_end }}" placeholder="{{ __('Range end (optional)') }}" aria-label="{{ __('Range end (optional)') }}">
                    <input class="form-control form-control-sm mb-1" name="caption" maxlength="500" required value="{{ $e->caption }}" aria-label="{{ __('Caption') }}">
                    <textarea class="form-control form-control-sm mb-1" name="note" rows="2" maxlength="5000" aria-label="{{ __('Note') }}">{{ $e->note }}</textarea>
                    <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                  </form>
                </details>
                <form method="POST" class="d-inline" action="{{ route('library.classification-entry-destroy', $e->id) }}" onsubmit="return confirm(@js(__('Delete this entry?')))">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }} {{ $e->notation }}"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-3">{{ __('No entries here.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="row">
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">{{ __('Add entry') }}</h5></div>
        <div class="card-body">
          <form method="POST" action="{{ route('library.classification-entry-store', $schedule->id) }}">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $parent->id ?? '' }}">
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label" for="notation">{{ __('Notation') }}</label>
                <input class="form-control" id="notation" name="notation" maxlength="100" required value="{{ old('notation') }}">
              </div>
              <div class="col-6 mb-3">
                <label class="form-label" for="notation_end">{{ __('Range end (optional)') }}</label>
                <input class="form-control" id="notation_end" name="notation_end" maxlength="100" value="{{ old('notation_end') }}">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="caption">{{ __('Caption') }}</label>
              <input class="form-control" id="caption" name="caption" maxlength="500" required value="{{ old('caption') }}">
            </div>
            <div class="mb-3">
              <label class="form-label" for="note">{{ __('Note') }}</label>
              <textarea class="form-control" id="note" name="note" rows="2" maxlength="5000">{{ old('note') }}</textarea>
            </div>
            <button class="btn btn-primary">{{ __('Add') }}</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6 mb-4">
      <div class="card h-100">
        <div class="card-header"><h5 class="mb-0">{{ __('Load from CSV') }}</h5></div>
        <div class="card-body">
          <p class="small text-muted">{{ __('Columns: notation, caption, and optionally notation_end, parent_notation, note. Existing notations are updated in the columns the file contains; other columns are left alone. If any row is wrong nothing is loaded. Without parent_notation the broader entry of a new row is worked out from the notation and an existing entry keeps its own; schedules arranged by ranges should supply it.') }}</p>
          <form method="POST" enctype="multipart/form-data" action="{{ route('library.classification-import', $schedule->id) }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="file">{{ __('CSV file') }}</label>
              <input class="form-control" type="file" id="file" name="file" accept=".csv,text/csv" required>
            </div>
            <button class="btn btn-primary">{{ __('Load') }}</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('Schedule settings') }}</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('library.classification-update', $schedule->id) }}">
        @csrf @method('PUT')
        @include('ahg-library::classification._schedule_fields', ['s' => $schedule])
        <button class="btn btn-primary">{{ __('Save') }}</button>
      </form>
      <form method="POST" class="mt-2" action="{{ route('library.classification-destroy', $schedule->id) }}" onsubmit="return confirm(@js(__('Delete this schedule and all its entries?')))">
        @csrf @method('DELETE')
        <button class="btn btn-outline-danger">{{ __('Delete schedule') }}</button>
      </form>
    </div>
  </div>
</div>
@endsection
