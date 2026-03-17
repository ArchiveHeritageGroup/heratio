@extends('theme::layouts.1col')
@section('title', 'Email Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-envelope me-2"></i>Email Settings</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.email') }}">
      @csrf
      <div class="row">
        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header"><i class="fas fa-server me-2"></i>SMTP Configuration</div>
            <div class="card-body">
              @foreach ($smtpSettings as $setting)
                <div class="mb-3">
                  <label class="form-label">{{ ucwords(str_replace('_', ' ', str_replace('smtp_', '', $setting->setting_key))) }}</label>
                  @if ($setting->setting_type === 'boolean')
                    <select name="settings[{{ $setting->setting_key }}]" class="form-select">
                      <option value="0" {{ $setting->setting_value == '0' ? 'selected' : '' }}>Disabled</option>
                      <option value="1" {{ $setting->setting_value == '1' ? 'selected' : '' }}>Enabled</option>
                    </select>
                  @elseif ($setting->setting_type === 'password')
                    <input type="password" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @elseif ($setting->setting_type === 'number')
                    <input type="number" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @else
                    <input type="{{ $setting->setting_type === 'email' ? 'email' : 'text' }}" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                  @endif
                  @if ($setting->description)<small class="text-muted">{{ e($setting->description) }}</small>@endif
                </div>
              @endforeach
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header"><i class="fas fa-bell me-2"></i>Notification Recipients</div>
            <div class="card-body">
              @foreach ($notificationSettings as $setting)
                <div class="mb-3">
                  <label class="form-label">{{ ucwords(str_replace('_', ' ', str_replace('notify_', '', $setting->setting_key))) }}</label>
                  <input type="email" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}" placeholder="admin@example.com">
                  @if ($setting->description)<small class="text-muted">{{ e($setting->description) }}</small>@endif
                </div>
              @endforeach
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card mb-4">
            <div class="card-header"><i class="fas fa-file-alt me-2"></i>Email Templates</div>
            <div class="card-body">
              <div class="alert alert-info small">
                <strong>Available placeholders:</strong><br>
                <code>{name}</code> Recipient name, <code>{email}</code> Recipient email,
                <code>{institution}</code> Institution, <code>{login_url}</code> Login URL,
                <code>{reset_url}</code> Reset URL, <code>{date}</code> / <code>{time}</code> Booking details
              </div>
              <div class="accordion" id="templateAccordion">
                @php $index = 0; @endphp
                @foreach ($templateSettings as $setting)
                  @if (str_ends_with($setting->setting_key, '_subject'))
                    @php
                      $index++;
                      $baseKey = str_replace('_subject', '', $setting->setting_key);
                      $bodyKey = $baseKey . '_body';
                      $bodySetting = $templateSettings->firstWhere('setting_key', $bodyKey);
                      $label = ucwords(str_replace('_', ' ', str_replace('email_', '', $baseKey)));
                    @endphp
                    <div class="accordion-item">
                      <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tpl{{ $index }}">{{ $label }}</button></h2>
                      <div id="tpl{{ $index }}" class="accordion-collapse collapse" data-bs-parent="#templateAccordion">
                        <div class="accordion-body">
                          <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="settings[{{ $setting->setting_key }}]" class="form-control" value="{{ e($setting->setting_value ?? '') }}">
                          </div>
                          @if($bodySetting)
                          <div class="mb-3">
                            <label class="form-label">Body</label>
                            <textarea name="settings[{{ $bodyKey }}]" class="form-control" rows="5">{{ e($bodySetting->setting_value ?? '') }}</textarea>
                          </div>
                          @endif
                        </div>
                      </div>
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><i class="fas fa-bell me-2"></i>Notification Toggles</div>
        <div class="card-body">
          @foreach (['research_email_notifications' => 'Research notifications', 'access_request_email_notifications' => 'Access request notifications', 'workflow_email_notifications' => 'Workflow notifications'] as $key => $label)
            <div class="form-check form-switch mb-2">
              <input type="hidden" name="notif_toggles[{{ $key }}]" value="0">
              <input class="form-check-input" type="checkbox" name="notif_toggles[{{ $key }}]" value="1" id="{{ $key }}" {{ ($notifToggles[$key] ?? '1') == '1' ? 'checked' : '' }}>
              <label class="form-check-label" for="{{ $key }}">{{ $label }}</label>
            </div>
          @endforeach
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
