{{-- WhatsApp chat bubble - Admin > AHG Settings > Features.
     A wa.me click-to-chat link: the visitor starts the conversation in their own
     WhatsApp, so no Meta API, message templates or consent register are involved.
     Renders nothing unless switched on AND the number is a plausible international
     one (8-15 digits once +, spaces and dashes are stripped). --}}
@php
  $waEnabled = \AhgCore\Services\AhgSettingsService::getBool('landing_whatsapp_enabled', false);
  $waNumber = preg_replace('/\D+/', '', (string) \AhgCore\Services\AhgSettingsService::get('landing_whatsapp_number', ''));
  $waMessage = trim((string) \AhgCore\Services\AhgSettingsService::get('landing_whatsapp_message', ''));
  $waUrl = 'https://wa.me/'.$waNumber.($waMessage !== '' ? '?text='.rawurlencode($waMessage) : '');
@endphp

@if ($waEnabled && strlen($waNumber) >= 8 && strlen($waNumber) <= 15)
  <a href="{{ $waUrl }}" class="ahg-whatsapp-bubble" target="_blank" rel="noopener noreferrer"
     aria-label="{{ __('Chat with us on WhatsApp') }}" title="{{ __('Chat with us on WhatsApp') }}">
    <i class="bi bi-whatsapp" aria-hidden="true"></i>
  </a>
  <style>
    /* Bottom-right, below the voice button (bottom: 80px) and clear of the chatbot (bottom-left). */
    .ahg-whatsapp-bubble {
      position: fixed; right: 20px; bottom: 20px; z-index: 1040;
      width: 56px; height: 56px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: #25D366; color: #fff; font-size: 28px; text-decoration: none;
      box-shadow: 0 4px 12px rgba(0, 0, 0, .25);
      transition: transform .15s ease, box-shadow .15s ease;
    }
    .ahg-whatsapp-bubble:hover, .ahg-whatsapp-bubble:focus-visible {
      color: #fff; transform: scale(1.06); box-shadow: 0 6px 16px rgba(0, 0, 0, .3);
    }
    .ahg-whatsapp-bubble:focus-visible { outline: 3px solid #128C7E; outline-offset: 3px; }
    @media print { .ahg-whatsapp-bubble { display: none; } }
  </style>
@endif
