<div class="container-fluid py-3">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="@php echo route('researcher.dashboard') @endphp">Researcher</a></li>
      <li class="breadcrumb-item active">New Submission</li>
    </ol>
  </nav>

  <div class="row justify-content-center">
    <div class="col-lg-8">

      <div class="card">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Submission</h5>
        </div>
        <div class="card-body">

          <p class="text-muted mb-4">
            Create a submission package to upload and describe a collection. After adding items and files,
            submit for archivist review.
          </p>

          <form method="post">

            <div class="mb-3">
              <label class="form-label fw-bold">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="title" class="form-control" required placeholder="e.g., Smith Family Papers 1950-1975">
              <small class="text-muted">A descriptive title for this submission package.</small>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Description <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="description" class="form-control" rows="3" placeholder="Brief description of the collection being submitted..."></textarea>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">Target Repository <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="repository_id" class="form-select">
                <option value="">-- Select repository --</option>
                @php foreach ($repositories as $repo): @endphp
                  <option value="@php echo $repo->id @endphp">@php echo htmlspecialchars($repo->name) @endphp</option>
                @php endforeach @endphp
              </select>
              <small class="text-muted">The archival institution where this collection will be placed.</small>
            </div>

            @if(!empty($projects))
            <div class="mb-3">
              <label class="form-label fw-bold">Linked Research Project <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="project_id" class="form-select">
                <option value="">-- None --</option>
                @php foreach ($projects as $proj): @endphp
                  <option value="@php echo $proj->id @endphp">@php echo htmlspecialchars($proj->title) @endphp (@php echo ucfirst($proj->status) @endphp)</option>
                @php endforeach @endphp
              </select>
              <small class="text-muted">Link this submission to an existing research project.</small>
            </div>
            @endif

            <div class="mb-3">
              <label class="form-label fw-bold">Parent Record (optional) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="hidden" name="parent_object_id" id="parentObjectId" value="">
              <input type="text" class="form-control" id="parentSearch" placeholder="Type to search for a parent record..." autocomplete="off">
              <small class="text-muted">Place this submission under an existing archival record. Leave blank for root level.</small>
              <div id="parentResults" class="list-group mt-1" style="display:none; position:absolute; z-index:999; max-height:200px; overflow-y:auto;"></div>
            </div>

            <hr>

            <div class="d-flex justify-content-between">
              <a href="@php echo route('researcher.dashboard') @endphp" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Cancel
              </a>
              <button type="submit" class="btn atom-btn-white">
                <i class="bi bi-check-lg me-1"></i>Create Submission
              </button>
            </div>

          </form>

        </div>
      </div>

    </div>
  </div>

</div>

<script @php echo $nattr @endphp>
(function() {
  var searchInput = document.getElementById('parentSearch');
  var hiddenInput = document.getElementById('parentObjectId');
  var resultsDiv = document.getElementById('parentResults');
  var debounceTimer = null;

  searchInput.addEventListener('input', function() {
    var q = this.value.trim();
    clearTimeout(debounceTimer);
    if (q.length < 2) { resultsDiv.style.display = 'none'; return; }

    debounceTimer = setTimeout(function() {
      fetch('/index.php/informationobject/autocomplete?query=' + encodeURIComponent(q) + '&limit=10')
        .then(function(r) { return r.json(); })
        .then(function(data) {
          var results = data.results || data;
          if (!results || results.length === 0) {
            resultsDiv.innerHTML = '<div class="list-group-item text-muted small">No results found</div>';
            resultsDiv.style.display = '';
            return;
          }
          var html = '';
          results.forEach(function(item) {
            var id = item.identifier || item.id || '';
            var title = item.title || item.name || item.label || '';
            var objectId = item.id || item.object_id || '';
            html += '<a href="#" class="list-group-item list-group-item-action small parent-result" data-id="' + objectId + '">'
              + '<strong>' + title + '</strong>'
              + (id ? ' <span class="text-muted">(' + id + ')</span>' : '')
              + '</a>';
          });
          resultsDiv.innerHTML = html;
          resultsDiv.style.display = '';
        })
        .catch(function() { resultsDiv.style.display = 'none'; });
    }, 300);
  });

  resultsDiv.addEventListener('click', function(e) {
    var item = e.target.closest('.parent-result');
    if (!item) return;
    e.preventDefault();
    hiddenInput.value = item.getAttribute('data-id');
    searchInput.value = item.textContent.trim();
    resultsDiv.style.display = 'none';
  });

  // Clear selection
  searchInput.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { resultsDiv.style.display = 'none'; }
    if (e.key === 'Backspace' && this.value === '') { hiddenInput.value = ''; }
  });

  document.addEventListener('click', function(e) {
    if (!resultsDiv.contains(e.target) && e.target !== searchInput) {
      resultsDiv.style.display = 'none';
    }
  });
})();
</script>
