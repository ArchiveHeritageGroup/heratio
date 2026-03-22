@if($sf_user->getAttribute('search-realm') && sfConfig::get('app_enable_institutional_scoping'))
  @php include_component('repository', 'holdingsInstitution', ['resource' => QubitRepository::getById($sf_user->getAttribute('search-realm'))]); @endphp
@php } else { @endphp
  @php echo get_component('repository', 'logo'); @endphp
@endforeach
@php echo get_component('informationobject', 'treeView'); @endphp
@php echo get_component('menu', 'staticPagesMenu'); @endphp
@php // Check if a plugin is enabled
if (!function_exists('isPluginActive')) {
    function isPluginActive($pluginName) {
        static $plugins = null;
        if ($plugins === null) {
            try {
                $pluginNames = \Illuminate\Database\Capsule\Manager::table('atom_plugin')
                    ->where('is_enabled', 1)
                    ->pluck('name')
                    ->toArray();
                $plugins = array_flip($pluginNames);
            } catch (Exception $e) {
                $plugins = [];
            }
        }
        return isset($plugins[$pluginName]);
    }
}

// Museum/CCO specific links for authenticated users
if (isset($resource)) {
  // Get raw resource to bypass sfOutputEscaperObjectDecorator (instanceof fails on wrapped objects)
  $rawResource = isset($sf_data) ? $sf_data->getRaw('resource') : $resource;
  $resourceSlug = null;
  if ($rawResource instanceof QubitInformationObject) {
    $resourceSlug = $rawResource->slug;
  } elseif (is_object($rawResource) && property_exists($rawResource, 'slug')) {
    $resourceSlug = $rawResource->slug;
  } elseif (is_object($resource) && method_exists($resource, '__call')) {
    // Fallback for escaped objects — access slug via method call
    try { $resourceSlug = $resource->slug; } catch (Exception $e) {}
  }
  
  // Check which plugins are enabled
  $hasCco = isPluginActive('ahgProvenancePlugin');
  $hasCondition = isPluginActive('ahgConditionPlugin');
  $hasSpectrum = isPluginActive('ahgSpectrumPlugin');
  $hasGrap = isPluginActive('ahgHeritageAccountingPlugin');
  $hasOais = isPluginActive('ahgPreservationPlugin');
  $hasResearch = isPluginActive('ahgResearchPlugin');
  $hasDisplay = isPluginActive('ahgDisplayPlugin');

  // Only show section if user is authenticated and at least one plugin is enabled
  if ($sf_user->isAuthenticated() && $resourceSlug && ($hasCco || $hasCondition || $hasSpectrum || $hasGrap || $hasOais || $hasResearch || $hasDisplay)) { @endphp
<section class="sidebar-widget">
  <h4>{{ __('Collections Management') }}</h4>
  <ul>
    @if($hasCco)
    <li>@php echo link_to(__('Provenance'), ['module' => 'cco', 'action' => 'provenance', 'slug' => $resourceSlug]); @endphp</li>
    @endif
    @if($hasCondition)
    <li>@php echo link_to(__('Condition assessment'), '@condition_check_by_slug?slug=' . $resourceSlug); @endphp</li>
    @endif
    @if($hasSpectrum)
    <li>@php echo link_to(__('Spectrum data'), '@spectrum_index?slug=' . $resourceSlug); @endphp</li>
    @endif
    @if($hasGrap)
    <li><a href="/index.php/heritage/add?io_id=@php echo $resource->id; @endphp">{{ __('Heritage Assets') }}</a></li>
    @endif
    @if($hasOais)
    <li>@php echo link_to(__('Digital Preservation (OAIS)'), ['module' => 'preservation', 'action' => 'packages']); @endphp</li>
    @endif
    @if($hasResearch)
    <li>@php echo link_to(__('Cite this Record'), ['module' => 'research', 'action' => 'cite', 'slug' => $resourceSlug]); @endphp</li>
    @endif
  </ul>
</section>
@php }
} @endphp
<!-- EXTENDED RIGHTS CONTEXT MENU (Only if plugin enabled) -->

<style @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
.ctx-hidden { display: none; }
.ctx-zone-scroll { max-height: 250px; overflow-y: auto; }
.ctx-prewrap { white-space: pre-wrap; }
</style>
<!-- AI Tools Section (NER, Summarize, Translate, Handwriting) -->
@if(isset($resource) && $sf_user->isAuthenticated() && isPluginActive('ahgAIPlugin'))
@php // Check for PDF and approved entities for PDF overlay feature
  $hasPdfForOverlay = false;
  $approvedEntityCount = 0;
  try {
      $hasPdfForOverlay = Illuminate\Database\Capsule\Manager::table('digital_object')
          ->where('object_id', $resource->id)
          ->where('mime_type', 'LIKE', '%pdf%')
          ->exists();
      $approvedEntityCount = Illuminate\Database\Capsule\Manager::table('ahg_ner_entity')
          ->where('object_id', $resource->id)
          ->whereIn('status', ['approved', 'linked'])
          ->count();
  } catch (Exception $e) {} @endphp
<section class="sidebar-widget">
  <h4>{{ __('AI Tools') }}</h4>
  <ul>
    <li>
      <a href="#" id="nerExtractBtn" data-object-id="@php echo $resource->id; @endphp">
        <i class="bi bi-cpu me-1"></i>{{ __('Extract Entities (NER)') }}
      </a>
    </li>
    @if($hasPdfForOverlay && $approvedEntityCount > 0)
    <li>
      <a href="@php echo url_for(['module' => 'ai', 'action' => 'pdfOverlay', 'id' => $resource->id]); @endphp">
        <i class="bi bi-file-earmark-pdf me-1"></i>{{ __('View PDF Entities') }}
        <span class="badge bg-success rounded-pill ms-1">@php echo $approvedEntityCount; @endphp</span>
      </a>
    </li>
    @endif
    <li>
      <a href="#" id="aiSummarizeBtn" data-object-id="@php echo $resource->id; @endphp">
        <i class="bi bi-file-text me-1"></i>{{ __('Generate Summary') }}
      </a>
    </li>
    @if(isPluginActive('ahgTranslationPlugin'))
    <li>@php include_partial('translation/translateModal', ['objectId' => $resource->id]); @endphp</li>
    @endif
    <li class="mt-2">
      <a href="/ner/review">
        <i class="bi bi-list-check me-1"></i>{{ __('Review Dashboard') }}
      </a>
    </li>
    @php /* Handwriting Recognition - commented out for now
    <li class="mt-2" style="list-style:none; margin-left:-1rem;"><span class="fw-semibold text-secondary">{{ __('Handwriting Recognition') }}</span></li>
    <li>
      <a href="#" onclick="extractHandwriting(<?php echo $resource->id @endphp, 'all'); return false;">
        <i class="bi bi-pencil me-1"></i>{{ __('Extract All (HTR)') }}
      </a>
    </li>
    <li>
      <a href="#" onclick="extractHandwriting(@php echo $resource->id @endphp, 'date'); return false;">
        <i class="bi bi-calendar-event me-1"></i>{{ __('Extract Dates') }}
      </a>
    </li>
    <li>
      <a href="#" onclick="extractHandwriting(@php echo $resource->id @endphp, 'digits'); return false;">
        <i class="bi bi-123 me-1"></i>{{ __('Extract Digits') }}
      </a>
    </li>
    <li>
      <a href="#" onclick="extractHandwriting(@php echo $resource->id @endphp, 'letters'); return false;">
        <i class="bi bi-alphabet me-1"></i>{{ __('Extract Letters') }}
      </a>
    </li>
    */ ?>
  </ul>
  <div id="summaryResult" class="mt-2 ctx-hidden"></div>
  <div id="htrResult" class="mt-2 ctx-hidden"></div>
</section>

<!-- NER Results Modal -->
<div class="modal fade" id="nerModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-cpu me-2"></i>Extracted Entities</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="nerModalBody"></div>
      <div class="modal-footer">
        <span id="nerProcessingTime" class="text-muted small me-auto"></span>
        <a href="/ner/review" class="btn atom-btn-white ctx-hidden" id="nerReviewBtn">Review & Link</a>
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- HTR Results Modal -->
<div class="modal fade" id="htrModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Handwriting Recognition (HTR)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="htrModalBody"></div>
      <div class="modal-footer">
        <span id="htrProcessingTime" class="text-muted small me-auto"></span>
        <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script @php $n = sfConfig::get('csp_nonce', ''); echo $n ? preg_replace('/^nonce=/', 'nonce="', $n).'"' : ''; @endphp>
function extractEntities(objectId) {
  var modal = new bootstrap.Modal(document.getElementById('nerModal'));
  modal.show();
  document.getElementById('nerModalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Extracting...</p></div>';
  document.getElementById('nerReviewBtn').classList.add('ctx-hidden');

  fetch('/ner/extract/' + objectId, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success) {
        document.getElementById('nerModalBody').innerHTML = '<div class="alert alert-danger">' + (data.error || 'Failed') + '</div>';
        return;
      }
      var html = '';
      var cfg = { PERSON: {icon:'bi-person-fill',color:'primary',label:'People'}, ORG: {icon:'bi-building',color:'success',label:'Organizations'}, GPE: {icon:'bi-geo-alt-fill',color:'info',label:'Places'}, DATE: {icon:'bi-calendar',color:'warning',label:'Dates'} };
      var total = 0;
      for (var type in data.entities) {
        var items = data.entities[type];
        if (items && items.length) {
          total += items.length;
          var c = cfg[type] || {icon:'bi-tag',color:'secondary',label:type};
          html += '<div class="mb-3"><h6 class="text-'+c.color+'"><i class="'+c.icon+' me-1"></i>'+c.label+' <span class="badge bg-'+c.color+'">'+items.length+'</span></h6><div class="d-flex flex-wrap gap-2">';
          for (var i=0; i<items.length; i++) { html += '<span class="badge bg-'+c.color+' bg-opacity-75 fs-6 fw-normal">'+items[i]+'</span>'; }
          html += '</div></div>';
        }
      }
      if (total === 0) { html = '<div class="alert alert-info">No entities found</div>'; }
      else { document.getElementById('nerReviewBtn').classList.remove('ctx-hidden'); }
      document.getElementById('nerModalBody').innerHTML = html;
      document.getElementById('nerProcessingTime').textContent = 'Found ' + total + ' entities in ' + (data.processing_time_ms||0) + 'ms';
    })
    .catch(function(err) {
      document.getElementById('nerModalBody').innerHTML = '<div class="alert alert-danger">Error: ' + err.message + '</div>';
    });
}

