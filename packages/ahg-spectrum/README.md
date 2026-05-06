Spectrum settings wiring

This package includes a helper `SpectrumSettings` (services/SpectrumSettings.php) to read `spectrum_*` settings from `ahg_settings` and several default behaviours.

Implemented:
- Master switch: `spectrum_enabled` - when false the spectrum routes and UI return 404 and are hidden.
- Default read helpers: default currency, loan default period, valves for requiring photos/valuation/insurance.

Outstanding:
- validators in controllers and view wiring for barcode, and notification scheduling.
