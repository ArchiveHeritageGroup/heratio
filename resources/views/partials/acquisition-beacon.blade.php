{{-- H09: acquisition beacon for the demo site. Records one visit per tab session
     once a page has been visible for five seconds, plus clicks to the live demo,
     the guided tour, documentation and GitHub. Stores no IP address or cookie:
     only a random id in sessionStorage that ties a visit to its later clicks,
     and the source of the visit. --}}
@if(\App\Support\MarketingSite::is())
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var url = @json(route('acquisition.event'));
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var store = window.sessionStorage;
  function get(k) { try { return store.getItem(k); } catch (e) { return null; } }
  function put(k, v) { try { store.setItem(k, v); } catch (e) {} }

  // Where this tab session came from, decided once on its first page.
  var visit = get('acqVisit');
  if (!visit) {
    visit = Array.from(crypto.getRandomValues(new Uint8Array(8)), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
    put('acqVisit', visit);
    var ref = document.referrer || '';
    var campaign = new URLSearchParams(location.search).get('utm_source') || '';
    put('acqRef', ref);
    put('acqCampaign', campaign);
  }

  function send(event, detail) {
    var f = new FormData();
    f.append('_token', token);
    f.append('event', event);
    f.append('source', get('acqSource') || 'direct');
    f.append('path', location.pathname);
    f.append('visit', visit);
    if (detail) { f.append('detail', detail); }
    if (navigator.sendBeacon) { navigator.sendBeacon(url, f); } else { fetch(url, { method: 'POST', body: f, keepalive: true }); }
  }

  // Classify the source here so only a label is sent, never the referring address.
  // The server accepts only well-formed labels (AcquisitionController::SOURCE).
  if (!get('acqSource')) {
    var host = '', camp = (get('acqCampaign') || '').toLowerCase().replace(/[^a-z0-9._-]/g, '').slice(0, 40);
    try { host = new URL(get('acqRef') || '').hostname.replace(/^www\./, '').toLowerCase(); } catch (e) {}
    var own = location.hostname.replace(/^www\./, '');
    var src = camp ? 'campaign:' + camp
      : (!host || host === own) ? 'direct'
      : /(^|\.)github\.(com|io)$/.test(host) ? 'github'
      : /(google\.|bing\.com|duckduckgo\.com|yahoo\.|ecosia\.org|yandex\.|baidu\.com|brave\.com|startpage\.com)/.test(host) ? 'search'
      : /(^|\.)(linkedin\.com|lnkd\.in|facebook\.com|twitter\.com|t\.co|x\.com|instagram\.com|youtube\.com|reddit\.com|mastodon\.social|bsky\.app|whatsapp\.com)$/.test(host) ? 'social'
      : 'referral:' + host.slice(0, 50);
    put('acqSource', src);
  }
  window.heratioAcqSource = get('acqSource');

  // A visit: once per tab session, after five visible seconds.
  if (!get('acqVisitSent')) {
    var shown = 0, timer = setInterval(function () {
      if (document.visibilityState === 'visible') { shown += 1; }
      if (shown >= 5) { clearInterval(timer); put('acqVisitSent', '1'); send('visit'); }
    }, 1000);
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[href], [data-demo-tour-start]');
    if (!a) { return; }
    if (a.hasAttribute('data-demo-tour-start')) { send('tour'); return; }
    var href = a.getAttribute('href') || '';
    if (/github\.com\/ArchiveHeritageGroup/i.test(href)) { send('github', href.indexOf('#installation') > -1 ? 'install' : 'source'); }
    else if (/\/(glam\/browse|exhibition-space\/)/.test(href)) { send('demo', href.replace(/^https?:\/\/[^/]+/, '').slice(0, 120)); }
    else if (/\/(help\/|solutions\/)/.test(href)) { send('docs', href.replace(/^https?:\/\/[^/]+/, '').slice(0, 120)); }
  }, true);
})();
</script>
@endif
