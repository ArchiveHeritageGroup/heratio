<div class="container-fluid py-3">

  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
      <li class="breadcrumb-item"><a href="@php echo route('researcher.dashboard') @endphp">Researcher</a></li>
      <li class="breadcrumb-item"><a href="{{ route('researcher.viewSubmission', ['id' => $submissionId]) }}">@php echo htmlspecialchars($submission->title) @endphp</a></li>
      <li class="breadcrumb-item active">@php echo $item ? 'Edit Item' : 'Add Item' @endphp</li>
    </ol>
  </nav>

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">@php echo session('success') @endphp<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <form method="post">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">
        <i class="bi bi-@php echo $item ? 'pencil' : 'plus-circle' @endphp me-2"></i>
        @php echo $item ? 'Edit Item' : 'Add Item' @endphp
      </h4>
      <div>
        <a href="{{ route('researcher.viewSubmission', ['id' => $submissionId]) }}"
           class="btn btn-outline-secondary me-1">
          <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <button type="submit" class="btn atom-btn-white">
          <i class="bi bi-check-lg me-1"></i>Save
        </button>
      </div>
    </div>

    <div class="row">
      <div class="col-lg-8">

        <!-- Item Type -->
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0">Item Type</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">Type <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <select name="item_type" class="form-select" id="itemType">
                  <option value="description" @php echo ($item->item_type ?? 'description') === 'description' ? 'selected' : '' @endphp>Description (ISAD(G))</option>
                  <option value="note" @php echo ($item->item_type ?? '') === 'note' ? 'selected' : '' @endphp>Research Note</option>
                  <option value="creator" @php echo ($item->item_type ?? '') === 'creator' ? 'selected' : '' @endphp>New Creator</option>
                  <option value="repository" @php echo ($item->item_type ?? '') === 'repository' ? 'selected' : '' @endphp>New Repository</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Parent Item <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="parent_item_id" class="form-select">
                  <option value="">-- Root level --</option>
                  @php foreach ($items as $parentItem): @endphp
                    @if((!$item || (int) $parentItem->id !== (int) $item->id) && $parentItem->item_type === 'description')
                      <option value="@php echo $parentItem->id @endphp"
                        @php echo ($item && (int) ($item->parent_item_id ?? 0) === (int) $parentItem->id) ? 'selected' : '' @endphp>
                        @php echo htmlspecialchars($parentItem->title) @endphp
                        (@php echo $parentItem->level_of_description @endphp)
                      </option>
                    @endif
                  @php endforeach @endphp
                </select>
                <small class="text-muted">Place this item under an existing item for hierarchy.</small>
              </div>
            </div>
          </div>
        </div>

        <!-- Identity Area (ISAD(G) 3.1) -->
        <div class="card mb-3" id="sectionIdentity">
          <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="bi bi-card-heading me-2"></i>Identity Area</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label fw-bold">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
                <input type="text" name="title" class="form-control" required value="@php echo htmlspecialchars($item->title ?? '') @endphp">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Identifier <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="identifier" class="form-control" value="@php echo htmlspecialchars($item->identifier ?? '') @endphp" placeholder="e.g., MS-2024-001">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Level of Description <span class="badge bg-warning ms-1">Recommended</span></label>
                <select name="level_of_description" class="form-select">
                  @php $levels = ['fonds', 'subfonds', 'collection', 'series', 'subseries', 'file', 'item'];
                    foreach ($levels as $level): @endphp
                    <option value="@php echo $level @endphp" @php echo ($item->level_of_description ?? 'item') === $level ? 'selected' : '' @endphp>
                      @php echo ucfirst($level) @endphp
                    </option>
                  @php endforeach @endphp
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Date (display) <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="date_display" class="form-control" value="@php echo htmlspecialchars($item->date_display ?? '') @endphp" placeholder="e.g., 1950-1975">
              </div>
              <div class="col-md-4">
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label fw-bold">Start Date <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="date" name="date_start" class="form-control" value="@php echo $item->date_start ?? '' @endphp">
                  </div>
                  <div class="col-6">
                    <label class="form-label fw-bold">End Date <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="date" name="date_end" class="form-control" value="@php echo $item->date_end ?? '' @endphp">
                  </div>
                </div>
              </div>
              <div class="col-12">
                <label class="form-label fw-bold">Extent and Medium <span class="badge bg-warning ms-1">Recommended</span></label>
                <input type="text" name="extent_and_medium" class="form-control" value="@php echo htmlspecialchars($item->extent_and_medium ?? '') @endphp" placeholder="e.g., 3 boxes, 150 photographs">
              </div>
            </div>
          </div>
        </div>

        <!-- Content Area (ISAD(G) 3.3) -->
        <div class="card mb-3" id="sectionContent">
          <div class="card-header"><h6 class="mb-0"><i class="bi bi-file-text me-2"></i>Content and Structure</h6></div>
          <div class="card-body">
            <div class="mb-3">
              <label class="form-label fw-bold">Scope and Content <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="scope_and_content" class="form-control" rows="4">@php echo htmlspecialchars($item->scope_and_content ?? '') @endphp</textarea>
            </div>
          </div>
        </div>

        <!-- Access Points -->
        <div class="card mb-3" id="sectionAccessPoints">
          <div class="card-header bg-info text-white"><h6 class="mb-0"><i class="bi bi-tags me-2"></i>Access Points</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">Creators <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="hidden" name="creators" id="creatorsValue" value="@php echo htmlspecialchars($item->creators ?? '') @endphp">
                <div class="tag-container border rounded p-1 d-flex flex-wrap gap-1 mb-1" id="creatorsTags"></div>
                <input type="text" class="form-control form-control-sm tag-autocomplete" id="creatorsInput"
                       data-target="creators" data-source="actor" placeholder="Type to search creators...">
                <small class="text-muted">Persons, organizations, families.</small>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Subjects <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="hidden" name="subjects" id="subjectsValue" value="@php echo htmlspecialchars($item->subjects ?? '') @endphp">
                <div class="tag-container border rounded p-1 d-flex flex-wrap gap-1 mb-1" id="subjectsTags"></div>
                <input type="text" class="form-control form-control-sm tag-autocomplete" id="subjectsInput"
                       data-target="subjects" data-source="term" data-taxonomy="35" placeholder="Type to search subjects...">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Places <span class="badge bg-warning ms-1">Recommended</span></label>
                <input type="hidden" name="places" id="placesValue" value="@php echo htmlspecialchars($item->places ?? '') @endphp">
                <div class="tag-container border rounded p-1 d-flex flex-wrap gap-1 mb-1" id="placesTags"></div>
                <input type="text" class="form-control form-control-sm tag-autocomplete" id="placesInput"
                       data-target="places" data-source="term" data-taxonomy="42" placeholder="Type to search places...">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Genre <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="hidden" name="genres" id="genresValue" value="@php echo htmlspecialchars($item->genres ?? '') @endphp">
                <div class="tag-container border rounded p-1 d-flex flex-wrap gap-1 mb-1" id="genresTags"></div>
                <input type="text" class="form-control form-control-sm tag-autocomplete" id="genresInput"
                       data-target="genres" data-source="term" data-taxonomy="78" placeholder="Type to search genre...">
              </div>
            </div>
          </div>
        </div>

        <!-- Conditions Area (ISAD(G) 3.4) -->
        <div class="card mb-3" id="sectionConditions">
          <div class="card-header"><h6 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Conditions of Access and Use</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-bold">Conditions Governing Access <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="access_conditions" class="form-control" rows="2">@php echo htmlspecialchars($item->access_conditions ?? '') @endphp</textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-bold">Conditions Governing Reproduction <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="reproduction_conditions" class="form-control" rows="2">@php echo htmlspecialchars($item->reproduction_conditions ?? '') @endphp</textarea>
              </div>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0"><i class="bi bi-sticky me-2"></i>Notes</h6></div>
          <div class="card-body">
            <textarea name="notes" class="form-control" rows="3">@php echo htmlspecialchars($item->notes ?? '') @endphp</textarea>
          </div>
        </div>

        <!-- Repository fields (shown when item_type = repository) -->
        <div class="card mb-3" id="sectionRepository" style="display:none;">
          <div class="card-header bg-warning"><h6 class="mb-0"><i class="bi bi-building me-2"></i>Repository Details</h6></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-12">
                <label class="form-label fw-bold">Repository Name <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="repository_name" class="form-control" value="@php echo htmlspecialchars($item->repository_name ?? '') @endphp">
              </div>
              <div class="col-md-8">
                <label class="form-label fw-bold">Address <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="repository_address" class="form-control" rows="2">@php echo htmlspecialchars($item->repository_address ?? '') @endphp</textarea>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-bold">Contact <span class="badge bg-secondary ms-1">Optional</span></label>
                <input type="text" name="repository_contact" class="form-control" value="@php echo htmlspecialchars($item->repository_contact ?? '') @endphp" placeholder="Email or phone">
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- Sidebar: Files -->
      <div class="col-lg-4">

        @if($item)
        <div class="card mb-3 sticky-top" style="top: 1rem;">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="bi bi-paperclip me-2"></i>Files (@php echo count($itemFiles) @endphp)</h6>
          </div>
          <div class="card-body">
            <!-- Upload zone -->
            @if(in_array($submission->status, ['draft', 'returned']))
            <div class="mb-3">
              <input type="file" id="fileUpload" class="form-control form-control-sm" multiple>
              <small class="text-muted">Drop files or click to upload.</small>
              <div id="uploadProgress" class="mt-2"></div>
            </div>
            @endif

            <!-- File list -->
            <div id="fileList">
              @if(empty($itemFiles))
                <p class="text-muted small mb-0" id="noFilesMsg">No files attached.</p>
              @endif
              @php foreach ($itemFiles as $f): @endphp
                <div class="d-flex justify-content-between align-items-center mb-2 file-entry" data-id="@php echo $f->id @endphp">
                  <div class="text-truncate me-2">
                    <i class="bi bi-file-earmark me-1"></i>
                    <small>@php echo htmlspecialchars($f->original_name) @endphp</small>
                    <br><small class="text-muted">@php echo round($f->file_size / 1024, 1) @endphp KB</small>
                  </div>
                  @if(in_array($submission->status, ['draft', 'returned']))
                    <button type="button" class="btn btn-sm btn-outline-danger delete-file-btn" data-file-id="@php echo $f->id @endphp">
                      <i class="bi bi-trash"></i>
                    </button>
                  @endif
                </div>
              @php endforeach @endphp
            </div>
          </div>
        </div>
        @else
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Save the item first, then you can upload files.
          </div>
        @endif

      </div>
    </div>
  </form>

