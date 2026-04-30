@extends('theme::layouts.1col')
@section('title', 'Dropdown Manager')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0"><i class="fas fa-list me-2"></i>Dropdown Manager</h1>
      <a href="{{ route('settings.dropdown.store') }}" class="btn atom-btn-white"><i class="fas fa-plus me-1"></i>{{ __('Add Dropdown') }}</a>
    </div>

    @if($dropdowns->isEmpty())
      <div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>No dropdowns configured. Click "Add Dropdown" to create one.</div>
    @else
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-dark">
            <tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th>{{ __('Description') }}</th><th>{{ __('Values') }}</th><th>{{ __('Actions') }}</th></tr>
          </thead>
          <tbody>
            @foreach($dropdowns as $dd)
              <tr>
                <td><strong>{{ $dd->name }}</strong></td>
                <td><code>{{ $dd->slug }}</code></td>
                <td>{{ Str::limit($dd->description ?? '', 60) }}</td>
                <td>{{ $dd->value_count ?? '-' }}</td>
                <td>
                  <a href="{{ route('settings.dropdown.store', ['id' => $dd->id]) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>
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
