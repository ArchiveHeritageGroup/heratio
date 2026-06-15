{{--
    Compatibility bridge layout for the AI Chatbot admin/index/review views.

    Those Blade templates (locked) `@extends('layouts/admin')`, a layout name
    that has never existed in Heratio. The canonical admin layout is
    `theme::layouts.1col`. This bridge maps `layouts/admin` onto it so the
    chatbot pages render instead of throwing "View [layouts.admin] not found".

    The chatbot views also `@push('scripts')`, but the master layout only
    renders `@stack('js')`. We re-emit the `scripts` stack into the `js` stack
    so their inline JS still loads.

    Copyright (C) 2026 Johan Pieterse
    AGPL-3.0
--}}
@extends('theme::layouts.1col')

@push('js')
    @stack('scripts')
@endpush