</div>

@if($item)
<script @php echo $nattr @endphp>
(function() {
  // Toggle sections based on item type
  var typeSelect = document.getElementById('itemType');
  function toggleSections() {
    var type = typeSelect.value;
    var show = function(id, vis) { var el = document.getElementById(id); if(el) el.style.display = vis ? '' : 'none'; };
    show('sectionIdentity', type === 'description' || type === 'note');
    show('sectionContent', true);
    show('sectionAccessPoints', type === 'description');
    show('sectionConditions', type === 'description');
    show('sectionRepository', type === 'repository');
  }
  typeSelect.addEventListener('change', toggleSections);
  toggleSections();

  // AJAX file upload
  var fileInput = document.getElementById('fileUpload');
  if (fileInput) {
    fileInput.addEventListener('change', function() {
      var files = this.files;
      for (var i = 0; i < files.length; i++) {
        uploadFile(files[i]);
      }
      this.value = '';
    });
  }

  function uploadFile(file) {
    var fd = new FormData();
    fd.append('file', file);
    fd.append('item_id', '@php echo $item->id @endphp');

    var prog = document.getElementById('uploadProgress');
    prog.innerHTML = '<small class="text-muted">Uploading ' + file.name + '...</small>';

    fetch('@php echo route('researcher.apiUpload') @endphp', {
      method: 'POST',
      body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      prog.innerHTML = '';
      if (data.success) {
        addFileEntry(data.file);
        var noMsg = document.getElementById('noFilesMsg');
        if (noMsg) noMsg.remove();
      } else {
        prog.innerHTML = '<small class="text-danger">' + (data.error || 'Upload failed') + '</small>';
      }
    })
    .catch(function() {
      prog.innerHTML = '<small class="text-danger">Upload error</small>';
    });
  }

  function addFileEntry(f) {
    var html = '<div class="d-flex justify-content-between align-items-center mb-2 file-entry" data-id="' + f.id + '">'
      + '<div class="text-truncate me-2"><i class="bi bi-file-earmark me-1"></i><small>' + f.original_name + '</small>'
      + '<br><small class="text-muted">' + Math.round(f.file_size / 1024 * 10) / 10 + ' KB</small></div>'
      + '<button type="button" class="btn btn-sm btn-outline-danger delete-file-btn" data-file-id="' + f.id + '"><i class="bi bi-trash"></i></button>'
      + '</div>';
    document.getElementById('fileList').insertAdjacentHTML('beforeend', html);
  }

  // Delete file handler (delegated)
  document.getElementById('fileList').addEventListener('click', function(e) {
    var btn = e.target.closest('.delete-file-btn');
    if (!btn) return;
    e.preventDefault();
    if (!confirm('Delete this file?')) return;

    var fileId = btn.getAttribute('data-file-id');
    fetch('@php echo route('researcher.apiDeleteFile') @endphp?file_id=' + fileId, {
      method: 'POST'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        var entry = btn.closest('.file-entry');
        if (entry) entry.remove();
      }
    });
  });

  // ─── TAG AUTOCOMPLETE ───────────────────────────────────────
  var acUrl = '@php echo route('researcher.apiAutocomplete') @endphp';

  // Initialize all tag fields
  ['creators', 'subjects', 'places', 'genres'].forEach(function(field) {
    var input = document.getElementById(field + 'Input');
    var hidden = document.getElementById(field + 'Value');
    var tagsContainer = document.getElementById(field + 'Tags');
    if (!input || !hidden || !tagsContainer) return;

    // Render existing tags
    var existingValues = (hidden.value || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
    existingValues.forEach(function(val) { addTag(tagsContainer, hidden, val); });

    var debounce = null;
    var dropdown = document.createElement('div');
    dropdown.className = 'list-group mt-1';
    dropdown.style.cssText = 'display:none; position:absolute; z-index:999; max-height:200px; overflow-y:auto; width:100%;';
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(dropdown);

    input.addEventListener('input', function() {
      var q = this.value.trim();
      clearTimeout(debounce);
      if (q.length < 1) { dropdown.style.display = 'none'; return; }

      debounce = setTimeout(function() {
        var src = input.getAttribute('data-source');
        var tax = input.getAttribute('data-taxonomy') || '';
        var url = acUrl + '?query=' + encodeURIComponent(q) + '&source=' + src + '&taxonomy=' + tax + '&limit=10';

        fetch(url)
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (!data || data.length === 0) {
              dropdown.innerHTML = '<div class="list-group-item text-muted small py-1">No results. Press Enter to add "' + q + '"</div>';
              dropdown.style.display = '';
              return;
            }
            var html = '';
            data.forEach(function(item) {
              html += '<a href="#" class="list-group-item list-group-item-action small py-1 ac-result" data-name="' + escHtml(item.name) + '">'
                + escHtml(item.name) + '</a>';
            });
            dropdown.innerHTML = html;
            dropdown.style.display = '';
          })
          .catch(function() { dropdown.style.display = 'none'; });
      }, 250);
    });

    // Select from dropdown
    dropdown.addEventListener('click', function(e) {
      var item = e.target.closest('.ac-result');
      if (!item) return;
      e.preventDefault();
      var name = item.getAttribute('data-name');
      addTag(tagsContainer, hidden, name);
      input.value = '';
      dropdown.style.display = 'none';
    });

    // Enter key to add custom value
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var val = this.value.trim();
        if (val) {
          addTag(tagsContainer, hidden, val);
          this.value = '';
          dropdown.style.display = 'none';
        }
      }
      if (e.key === 'Escape') { dropdown.style.display = 'none'; }
    });

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
      if (!dropdown.contains(e.target) && e.target !== input) {
        dropdown.style.display = 'none';
      }
    });
  });

  function addTag(container, hidden, name) {
    // Prevent duplicates
    var current = getTagValues(hidden);
    if (current.indexOf(name) !== -1) return;

    var tag = document.createElement('span');
    tag.className = 'badge bg-secondary d-flex align-items-center gap-1';
    tag.innerHTML = escHtml(name) + ' <button type="button" class="btn-close btn-close-white" style="font-size:0.5rem;" aria-label="Remove"></button>';
    tag.querySelector('.btn-close').addEventListener('click', function() {
      tag.remove();
      syncHidden(container, hidden);
    });
    container.appendChild(tag);
    syncHidden(container, hidden);
  }

  function syncHidden(container, hidden) {
    var tags = container.querySelectorAll('.badge');
    var values = [];
    tags.forEach(function(t) {
      // Get text without the close button
      var clone = t.cloneNode(true);
      var btn = clone.querySelector('.btn-close');
      if (btn) btn.remove();
      values.push(clone.textContent.trim());
    });
    hidden.value = values.join(', ');
  }

  function getTagValues(hidden) {
    return (hidden.value || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
  }

  function escHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }
})();
</script>
@endif
