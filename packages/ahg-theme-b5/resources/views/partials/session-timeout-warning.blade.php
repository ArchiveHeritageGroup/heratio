{{--
  heratio#1543. A few minutes before an idle session ends, offer to keep it,
  so unsaved description work is not lost. The lifetime is the one the
  SessionTimeout middleware set for this request (Admin > Security >
  Session timeout); "Stay signed in" posts to session.keepalive, which renews
  the session, and the countdown starts again.
--}}
@php
  $stLifetimeMs = max(1, (int) config('session.lifetime', 120)) * 60000;
  $stWarnMs = max(0, $stLifetimeMs - min(5 * 60000, intdiv($stLifetimeMs, 2)));
  $stNonce = function_exists('csp_nonce') ? csp_nonce() : '';
@endphp
<div id="sessionTimeoutWarning" class="toast position-fixed bottom-0 end-0 m-3" role="alertdialog" aria-live="assertive" aria-atomic="true"
     aria-labelledby="sessionTimeoutTitle" data-bs-autohide="false" style="z-index:1090">
  <div class="toast-header">
    <strong class="me-auto" id="sessionTimeoutTitle">{{ __('Your session is about to end') }}</strong>
  </div>
  <div class="toast-body">
    <p class="mb-2">{{ __('You will be signed out soon because of inactivity. Unsaved changes will be lost.') }}</p>
    <button type="button" class="btn btn-sm atom-btn-outline-success" id="sessionTimeoutStay">{{ __('Stay signed in') }}</button>
  </div>
</div>
<script nonce="{{ $stNonce }}">
(function start() {
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', start); return; }
  var warnMs = {{ $stWarnMs }}, url = @json(route('session.keepalive')), timer = null;
  var el = document.getElementById('sessionTimeoutWarning');
  if (!el || !window.bootstrap) { return; }
  var toast = bootstrap.Toast.getOrCreateInstance(el);
  function arm() { clearTimeout(timer); timer = setTimeout(function () { toast.show(); document.getElementById('sessionTimeoutStay').focus(); }, warnMs); }
  document.getElementById('sessionTimeoutStay').addEventListener('click', function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': token ? token.content : '', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { if (r.ok) { toast.hide(); arm(); } else { window.location.reload(); } })
      .catch(function () { /* offline: leave the warning up */ });
  });
  arm();
})();
</script>
