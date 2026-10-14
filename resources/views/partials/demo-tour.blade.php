{{-- H06: floating guide card for the demo tour. Rendered on every page of the demo
     hosts, but it shows nothing until a visitor starts the tour at /solutions/tour.
     Progress lives in localStorage, so it needs no session or login. --}}
@if(\App\Support\DemoTour::enabled())
<div id="demo-tour-card" class="card shadow position-fixed d-none" role="dialog" aria-live="polite" aria-labelledby="demo-tour-title"
     style="right:1rem;bottom:1rem;max-width:22rem;z-index:1080;">
  <div class="card-body">
    <div class="small text-muted mb-1" id="demo-tour-count"></div>
    <h2 class="h6 mb-2" id="demo-tour-title"></h2>
    <p class="small mb-3" id="demo-tour-text"></p>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" data-demo-tour="back">{{ __('Back') }}</button>
      <button type="button" class="btn btn-sm btn-primary" data-demo-tour="next">{{ __('Next') }}</button>
      <button type="button" class="btn btn-sm btn-link ms-auto" data-demo-tour="exit">{{ __('End tour') }}</button>
    </div>
  </div>
</div>
<script nonce="{{ $cspNonce ?? '' }}">
(function () {
  var steps = @json(\App\Support\DemoTour::steps());
  var KEY = 'heratioDemoTour';
  var labels = {
    of: @json(__('Step :n of :total')),
    away: @json(__('Tour paused')),
    awayText: @json(__('You have left the tour page. Go back to where you were, or end the tour.')),
    resume: @json(__('Back to the tour')),
    finish: @json(__('Finish'))
  };
  function get() { try { var v = localStorage.getItem(KEY); return v === null ? null : parseInt(v, 10); } catch (e) { return null; } }
  function set(i) { try { i === null ? localStorage.removeItem(KEY) : localStorage.setItem(KEY, String(i)); } catch (e) {} }
  function go(i) { set(i); window.location.href = steps[i].path; }

  document.querySelectorAll('[data-demo-tour-start]').forEach(function (b) {
    b.addEventListener('click', function () { go(0); });
  });

  var i = get();
  if (i === null || isNaN(i) || !steps[i]) { return; }
  var card = document.getElementById('demo-tour-card');
  var here = window.location.pathname.replace(/\/+$/, '') || '/';
  var onStep = here === steps[i].path;
  var next = card.querySelector('[data-demo-tour="next"]');
  var back = card.querySelector('[data-demo-tour="back"]');

  document.getElementById('demo-tour-count').textContent = labels.of.replace(':n', i + 1).replace(':total', steps.length);
  document.getElementById('demo-tour-title').textContent = onStep ? steps[i].title : labels.away;
  document.getElementById('demo-tour-text').textContent = onStep ? steps[i].text : labels.awayText;
  if (!onStep) { next.textContent = labels.resume; }
  else if (i === steps.length - 1) { next.textContent = labels.finish; }
  back.disabled = onStep && i === 0;

  next.addEventListener('click', function () {
    if (!onStep) { go(i); } else if (i < steps.length - 1) { go(i + 1); } else { set(null); card.classList.add('d-none'); }
  });
  back.addEventListener('click', function () { go(onStep ? Math.max(0, i - 1) : i); });
  card.querySelector('[data-demo-tour="exit"]').addEventListener('click', function () { set(null); card.classList.add('d-none'); });
  card.classList.remove('d-none');

  if (onStep && steps[i].target) {
    var t = document.querySelector(steps[i].target);
    if (t) {
      t.style.outline = '3px solid #E1B34F';
      t.style.outlineOffset = '4px';
      t.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
})();
</script>
@endif
