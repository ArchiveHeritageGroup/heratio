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
    {{-- Inline SVG, not an icon font: the theme ships Font Awesome, whose brands set
         is not in the served bundle, and Bootstrap Icons' stylesheet (which has
         bi-whatsapp) is loaded on two pages only - so the bubble rendered empty.
         Path extracted from the shipped bootstrap-icons.woff, U+F618, y-flipped. --}}
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300" width="28" height="28" fill="currentColor" aria-hidden="true" focusable="false"><g transform="translate(0,300) scale(1,-1)"><path d="M255 256Q234 277 206.5 288.5Q179 300 150 300Q110 300 75.5 280.0Q41 260 21.0 226.0Q1 192 1.0 152.0Q1 112 21 77L0 0L79 21Q112 3 150 3Q190 3 224.5 23.0Q259 43 279.0 77.0Q299 111 299 151Q299 181 287.5 208.5Q276 236 255 256ZM150 28Q116 28 87 45L82 48L36 35L48 81L45 86Q26 116 26.0 150.5Q26 185 43.0 213.5Q60 242 88.0 258.5Q116 275 150 275Q175 275 197.5 265.5Q220 256 237.5 238.5Q255 221 264.5 198.5Q274 176 273 151Q273 118 256.5 89.5Q240 61 211.5 44.5Q183 28 150 28ZM218 120Q197 131 193.0 132.0Q189 133 187.5 133.0Q186 133 184 131Q180 125 172 116Q169 113 164 115L162 116Q146 123 134.5 133.5Q123 144 114 159Q112 162 112.5 163.5Q113 165 115 167L121 173Q122 175 124 178L125 179Q126 182 124 186L113 214Q111 218 109.5 219.0Q108 220 105 220H97Q91 220 87 215Q82 209 79 204Q74 196 74.0 184.5Q74 173 81 159Q85 152 90 146V145Q99 132 110 121Q131 99 153 90Q162 86 173.5 82.5Q185 79 198 81Q204 81 212.0 86.5Q220 92 222.5 98.0Q225 104 225.5 109.0Q226 114 225.0 115.5Q224 117 220 119Z"/></g></svg>
  </a>
  <style>
    /* Bottom-right, below the voice mic (bottom: 92px, 16px clear) and clear of the
       chatbot (bottom-left). Deliberately the lowest of the three: it is the text
       channel, so it stays the easiest to reach for anyone who cannot use the mic. */
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
    /* Line up with the mic, which moves to right: 14px on phones. */
    @media (max-width: 575.98px) { .ahg-whatsapp-bubble { right: 14px; } }
    @media print { .ahg-whatsapp-bubble { display: none; } }
  </style>
@endif
