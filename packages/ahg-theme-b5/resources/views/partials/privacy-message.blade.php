{{-- Privacy notification banner --}}
@if(config('app.privacy_notification_enabled', false) && !session('privacy_message_dismissed'))
  @php
    $privacyNotification = \DB::table('setting_i18n')
        ->join('setting', 'setting.id', '=', 'setting_i18n.id')
        ->where('setting.name', 'privacy_notification')
        ->where('setting_i18n.culture', app()->getLocale())
        ->value('setting_i18n.value');
  @endphp
  @if(!empty($privacyNotification))
    <div id="privacy-message" class="alert alert-info alert-dismissible rounded-0 text-center mb-0" role="alert">
      {!! $privacyNotification !!}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
    </div>
  @endif
@endif
