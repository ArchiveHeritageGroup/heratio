@php $gaKey = QubitSetting::getByName('google_analytics');
if (empty($gaKey)) {
    $gaKey = sfConfig::get('app_google_analytics_api_key', '');
} @endphp
@if(!empty($gaKey))
    <script {{ __(sfConfig::get('csp_nonce', '')) }} async src="https://www.googletagmanager.com/gtag/js?id=@php echo $gaKey; @endphp"></script>
    <script {{ __(sfConfig::get('csp_nonce', '')) }}>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    @php include_slot('google_analytics'); @endphp
    gtag('config', '@php echo $gaKey; @endphp');
    </script>
@endforeach
