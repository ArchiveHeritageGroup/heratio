@extends('theme::layouts.1col')
@section('title', __('Stationery Templates'))
@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="mb-0"><i class="fas fa-table-cells me-2"></i>{{ __('Stationery Templates') }}</h1>
    <div class="d-flex gap-2">
      @if($ready)<a href="{{ route('library.labels.templates.create') }}" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>{{ __('Add template') }}</a>@endif
      <a href="{{ route('library.labels.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
  </div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if(! $ready)
    <div class="alert alert-warning">{{ __('Label printing is not set up on this installation yet. Run the database migrations.') }}</div>
  @else
    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead><tr>
          <th>{{ __('Name') }}</th><th>{{ __('Page (mm)') }}</th><th>{{ __('Label (mm)') }}</th><th>{{ __('Pitch (mm)') }}</th><th>{{ __('Margins top / left') }}</th><th>{{ __('Layout') }}</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($templates as $t)
          <tr>
            <td>{{ $t->name }} @if($t->is_builtin)<span class="badge bg-secondary">{{ __('Built-in') }}</span>@endif</td>
            <td>{{ (float) $t->page_width_mm }} x {{ (float) $t->page_height_mm }}</td>
            <td>{{ (float) $t->label_width_mm }} x {{ (float) $t->label_height_mm }}</td>
            <td>{{ (float) $t->pitch_x_mm }} x {{ (float) $t->pitch_y_mm }}</td>
            <td>{{ (float) $t->margin_top_mm }} / {{ (float) $t->margin_left_mm }}</td>
            <td>{{ $t->columns_across }} x {{ $t->rows_down }}</td>
            <td class="text-nowrap">
              <a class="btn btn-sm atom-btn-white" href="{{ route('library.labels.templates.create', ['from' => $t->id]) }}">{{ __('Copy') }}</a>
              @unless($t->is_builtin)
                <a class="btn btn-sm atom-btn-white" href="{{ route('library.labels.templates.edit', $t->id) }}">{{ __('Edit') }}</a>
                <form method="post" action="{{ route('library.labels.templates.delete', $t->id) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                </form>
              @endunless
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
