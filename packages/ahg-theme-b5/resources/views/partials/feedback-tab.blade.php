{{-- Floating Feedback Tab — left edge, halfway down --}}
<div id="feedback-tab-wrap">
  {{-- The tab button --}}
  <button id="feedback-tab-btn" type="button" aria-label="Give feedback" title="Feedback">
    <i class="fas fa-comment-alt me-1"></i>Feedback
  </button>
  <button id="feedback-tab-dismiss" type="button" class="d-none" aria-label="Hide feedback" title="Hide feedback tab">
    <i class="fas fa-chevron-left"></i>
  </button>

  {{-- The slide-out panel --}}
  <div id="feedback-panel" class="d-none">
    <div class="feedback-panel-header">
      <strong><i class="fas fa-comment-alt me-1"></i>Feedback</strong>
      <button type="button" id="feedback-panel-close" class="btn-close btn-close-white btn-sm" aria-label="Close"></button>
    </div>
    <form id="feedback-panel-form" method="POST" action="{{ url('/feedback/general') }}">
      @csrf
      {{-- Hidden fields for existing controller --}}
      <input type="hidden" name="feed_name" value="{{ auth()->user()->username ?? 'Anonymous' }}">
      <input type="hidden" name="feed_surname" value="(Quick Feedback)">
      <input type="hidden" name="feed_email" value="{{ auth()->user()->email ?? 'anonymous@heratio.local' }}">
      <input type="hidden" name="feed_type_id" value="0">

      {{-- Star rating --}}
      <div class="mb-2">
        <label class="form-label small fw-bold mb-1">Rate this page</label>
        <div id="feedback-stars" class="d-flex gap-1">
          <span class="feedback-star" data-value="1"><i class="far fa-star"></i></span>
          <span class="feedback-star" data-value="2"><i class="far fa-star"></i></span>
          <span class="feedback-star" data-value="3"><i class="far fa-star"></i></span>
          <span class="feedback-star" data-value="4"><i class="far fa-star"></i></span>
          <span class="feedback-star" data-value="5"><i class="far fa-star"></i></span>
        </div>
        <input type="hidden" name="rating" id="feedback-rating" value="">
      </div>

      {{-- Category --}}
      <div class="mb-2">
        <select name="feed_type_id" class="form-select form-select-sm">
          <option value="0">General feedback</option>
          <option value="1">Bug report</option>
          <option value="2">Feature request</option>
          <option value="3">Content issue</option>
          <option value="4">Usability</option>
        </select>
      </div>

      {{-- Subject (auto-generated, hidden) --}}
      <input type="hidden" name="subject" id="feedback-subject" value="">

      {{-- Message --}}
      <div class="mb-2">
        <textarea name="remarks" class="form-control form-control-sm" rows="3" placeholder="Tell us what you think..." required></textarea>
      </div>

      <button type="submit" class="btn btn-sm atom-btn-outline-success w-100">
        <i class="fas fa-paper-plane me-1"></i>Send Feedback
      </button>

      <div id="feedback-success" class="d-none text-center mt-2">
        <i class="fas fa-check-circle text-success fa-2x mb-1 d-block"></i>
        <span class="small text-muted">Thank you for your feedback!</span>
      </div>
    </form>
  </div>
</div>

