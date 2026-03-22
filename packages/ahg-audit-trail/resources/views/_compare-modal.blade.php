{{-- Partial: Audit compare modal --}}
<div class="modal fade" id="auditCompareModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header" style="background:var(--ahg-primary);color:#fff"><h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Change Comparison</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="mb-3 p-3 bg-light rounded border"><div class="row"><div class="col-md-4"><strong>Record:</strong> <span id="compareEntityTitle">-</span></div><div class="col-md-4"><strong>Changed by:</strong> <span id="compareUsername">-</span></div><div class="col-md-4"><strong>Date:</strong> <span id="compareDate">-</span></div></div></div>
      <div class="row g-3">
        <div class="col-md-6"><div class="card border-danger h-100"><div class="card-header bg-danger text-white py-2"><i class="fas fa-minus-circle me-2"></i>Before</div><div class="card-body"><pre id="compareOldValues" class="bg-light p-3 rounded small mb-0">-</pre></div></div></div>
        <div class="col-md-6"><div class="card border-success h-100"><div class="card-header bg-success text-white py-2"><i class="fas fa-plus-circle me-2"></i>After</div><div class="card-body"><pre id="compareNewValues" class="bg-light p-3 rounded small mb-0">-</pre></div></div></div>
      </div>
    </div>
  </div></div>
</div>
