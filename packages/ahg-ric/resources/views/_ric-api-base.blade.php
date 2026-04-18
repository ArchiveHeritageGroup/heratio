{{-- Injects the RiC API base URL as a JS constant. Include once per page
     that uses embedded fetch() calls to /api/ric/v1/*. Value driven by
     config('ric.api_url') (set after the Phase 4.3 split) or defaults to
     the in-process /api/ric/v1. --}}
<script>
    window.RIC_API_BASE = @json($ricApiBase);
    var RIC_API_BASE = window.RIC_API_BASE;
</script>
