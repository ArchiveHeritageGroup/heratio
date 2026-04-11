@extends('theme::layouts.2col')
@section('title', 'Portable Export')
@section('body-class', 'admin settings')
@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection
@section('title-block')
  <h1><i class="fas fa-compact-disc me-2"></i>Portable Export</h1>
  <p class="text-muted small mb-0">Portable offline export configuration</p>
@endsection
@section('content')
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  <form method="post" action="{{ route('settings.ahg.portable_export') }}">
    @csrf
    @php
      $checkboxPrefixes = ['enabled','auto','require','notify','show','enforce','allow','loop','extract','create','generate','include','overwrite','save','batch','preserve','strip','rotate','orient','watermark','cascade','sync','blur','store','hover','audit','force','expiry'];
      $passwordKeys = ['password','api_key','secret','salt'];
      $selectKeys = [];
    @endphp
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0"><i class="fas fa-compact-disc me-2"></i>Portable Export</h5></div>
      <div class="card-body">
        @forelse($settings as $key => $val)
          @php
            $isCheckbox = false;
            foreach ($checkboxPrefixes as $pfx) { if (str_contains($key, $pfx)) { $isCheckbox = true; break; } }
            $isPassword = false;
            foreach ($passwordKeys as $pk) { if (str_contains($key, $pk)) { $isPassword = true; break; } }
            $label = ucfirst(str_replace('_', ' ', preg_replace('/^[a-z]+_/', '', $key)));
          @endphp
          @if($isCheckbox && in_array($val, ['true','false','1','0']))
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" id="{{ $key }}" name="settings[{{ $key }}]" value="true" {{ in_array($val, ['true','1']) ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}"><strong>{{ $label }}</strong></label>
            </div>
          @elseif($isPassword)
            <div class="mb-3">
              <label for="{{ $key }}" class="form-label"><strong>{{ $label }}</strong></label>
              <div class="input-group" style="max-width:500px">
                <input type="password" class="form-control" id="{{ $key }}" name="settings[{{ $key }}]" value="{{ e($val) }}" autocomplete="off">
                <button class="btn atom-btn-white" type="button" onclick="var i=document.getElementById('{{ $key }}');i.type=i.type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
              </div>
            </div>
          @elseif(is_numeric($val) && $val !== '' && !str_contains($key, 'id'))
            <div class="mb-3">
              <label for="{{ $key }}" class="form-label"><strong>{{ $label }}</strong></label>
              <input type="number" class="form-control" id="{{ $key }}" name="settings[{{ $key }}]" value="{{ e($val) }}" style="max-width:300px">
            </div>
          @else
            <div class="mb-3">
              <label for="{{ $key }}" class="form-label"><strong>{{ $label }}</strong></label>
              <input type="text" class="form-control" id="{{ $key }}" name="settings[{{ $key }}]" value="{{ e($val) }}">
            </div>
          @endif
        @empty
          <div class="alert alert-info mb-0"><i class="fas fa-info-circle me-1"></i>No settings configured yet. Save to create default entries.</div>
        @endforelse
      </div>
    </div>
    <div class="d-flex justify-content-between">
      <a href="{{ route('settings.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Settings</button>
    </div>
  </form>
@endsection
