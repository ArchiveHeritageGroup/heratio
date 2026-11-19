{{-- heratio#1751: hash the chosen file in the browser (SHA-256) and send it with
     the upload, so the server can tell a file damaged in transfer from the one
     the user picked. Needs HTTPS (crypto.subtle); without it, or for files over
     the limit below, the upload goes ahead unhashed as before.
     ponytail: crypto.subtle has no streaming digest, so the whole file is read
     into memory; 512 MB is the ceiling. A chunked hasher would lift it. --}}
{{-- heratio#1749: a file over this page's limit is caught when chosen, with the
     routes that take larger files, instead of failing after a long upload. --}}
@php
  $__maxBytes = \AhgCore\Services\DigitalObjectService::getMaxUploadSize();
  $__tooBig = __('This file is larger than :size, the most this page accepts. Upload it through the ingest wizard, which sends large files in resumable parts, or by FTP, then link it here.', ['size' => \AhgCore\Services\DigitalObjectService::formatFileSize($__maxBytes)]);
  $__links = [
      ['href' => \Illuminate\Support\Facades\Route::has('ingest.index') ? route('ingest.index') : null, 'text' => __('Ingest wizard')],
      ['href' => \Illuminate\Support\Facades\Route::has('ftpUpload.index') ? route('ftpUpload.index') : null, 'text' => __('FTP upload')],
  ];
@endphp
<script>
(function () {
  if (window.__ahgSizeCheck) return; window.__ahgSizeCheck = true;
  var MAX = {{ (int) $__maxBytes }}, MSG = @json($__tooBig);
  var LINKS = @json($__links);
  document.addEventListener('change', function (ev) {
    var input = ev.target;
    if (!input.matches || !input.matches('input[type=file][name="digital_object"]')) return;
    var old = input.parentNode.querySelector('.ahg-too-big'); if (old) old.remove();
    var f = input.files && input.files[0];
    if (!MAX || !f || f.size <= MAX) return;
    input.value = '';
    var box = document.createElement('div'); box.className = 'alert alert-warning mt-2 ahg-too-big'; box.setAttribute('role', 'alert');
    box.textContent = MSG + ' ';
    LINKS.forEach(function (l) { if (!l.href) return; var a = document.createElement('a'); a.href = l.href; a.textContent = l.text; a.className = 'me-2'; box.appendChild(a); });
    input.insertAdjacentElement('afterend', box);
  });
})();
</script>
<script>
(function () {
  if (window.__ahgClientHash) return; window.__ahgClientHash = true;
  var LIMIT = 512 * 1024 * 1024;
  function hex(buf) { return Array.prototype.map.call(new Uint8Array(buf), function (b) { return ('0' + b.toString(16)).slice(-2); }).join(''); }
  document.addEventListener('submit', function (ev) {
    var form = ev.target, input = form.querySelector('input[type=file][name="digital_object"]');
    if (!input || !input.files || !input.files[0] || form.dataset.hashed === '1') return;
    var file = input.files[0];
    if (!(window.crypto && crypto.subtle) || file.size > LIMIT) return;
    ev.preventDefault();
    var btn = form.querySelector('[type=submit]'); if (btn) btn.disabled = true;
    file.arrayBuffer().then(function (b) { return crypto.subtle.digest('SHA-256', b); }).then(function (d) {
      var h = form.querySelector('input[name="client_sha256"]');
      if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'client_sha256'; form.appendChild(h); }
      h.value = hex(d);
    }).catch(function () {}).then(function () { form.dataset.hashed = '1'; if (btn) btn.disabled = false; form.requestSubmit ? form.requestSubmit(btn || undefined) : form.submit(); });
  }, true);
})();
</script>
