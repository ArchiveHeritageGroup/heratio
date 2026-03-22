{{-- Dynamic CSS custom properties injected into page head --}}
<style>
:root {
  --ahg-primary: {{ $themeSettings['ahg_primary_color'] ?? '#005837' }};
  --ahg-secondary: {{ $themeSettings['ahg_secondary_color'] ?? '#37A07F' }};
  --ahg-background-light: {{ $themeSettings['ahg_body_bg'] ?? '#ffffff' }};
  --ahg-body-text: {{ $themeSettings['ahg_body_text'] ?? '#212529' }};
  --ahg-footer-bg: {{ $themeSettings['ahg_footer_bg'] ?? '#005837' }};
  --ahg-footer-text: {{ $themeSettings['ahg_footer_text_color'] ?? '#ffffff' }};
  --ahg-header-bg: {{ $themeSettings['ahg_header_bg'] ?? '#212529' }};
  --ahg-header-text: {{ $themeSettings['ahg_header_text'] ?? '#ffffff' }};
  --ahg-card-header-bg: {{ $themeSettings['ahg_card_header_bg'] ?? '#005837' }};
  --ahg-card-header-text: {{ $themeSettings['ahg_card_header_text'] ?? '#ffffff' }};
  --ahg-btn-bg: {{ $themeSettings['ahg_button_bg'] ?? '#005837' }};
  --ahg-btn-text: {{ $themeSettings['ahg_button_text'] ?? '#ffffff' }};
  --ahg-link-color: {{ $themeSettings['ahg_link_color'] ?? '#005837' }};
}
</style>
