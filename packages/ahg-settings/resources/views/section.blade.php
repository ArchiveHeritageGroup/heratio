@extends('theme::layouts.1col')
@section('title', $sectionLabel)
@section('body-class', 'admin settings')

@section('content')
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $sectionLabel }}</li>
    </ol>
  </nav>

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-sliders-h me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $sectionLabel }}</h1>
      <span class="small text-muted">{{ $settings->count() }} {{ Str::plural('setting', $settings->count()) }}</span>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @if($settings->isEmpty())
    <div class="alert alert-info">No editable settings found in this section.</div>
  @else
    <form method="post" action="{{ route('settings.section', $section) }}">
      @csrf
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 35%">Setting</th>
              <th>Value</th>
            </tr>
          </thead>
          <tbody>
            @foreach($settings as $setting)
              <tr>
                <td><code>{{ $setting->name }}</code></td>
                <td>
                  <input type="text" name="settings[{{ $setting->id }}]" class="form-control form-control-sm" value="{{ e($setting->value ?? '') }}">
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary ms-2">Back</a>
    </form>
  @endif
@endsection
