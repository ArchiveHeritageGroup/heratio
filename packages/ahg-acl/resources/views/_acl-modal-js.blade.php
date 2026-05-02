{{-- Autocomplete + "Submit" wiring for the _acl-modal partials.
     Loaded once per ACL editor page; binds every #acl-modal-container-<type>
     it finds. New scope rows are inserted just before the "Add permissions
     by..." trigger inside the matching accordion-body. --}}
<script>
(function () {
  function ready(fn) {
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }
  ready(function () {
    document.querySelectorAll('[id^="acl-modal-container-"]').forEach(function (modal) {
      var entityType = modal.id.replace('acl-modal-container-', '');
      var select = modal.querySelector('#acl-autocomplete-' + entityType);
      var listInput = modal.querySelector('input.list');
      var url = listInput ? listInput.value : '';
      if (!select || !url) return;

      // Replace the empty <select> with a search box + native multiline select
      var search = document.createElement('input');
      search.type = 'text';
      search.className = 'form-control form-control-sm mb-2';
      search.placeholder = 'Type to search...';
      select.parentNode.insertBefore(search, select);
      select.size = 8;
      select.classList.remove('form-autocomplete');

      var t;
      search.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(function () {
          var sep = url.indexOf('?') > -1 ? '&' : '?';
          fetch(url + sep + 'term=' + encodeURIComponent(search.value) + '&query=' + encodeURIComponent(search.value), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
          })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              select.innerHTML = '';
              (data || []).forEach(function (item) {
                var opt = document.createElement('option');
                opt.value = item.slug || ('' + item.id);
                opt.textContent = item.name || item.title || opt.value;
                opt.dataset.slug = item.slug || '';
                opt.dataset.name = item.name || item.title || '';
                opt.dataset.entityId = '' + item.id;
                select.appendChild(opt);
              });
            })
            .catch(function () { /* swallow */ });
        }, 250);
      });

      // Submit button = last button in modal footer
      var btns = modal.querySelectorAll('.modal-footer button');
      var submitBtn = btns.length ? btns[btns.length - 1] : null;
      if (!submitBtn) return;
      submitBtn.addEventListener('click', function () {
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return;
        addAclScope(entityType, opt.dataset.slug || opt.value, opt.dataset.name || opt.textContent);
        if (window.bootstrap) {
          var inst = bootstrap.Modal.getInstance(modal);
          if (inst) inst.hide();
        }
      });
    });
  });

  function addAclScope(entityType, slug, name) {
    var modal = document.getElementById('acl-modal-container-' + entityType);
    if (!modal) return;
    var tplWrap = modal.parentNode.querySelector('.acl-table-container.d-none');
    if (!tplWrap) return;

    var clone = tplWrap.cloneNode(true);
    clone.classList.remove('d-none');

    // Scope key:
    //  - IO ACL per-repository modal (entityType=repository AND an IO modal also exists) → "repo:<slug>"
    //  - everything else → slug as-is. AclService::applyAclForm parses these.
    var hasIoModal = !!document.getElementById('acl-modal-container-informationobject');
    var scope = (entityType === 'repository' && hasIoModal) ? ('repo:' + slug) : slug;
    clone.innerHTML = clone.innerHTML.replace(/\{objectId\}/g, scope);

    // The template ships with Inherit pre-checked. A user adding a new scope
    // almost always wants Grant (otherwise why open the modal). Flip defaults
    // to Grant so a Save right after Submit actually writes rows; users can
    // still pick Deny / Inherit per-action.
    clone.querySelectorAll('input[type="radio"][value="-1"]').forEach(function (r) { r.checked = false; });
    clone.querySelectorAll('input[type="radio"][value="1"]').forEach(function (r) { r.checked = true; });

    var caption = clone.querySelector('caption span');
    if (caption) caption.textContent = name + ' (newly added — review and Save)';

    // Visual highlight so the user notices the new section
    clone.style.outline = '2px solid var(--ahg-primary, #005837)';
    clone.style.outlineOffset = '2px';

    var triggerBtn = document.getElementById('acl-add-' + entityType);
    if (triggerBtn && triggerBtn.parentNode) {
      triggerBtn.parentNode.insertBefore(clone, triggerBtn);
      clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
})();
</script>