function generateSummary(objectId) {
  var btn = document.getElementById('aiSummarizeBtn');
  var resultDiv = document.getElementById('summaryResult');

  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';
  resultDiv.classList.add('ctx-hidden');

  fetch('/ner/summarize/' + objectId, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.innerHTML = '<i class="bi bi-file-text me-1"></i>Generate Summary';

      if (!data.success) {
        resultDiv.innerHTML = '<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i>' + (data.error || 'Failed') + '</div>';
        resultDiv.classList.remove('ctx-hidden');
        return;
      }

      var savedMsg = data.saved ? 'Saved to Scope & Content' : 'Generated (not saved)';
      resultDiv.innerHTML = '<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>' + savedMsg + '</div>' +
        '<div class="card"><div class="card-body py-2 small">' + data.summary + '</div></div>' +
        '<button class="btn btn-sm btn-outline-secondary mt-2" id="summaryRefreshBtn"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>';
      var rb = document.getElementById('summaryRefreshBtn');
      if (rb) rb.addEventListener('click', function() { location.reload(); });
      resultDiv.classList.remove('ctx-hidden');
    })
    .catch(function(err) {
      btn.innerHTML = '<i class="bi bi-file-text me-1"></i>Generate Summary';
      resultDiv.innerHTML = '<div class="alert alert-danger py-2 small">' + err.message + '</div>';
      resultDiv.classList.remove('ctx-hidden');
    });
}

