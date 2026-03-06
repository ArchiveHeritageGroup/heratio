@extends('theme::layouts.1col')

@section('title', $groupLabel . ' - AHG Settings')
@section('body-class', 'admin settings')

@section('content')
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('settings.index') }}">Settings</a></li>
      <li class="breadcrumb-item active" aria-current="page">{{ $groupLabel }}</li>
    </ol>
  </nav>

  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-puzzle-piece me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">{{ $groupLabel }}</h1>
      <span class="small text-muted">AHG settings &mdash; {{ $settings->count() }} {{ Str::plural('setting', $settings->count()) }}</span>
    </div>
  </div>

  @if($settings->isEmpty())
    <div class="alert alert-info">No settings found in this group.</div>
  @else
    <div class="table-responsive mb-3">
      <table class="table table-bordered table-striped mb-0">
        <thead>
          <tr>
            <th style="width: 35%">Setting</th>
            <th>Value</th>
            <th style="width: 15%">Type</th>
          </tr>
        </thead>
        <tbody>
          @foreach($settings as $setting)
            <tr>
              <td>
                <code>{{ $setting->setting_key }}</code>
                @if($setting->description)
                  <br><small class="text-muted">{{ $setting->description }}</small>
                @endif
              </td>
              <td>
                @if($setting->setting_type === 'boolean')
                  @if($setting->setting_value === '1' || $setting->setting_value === 'true')
                    <span class="badge bg-success">Enabled</span>
                  @else
                    <span class="badge bg-secondary">Disabled</span>
                  @endif
                @elseif($setting->setting_value !== null && $setting->setting_value !== '')
                  {{ Str::limit($setting->setting_value, 200) }}
                @else
                  <span class="text-muted fst-italic">Not set</span>
                @endif
              </td>
              <td>
                <span class="badge bg-light text-dark">{{ $setting->setting_type ?? 'string' }}</span>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
