{{-- Schedule browser and number check for the cataloguing form (#1475). --}}
@php($canManageSchedules = auth()->check() && \AhgCore\Services\AclService::hasPermission((int) auth()->id(), 'create'))
<div class="mb-3" id="class-picker"
     @if($canManageSchedules) data-manage="{{ route('library.classification-index') }}" @endif
     data-lookup="{{ route('library.classification-lookup') }}"
     data-check="{{ route('library.classification-check') }}">
  <button type="button" class="btn btn-sm btn-outline-secondary" id="class-picker-toggle" aria-expanded="false" aria-controls="class-picker-panel">
    <i class="fas fa-sitemap me-1"></i>{{ __('Browse schedule') }}
  </button>
  <span class="small ms-2" id="class-picker-status" role="status"></span>

  <div class="border rounded p-2 mt-2" id="class-picker-panel" hidden>
    <div class="input-group input-group-sm mb-2">
      <input type="search" class="form-control" id="class-picker-q" placeholder="{{ __('Search notation or caption') }}" aria-label="{{ __('Search notation or caption') }}">
      <button type="button" class="btn btn-outline-secondary" id="class-picker-search">{{ __('Search') }}</button>
    </div>
    <div class="small mb-2" id="class-picker-trail"></div>
    <ul class="list-unstyled mb-0" id="class-picker-list" style="max-height:260px;overflow:auto"></ul>
  </div>
</div>
<script>
(function () {
  var root = document.getElementById('class-picker');
  if (!root) return;
  var scheme = document.getElementById('classification_scheme');
  var call = document.getElementById('call_number');
  var dewey = document.getElementById('dewey_decimal');
  var panel = document.getElementById('class-picker-panel');
  var list = document.getElementById('class-picker-list');
  var trail = document.getElementById('class-picker-trail');
  var status = document.getElementById('class-picker-status');
  var q = document.getElementById('class-picker-q');
  var text = {
    choose: @json(__('Choose a classification scheme first.')),
    none: @json(__('No active schedule for this scheme.')),
    manage: @json(__('Manage schedules')),
    empty: @json(__('No entries.')),
    top: @json(__('Top')),
    failed: @json(__('The schedule could not be reached.')),
    exact: @json(__('In schedule:')),
    within: @json(__('Within:')),
    broader: @json(__('Not in the schedule. Nearest broader class:')),
    unknown: @json(__('Not in the schedule.')),
    invalid: @json(__('Not a valid number for this scheme.'))
  };

  function currentScheme() { return scheme.value || (dewey.value.trim() ? 'Dewey' : ''); }
  function isDewey() { return /^(dewey|ddc)$/i.test(currentScheme()); }
  function target() { return isDewey() ? dewey : call; }

  function fetchJson(url, params) {
    return fetch(url + '?' + new URLSearchParams(params), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(); return r.json(); });
  }
  function message(el, value) { el.textContent = value; }

  function load(params) {
    if (!currentScheme()) { list.replaceChildren(); trail.replaceChildren(); message(trail, text.choose); return; }
    fetchJson(root.dataset.lookup, Object.assign({ scheme: currentScheme() }, params)).then(function (d) {
      trail.replaceChildren();
      list.replaceChildren();
      if (!d.schedule) {
        message(trail, text.none);
        if (root.dataset.manage) {
          var manage = document.createElement('a');
          manage.href = root.dataset.manage;
          manage.className = 'ms-2';
          manage.textContent = text.manage;
          trail.append(manage);
        }
        return;
      }
      var top = crumb(text.top, {});
      trail.append(top);
      d.trail.forEach(function (t) { trail.append(' / ', crumb(t.notation + ' ' + t.caption, { parent: t.id })); });
      if (!d.entries.length) { var li = document.createElement('li'); li.className = 'text-muted'; li.textContent = text.empty; list.append(li); }
      d.entries.forEach(function (e) {
        var li = document.createElement('li');
        li.className = 'd-flex align-items-center gap-2 py-1';
        var use = document.createElement('button');
        use.type = 'button';
        use.className = 'btn btn-sm btn-outline-primary';
        use.textContent = e.notation;
        use.addEventListener('click', function () { target().value = e.notation; runCheck(); });
        var label = document.createElement('span');
        label.textContent = (e.notation_end ? '- ' + e.notation_end + ' ' : '') + e.caption;
        li.append(use, label);
        if (e.children) {
          var open = document.createElement('button');
          open.type = 'button';
          open.className = 'btn btn-sm btn-link ms-auto';
          open.textContent = '(' + e.children + ') >';
          open.setAttribute('aria-label', e.notation + ' ' + e.children);
          open.addEventListener('click', function () { load({ parent: e.id }); });
          li.append(open);
        }
        list.append(li);
      });
    }).catch(function () { message(trail, text.failed); });
  }
  function crumb(label, params) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-link btn-sm p-0';
    b.textContent = label;
    b.addEventListener('click', function () { load(params); });
    return b;
  }
  function runCheck() {
    var number = target().value.trim();
    status.className = 'small ms-2';
    if (!number || !currentScheme()) { message(status, ''); return; }
    fetchJson(root.dataset.check, { scheme: currentScheme(), number: number }).then(function (r) {
      if (r.status === 'exact' || r.status === 'within') {
        status.classList.add('text-success');
        message(status, text[r.status] + ' ' + r.path.map(function (p) { return p.notation + ' ' + p.caption; }).join(' > '));
      } else if (r.status === 'broader') {
        status.classList.add('text-warning-emphasis');
        message(status, text.broader + ' ' + r.path.map(function (p) { return p.notation + ' ' + p.caption; }).join(' > '));
      } else if (r.status === 'unknown' || r.status === 'invalid') {
        status.classList.add('text-danger');
        message(status, text[r.status]);
      } else {
        message(status, '');
      }
    }).catch(function () { message(status, text.failed); });
  }

  document.getElementById('class-picker-toggle').addEventListener('click', function () {
    panel.hidden = !panel.hidden;
    this.setAttribute('aria-expanded', String(!panel.hidden));
    if (!panel.hidden) load({});
  });
  document.getElementById('class-picker-search').addEventListener('click', function () { load({ q: q.value.trim() }); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); load({ q: q.value.trim() }); } });
  scheme.addEventListener('change', function () { runCheck(); if (!panel.hidden) load({}); });
  call.addEventListener('change', runCheck);
  dewey.addEventListener('change', runCheck);
})();
</script>