// Attach event listeners (CSP-safe, no inline onclick)
document.addEventListener('DOMContentLoaded', function() {
  var nerBtn = document.getElementById('nerExtractBtn');
  if (nerBtn) {
    nerBtn.addEventListener('click', function(e) {
      e.preventDefault();
      extractEntities(nerBtn.getAttribute('data-object-id'));
    });
  }
  var sumBtn = document.getElementById('aiSummarizeBtn');
  if (sumBtn) {
    sumBtn.addEventListener('click', function(e) {
      e.preventDefault();
      generateSummary(sumBtn.getAttribute('data-object-id'));
    });
  }
  var piiBtn = document.getElementById('piiScanBtn');
  if (piiBtn) {
    piiBtn.addEventListener('click', function(e) {
      e.preventDefault();
      scanForPii(piiBtn.getAttribute('data-object-id'));
    });
  }
});

// Show alert at top of page
function showTopAlert(message, type) {
  type = type || 'danger';
  var wrapper = document.getElementById('wrapper');
  if (!wrapper) wrapper = document.querySelector('.container-xxl');
  if (!wrapper) return;

  // Remove any existing top alerts
  var existing = document.getElementById('js-top-alert');
  if (existing) existing.remove();

  var alertHtml = '<div id="js-top-alert" class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
    '<i class="bi bi-exclamation-circle me-2"></i>' + message +
    '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
    '</div>';
  wrapper.insertAdjacentHTML('afterbegin', alertHtml);

  // Scroll to top to show the alert
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function extractHandwriting(objectId, mode) {
  var modal = new bootstrap.Modal(document.getElementById('htrModal'));
  var modalBody = document.getElementById('htrModalBody');
  var modeLabels = {
    'all': 'Extract All (HTR)',
    'date': 'Extract Dates',
    'digits': 'Extract Digits',
    'letters': 'Extract Letters',
    'stub': 'Test (Stub)'
  };

  modal.show();
  modalBody.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-info"></div><p class="mt-2">' + modeLabels[mode] + '...</p><p class="small text-muted">Using zone detection for better accuracy</p></div>';
  document.getElementById('htrProcessingTime').textContent = '';

  fetch('/ner/htr/' + objectId, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ mode: mode, use_zones: true })
  })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.success) {
        modalBody.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>' + (data.error || 'HTR extraction failed') + '</div>';
        return;
      }

      var html = '';
      var zonesDetected = data.zones_detected || 0;
      var resultCount = data.count || 0;

      // Show zone detection status
      if (data.use_zones && zonesDetected > 0) {
        html += '<div class="alert alert-success"><i class="bi bi-grid-3x3 me-2"></i>Detected ' + zonesDetected + ' text zone(s) - extracted ' + resultCount + ' item(s)</div>';
      } else if (resultCount > 0) {
        html += '<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>Extracted ' + resultCount + ' item(s)</div>';
      } else {
        html += '<div class="alert alert-warning"><i class="bi bi-exclamation-circle me-2"></i>No text detected</div>';
      }

      // Show zone details if available
      if (data.zones && data.zones.length > 0) {
        html += '<div class="mb-3">';
        html += '<h6 class="text-info"><i class="bi bi-bounding-box me-1"></i>Detected Zones:</h6>';
        html += '<div class="border rounded p-2 bg-light ctx-zone-scroll">';
        html += '<table class="table table-sm table-striped mb-0">';
        html += '<thead><tr><th>#</th><th>Text</th><th>Position</th></tr></thead><tbody>';
        for (var i = 0; i < data.zones.length; i++) {
          var zone = data.zones[i];
          var bbox = zone.bbox || {};
          html += '<tr>';
          html += '<td><span class="badge bg-secondary">' + (zone.zone_id + 1) + '</span></td>';
          html += '<td><strong>' + (zone.text || 'N/A') + '</strong></td>';
          html += '<td class="small text-muted">x:' + (bbox.x || 0) + ' y:' + (bbox.y || 0) + ' ' + (bbox.w || 0) + 'x' + (bbox.h || 0) + '</td>';
          html += '</tr>';
        }
        html += '</tbody></table></div></div>';
      }

      // Show combined results as badges
      if (data.results && data.results.length > 0) {
        html += '<div class="mb-3">';
        html += '<h6 class="text-primary"><i class="bi bi-list-ul me-1"></i>Extracted Text:</h6>';
        html += '<div class="d-flex flex-wrap gap-2">';
        for (var j = 0; j < data.results.length; j++) {
          html += '<span class="badge bg-info bg-opacity-75 fs-6 fw-normal">' + data.results[j] + '</span>';
        }
        html += '</div></div>';
      }

      // Show full combined text
      if (data.text) {
        html += '<div class="mb-3">';
        html += '<h6 class="text-secondary"><i class="bi bi-file-text me-1"></i>Combined Text:</h6>';
        html += '<div class="border rounded p-2 bg-light"><pre class="mb-0 ctx-prewrap">' + data.text + '</pre></div>';
        html += '</div>';
      }

      // Show image path for debugging
      if (data.image_path) {
        html += '<div class="small text-muted"><i class="bi bi-image me-1"></i><strong>Image:</strong> ' + data.image_path + '</div>';
      }

      modalBody.innerHTML = html;

      var timeInfo = 'Processed in ' + (data.processing_time_ms || 0) + 'ms';
      if (data.use_zones) timeInfo += ' (zone detection enabled)';
      document.getElementById('htrProcessingTime').textContent = timeInfo;
    })
    .catch(function(err) {
      modalBody.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i>' + (err.message || 'HTR extraction failed') + '</div>';
    });
}

