@extends('ahg-theme-b5::layout')

@section('title', 'Label Templates')

@section('content')
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2><i class="fas fa-tags me-2"></i> {{ __('Label Templates') }}</h2>
      <p class="text-muted small mb-0">{{ __('Configurable label / barcode sheet presets used by batch printing.') }}</p>
    </div>
    <a href="{{ route('ahglabel.templates.create') }}" class="btn btn-primary"><i class="fas fa-plus me-1"></i> {{ __('New template') }}</a>
  </div>

  @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead>
        <tr>
          <th>{{ __('Name') }}</th>
          <th>{{ __('Page') }}</th>
          <th class="text-center">{{ __('Grid') }}</th>
          <th class="text-center">{{ __('Label (mm)') }}</th>
          <th>{{ __('Shows') }}</th>
          <th class="text-center">{{ __('Default') }}</th>
          <th class="text-end">{{ __('Actions') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($templates as $t)
          <tr>
            <td><a href="{{ route('ahglabel.templates.edit', $t->id) }}">{{ $t->name }}</a></td>
            <td>{{ $t->page_size }}</td>
            <td class="text-center">{{ $t->columns }} &times; {{ $t->rows }}</td>
            <td class="text-center">{{ rtrim(rtrim(number_format($t->label_width_mm, 2), '0'), '.') }} &times; {{ rtrim(rtrim(number_format($t->label_height_mm, 2), '0'), '.') }}</td>
            <td class="small">
              @if($t->show_title)<span class="badge bg-light text-dark">title</span>@endif
              @if($t->show_identifier)<span class="badge bg-light text-dark">id</span>@endif
              @if($t->show_barcode)<span class="badge bg-light text-dark">barcode:{{ $t->barcode_source }}</span>@endif
              @if($t->show_qr)<span class="badge bg-light text-dark">qr</span>@endif
            </td>
            <td class="text-center">@if($t->is_default)<span class="badge bg-success">{{ __('default') }}</span>@endif</td>
            <td class="text-end">
              <a href="{{ route('ahglabel.templates.edit', $t->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}"><i class="fas fa-pen"></i></a>
              <form method="post" action="{{ route('ahglabel.templates.destroy', $t->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Delete this template?') }}');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-3">{{ __('No templates yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