<style>
  #feedback-tab-wrap {
    position: fixed;
    left: 0;
    top: 55%;
    transform: translateY(-50%);
    z-index: 1050;
  }
  #feedback-tab-btn {
    writing-mode: vertical-rl;
    text-orientation: mixed;
    transform: rotate(180deg);
    background: var(--ahg-primary, #2c6b4f);
    color: #fff;
    border: none;
    border-radius: 0 6px 6px 0;
    padding: 12px 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 2px 0 8px rgba(0,0,0,0.15);
    transition: background 0.2s, padding 0.2s;
    letter-spacing: 0.5px;
  }
  #feedback-tab-btn:hover {
    background: var(--ahg-primary-dark, #1e4d38);
    padding: 14px 10px;
  }
  #feedback-tab-dismiss {
    position: fixed;
    left: 302px;
    top: 50%;
    transform: translateY(-50%);
    background: #dc3545;
    color: #fff;
    border: none;
    border-radius: 0 6px 6px 0;
    padding: 10px 6px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: background 0.2s;
    box-shadow: 2px 0 6px rgba(0,0,0,0.15);
    z-index: 1052;
  }
  #feedback-tab-dismiss:hover {
    background: #a71d2a;
  }
  #feedback-panel {
    position: fixed;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 300px;
    background: #fff;
    border: 1px solid #ddd;
    border-left: 4px solid var(--ahg-primary, #2c6b4f);
    border-radius: 0 8px 8px 0;
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    z-index: 1051;
    animation: feedbackSlideIn 0.2s ease-out;
  }
  @keyframes feedbackSlideIn {
    from { transform: translateY(-50%) translateX(-100%); }
    to   { transform: translateY(-50%) translateX(0); }
  }
  .feedback-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 14px;
    background: var(--ahg-primary, #2c6b4f);
    color: #fff;
    border-radius: 0 7px 0 0;
    font-size: 0.9rem;
  }
  #feedback-panel form {
    padding: 12px 14px;
  }
  .feedback-star {
    cursor: pointer;
    font-size: 1.4rem;
    color: #ccc;
    transition: color 0.15s, transform 0.15s;
  }
  .feedback-star:hover,
  .feedback-star.active {
    color: #f5a623;
    transform: scale(1.15);
  }
  .feedback-star.active i {
    font-weight: 900;
  }
  .feedback-star.active i::before {
    content: "\f005"; /* fas fa-star (solid) */
  }

  @media (max-width: 576px) {
    #feedback-panel { width: 260px; }
    #feedback-tab-btn { font-size: 0.7rem; padding: 10px 6px; }
  }
  @media print {
    #feedback-tab-wrap { display: none !important; }
  }
</style>

<script>
(function() {
  const btn = document.getElementById('feedback-tab-btn');
  const dismissBtn = document.getElementById('feedback-tab-dismiss');
  const panel = document.getElementById('feedback-panel');
  const closeBtn = document.getElementById('feedback-panel-close');
  const form = document.getElementById('feedback-panel-form');
  const stars = document.querySelectorAll('.feedback-star');
  const ratingInput = document.getElementById('feedback-rating');
  const subjectInput = document.getElementById('feedback-subject');

  // Dismiss — just close the panel and show the tab button again
  dismissBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    panel.classList.add('d-none');
    dismissBtn.classList.add('d-none');
    btn.style.display = '';
  });

  // Toggle panel — show dismiss button when panel is open
  btn.addEventListener('click', function() {
    panel.classList.toggle('d-none');
    const isOpen = !panel.classList.contains('d-none');
    btn.style.display = isOpen ? 'none' : '';
    dismissBtn.classList.toggle('d-none', !isOpen);
  });

  closeBtn.addEventListener('click', function() {
    panel.classList.add('d-none');
    dismissBtn.classList.add('d-none');
    btn.style.display = '';
  });

  // Star rating
  stars.forEach(function(star) {
    star.addEventListener('click', function() {
      const val = parseInt(this.dataset.value);
      ratingInput.value = val;
      stars.forEach(function(s) {
        const sv = parseInt(s.dataset.value);
        s.classList.toggle('active', sv <= val);
      });
    });
    star.addEventListener('mouseenter', function() {
      const val = parseInt(this.dataset.value);
      stars.forEach(function(s) {
        const sv = parseInt(s.dataset.value);
        s.style.color = sv <= val ? '#f5a623' : '';
      });
    });
    star.addEventListener('mouseleave', function() {
      stars.forEach(function(s) {
        s.style.color = s.classList.contains('active') ? '#f5a623' : '';
      });
    });
  });

  // Submit via AJAX
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    // Build subject: "Quick Feedback [★★★☆☆] — /page/url"
    const rating = ratingInput.value ? '★'.repeat(parseInt(ratingInput.value)) + '☆'.repeat(5 - parseInt(ratingInput.value)) : 'No rating';
    const pagePath = window.location.pathname + window.location.search;
    subjectInput.value = 'Quick Feedback [' + rating + '] — ' + pagePath;

    const fd = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';

    fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': fd.get('_token'), 'Accept': 'application/json' },
      body: fd,
    })
    .then(function(r) { return r.json().catch(function() { return { success: true }; }); })
    .then(function() {
      form.style.display = 'none';
      document.getElementById('feedback-success').classList.remove('d-none');
      setTimeout(function() {
        panel.classList.add('d-none');
        btn.style.display = '';
        form.style.display = '';
        form.reset();
        document.getElementById('feedback-success').classList.add('d-none');
        stars.forEach(function(s) { s.classList.remove('active'); });
        ratingInput.value = '';
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Feedback';
      }, 2000);
    })
    .catch(function() {
      submitBtn.disabled = false;
      submitBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Feedback';
      alert('Could not send feedback. Please try again.');
    });
  });
})();
</script>