function scanForPii(objectId) {
  var btn = document.getElementById('piiScanBtn');
  var resultDiv = document.getElementById('piiResult');

  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Scanning...';
  resultDiv.classList.add('ctx-hidden');

  // Use NER extract endpoint - PII entities are PERSON type
  fetch('/ner/extract/' + objectId, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.innerHTML = '<i class="bi bi-shield-exclamation me-1"></i>Scan for PII';

      if (!data.success) {
        resultDiv.innerHTML = '<div class="alert alert-danger py-2 small">' + (data.error || 'Scan failed') + '</div>';
        resultDiv.classList.remove('ctx-hidden');
        return;
      }

      // Check for PII-relevant entities (PERSON, EMAIL, PHONE, ID numbers)
      var piiTypes = ['PERSON', 'EMAIL', 'PHONE', 'CARDINAL', 'NORP'];
      var piiFound = [];
      var piiCount = 0;

      for (var type in data.entities) {
        if (piiTypes.indexOf(type) !== -1 && data.entities[type] && data.entities[type].length > 0) {
          piiCount += data.entities[type].length;
          piiFound.push({type: type, items: data.entities[type]});
        }
      }

      if (piiCount === 0) {
        resultDiv.innerHTML = '<div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>No PII detected</div>';
      } else {
        var html = '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><strong>' + piiCount + ' potential PII items found</strong></div>';
        html += '<div class="small">';
        for (var i = 0; i < piiFound.length; i++) {
          html += '<div class="mb-1"><strong>' + piiFound[i].type + ':</strong> ';
          html += piiFound[i].items.slice(0, 5).join(', ');
          if (piiFound[i].items.length > 5) html += '...';
          html += '</div>';
        }
        html += '</div>';
        html += '<a href="/admin/privacy/redaction/' + objectId + '" class="btn btn-sm atom-btn-outline-warning mt-2"><i class="bi bi-eraser me-1"></i>Review & Redact</a>';
        resultDiv.innerHTML = html;
      }
      resultDiv.classList.remove('ctx-hidden');
    })
    .catch(function(err) {
      btn.innerHTML = '<i class="bi bi-shield-exclamation me-1"></i>Scan for PII';
      resultDiv.innerHTML = '<div class="alert alert-danger py-2 small">' + err.message + '</div>';
      resultDiv.classList.remove('ctx-hidden');
    });
}

