{{--
  Reusable dummy-sale demo modal. Triggered by any element with
    data-bs-toggle="modal" data-bs-target="#dummySaleModal"
  Optional data-* attributes on the trigger override the default sample data:
    data-dummy-title="Listing title"
    data-dummy-price="6500.00"
    data-dummy-currency="ZAR"
--}}
<div class="modal fade" id="dummySaleModal" tabindex="-1" aria-labelledby="dummySaleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning bg-opacity-25">
        <h5 class="modal-title" id="dummySaleModalLabel">
          <i class="fas fa-flask me-2 text-warning"></i> Demo sale (e-commerce disabled)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <div class="alert alert-warning small mb-3">
          <i class="fas fa-info-circle me-1"></i>
          E-commerce mode is currently <strong>disabled</strong>. No payment is being charged &mdash;
          this is a preview of what a real sale would look like.
        </div>

        <div id="dummySaleStage1">
          <div class="text-center py-3">
            <div class="spinner-border text-primary mb-3" role="status">
              <span class="visually-hidden">Processing&hellip;</span>
            </div>
            <p class="mb-0">Placing your order&hellip;</p>
          </div>
        </div>

        <div id="dummySaleStage2" style="display:none;">
          <div class="text-center py-3">
            <i class="fas fa-credit-card fa-3x text-info mb-3"></i>
            <p class="mb-2">Redirecting to PayFast&hellip;</p>
            <div class="progress" style="height:6px;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" style="width:60%"></div>
            </div>
          </div>
        </div>

        <div id="dummySaleStage3" style="display:none;">
          <div class="text-center py-3">
            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
            <h4 class="mb-3">Payment received</h4>
            <dl class="row text-start small mb-0">
              <dt class="col-5 text-muted">Item</dt>
              <dd class="col-7" id="dummySaleItem">Sample listing</dd>

              <dt class="col-5 text-muted">Amount</dt>
              <dd class="col-7"><strong id="dummySaleAmount">ZAR 6,500.00</strong></dd>

              <dt class="col-5 text-muted">Transaction</dt>
              <dd class="col-7"><code id="dummySaleTxn">TXN-DEMO-0001</code></dd>

              <dt class="col-5 text-muted">Status</dt>
              <dd class="col-7"><span class="badge bg-success">paid</span></dd>
            </dl>
            <p class="text-muted small mt-3 mb-0">
              In live mode, the buyer is redirected to PayFast and the marketplace listing is automatically
              marked sold once the ITN webhook confirms the payment.
            </p>
          </div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var modalEl = document.getElementById('dummySaleModal');
  if (!modalEl) return;

  modalEl.addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;
    var title = (trigger && trigger.getAttribute('data-dummy-title'))   || 'Sample listing';
    var price = (trigger && trigger.getAttribute('data-dummy-price'))   || '6500.00';
    var curr  = (trigger && trigger.getAttribute('data-dummy-currency')) || 'ZAR';
    // Optional CSS selector — when present, submit that form once Stage 3 hits.
    // Used by the cart's "Demo Sale" button to actually clear the cart and
    // create demo marketplace_transaction rows server-side.
    var submitSel = trigger && trigger.getAttribute('data-demo-submit');

    document.getElementById('dummySaleItem').textContent = title;
    document.getElementById('dummySaleAmount').textContent =
      curr + ' ' + Number(price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('dummySaleTxn').textContent =
      'TXN-DEMO-' + String(Math.floor(Math.random() * 9000) + 1000);

    document.getElementById('dummySaleStage1').style.display = 'block';
    document.getElementById('dummySaleStage2').style.display = 'none';
    document.getElementById('dummySaleStage3').style.display = 'none';

    setTimeout(function () {
      document.getElementById('dummySaleStage1').style.display = 'none';
      document.getElementById('dummySaleStage2').style.display = 'block';
    }, 900);

    setTimeout(function () {
      document.getElementById('dummySaleStage2').style.display = 'none';
      document.getElementById('dummySaleStage3').style.display = 'block';

      // Trigger any associated server-side form (e.g. cart demo checkout).
      // Wait briefly so the user sees the "Payment received" frame before
      // the page navigates.
      if (submitSel) {
        var form = document.querySelector(submitSel);
        if (form) {
          setTimeout(function () { form.submit(); }, 1800);
        }
      }
    }, 2100);
  });
})();
</script>
