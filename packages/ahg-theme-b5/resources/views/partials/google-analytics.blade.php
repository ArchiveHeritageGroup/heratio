{{-- Google Analytics --}}
@php
    $gaKey = \DB::table('setting_i18n')
        ->join('setting', 'setting.id', '=', 'setting_i18n.id')
        ->where('setting.name', 'google_analytics')
        ->where('setting_i18n.culture', app()->getLocale())
        ->value('setting_i18n.value')
        ?? config('app.google_analytics_api_key', '');
@endphp
@if(!empty($gaKey))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaKey }}"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $gaKey }}');
    </script>
@endif