</script>
@endif

<!-- Privacy & PII Section -->
@if(isset($resource) && $sf_user->isAuthenticated() && isPluginActive('ahgPrivacyPlugin'))
<section class="sidebar-widget">
  <h4>{{ __('Privacy & PII') }}</h4>
  <ul>
    <li>
      <a href="#" id="piiScanBtn" data-object-id="@php echo $resource->id; @endphp">
        <i class="bi bi-shield-exclamation me-1"></i>{{ __('Scan for PII') }}
      </a>
    </li>
    @if($resource->digitalObjectsRelatedByobjectId->count() > 0)
    <li>
      @php echo link_to('<i class="bi bi-eraser me-1"></i>' . __('Visual Redaction'), ['module' => 'privacyAdmin', 'action' => 'visualRedactionEditor', 'id' => $resource->id]); @endphp
    </li>
    @endif
    <li>
      <a href="/privacy">
        <i class="bi bi-clipboard-check me-1"></i>{{ __('Privacy Dashboard') }}
      </a>
    </li>
  </ul>
  <div id="piiResult" class="mt-2 ctx-hidden"></div>
</section>
@endif

@if(isPluginActive('ahgExtendedRightsPlugin'))
@php include_partial('informationobject/extendedRightsContextMenu', ['resource' => $resource]); @endphp
@endif

@if(isPluginActive('ahgResearchPlugin'))
@php include_partial('informationobject/researchToolsContextMenu', ['resource' => $resource]); @endphp
@endif
