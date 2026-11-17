{{-- CSP-safe print buttons: any [data-heritage-print] element prints the page. --}}
<script nonce="{{ function_exists('csp_nonce') ? csp_nonce() : '' }}">
document.querySelectorAll('[data-heritage-print]').forEach(function (b) {
  b.addEventListener('click', function () { window.print(); });
});
</script>
