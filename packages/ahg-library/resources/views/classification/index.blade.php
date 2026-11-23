@extends('theme::layouts.1col')
@section('title', __('Classification Schedules'))

@section('content')
<div class="container py-4">
  <h2 class="mb-3">{{ __('Classification Schedules') }}</h2>
  <p class="text-muted">{{ __('Load a schedule to browse it from the cataloguing form, check keyed numbers and file items in shelf order. One schedule is active per scheme.') }}</p>

  @include('ahg-library::classification._errors')

  <div class="card mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>{{ __('Name') }}</th><th>{{ __('Scheme') }}</th><th>{{ __('Edition') }}</th>
          <th>{{ __('Entries') }}</th><th>{{ __('Validation') }}</th><th>{{ __('Active') }}</th>
        </tr></thead>
        <tbody>
          @forelse($schedules as $s)
            <tr>
              <td><a href="{{ route('library.classification-show', $s->id) }}">{{ $s->name }}</a></td>
              <td>{{ $schemes[$s->scheme] ?? $s->scheme }}</td>
              <td>{{ $s->edition ?? '-' }}</td>
              <td>{{ number_format($s->entry_count) }}</td>
              <td>{{ $modes[$s->validation_mode] ?? $s->validation_mode }}</td>
              <td>{!! $s->is_active ? '<span class="badge bg-success">'.e(__('Active')).'</span>' : '<span class="badge bg-secondary">'.e(__('Inactive')).'</span>' !!}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No schedules yet.') }}</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h5 class="mb-0">{{ __('New schedule') }}</h5></div>
    <div class="card-body">
      <form method="POST" action="{{ route('library.classification-store') }}">
        @csrf
        @include('ahg-library::classification._schedule_fields', ['s' => null])
        <button class="btn btn-primary">{{ __('Create') }}</button>
      </form>
    </div>
  </div>
</div>
@endsection
