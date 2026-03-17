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

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  @if($settings->isEmpty())
    <div class="alert alert-info">No settings found in this group.</div>
  @else
    <form method="post" action="{{ route('settings.ahg', $group) }}">
      @csrf
      <div class="table-responsive mb-3">
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr>
              <th style="width: 30%">Setting</th>
              <th>Value</th>
              <th style="width: 10%">Type</th>
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
                    <select name="settings[{{ $setting->setting_key }}]" class="form-select form-select-sm">
                      <option value="0" {{ !in_array($setting->setting_value, ['1', 'true']) ? 'selected' : '' }}>Disabled</option>
                      <option value="1" {{ in_array($setting->setting_value, ['1', 'true']) ? 'selected' : '' }}>Enabled</option>
                    </select>
                  @elseif($setting->setting_type === 'password')
                    <input type="password" name="settings[{{ $setting->setting_key }}]" class="form-control form-control-sm" value="{{ e($setting->setting_value ?? '') }}">
                  @elseif($setting->setting_type === 'number' || $setting->setting_type === 'integer')
                    <input type="number" name="settings[{{ $setting->setting_key }}]" class="form-control form-control-sm" value="{{ e($setting->setting_value ?? '') }}">
                  @elseif($setting->setting_type === 'text' || strlen($setting->setting_value ?? '') > 100)
                    <textarea name="settings[{{ $setting->setting_key }}]" class="form-control form-control-sm" rows="3">{{ e($setting->setting_value ?? '') }}</textarea>
                  @else
                    <input type="text" name="settings[{{ $setting->setting_key }}]" class="form-control form-control-sm" value="{{ e($setting->setting_value ?? '') }}">
                  @endif
                </td>
                <td><span class="badge bg-light text-dark">{{ $setting->setting_type ?? 'string' }}</span></td>
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
