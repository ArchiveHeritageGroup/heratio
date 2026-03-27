@extends('theme::layouts.1col')

@section('title', 'FS Overlay Annotate')
@section('body-class', 'admin ai htr')

@push('css')
<style>
  .ba-wrap { position: relative; overflow: scroll; background: #1a1a2e; border-radius: 4px; height: 75vh; }
  .ba-wrap.tool-hand { cursor: grab; }
  .ba-wrap.tool-hand.panning { cursor: grabbing; }
  .ba-wrap.tool-draw { cursor: crosshair; }
  .ba-wrap.tool-select { cursor: default; }
  .ba-wrap canvas { display: block; }
  .ba-sidebar { max-height: 75vh; overflow-y: auto; }
  .ba-field { padding: 6px 10px; border-left: 3px solid #ccc; margin-bottom: 4px; cursor: pointer; }
  .ba-field.active { border-left-color: #0d6efd; background: #e8f0fe; }
  .ba-field.done { border-left-color: #198754; background: #d1e7dd; }
  .ba-field .ba-label { font-weight: 600; font-size: 0.8rem; color: #666; }
  .ba-field .ba-value { font-size: 0.95rem; }
  .ba-field .ba-coords { font-size: 0.7rem; color: #999; }
  .ba-field.skipped { border-left-color: #adb5bd; background: #f8f9fa; opacity: 0.6; }
  .ba-field.skipped .ba-value { text-decoration: line-through; }
  .ba-field .ba-skip-btn { float: right; font-size: 0.7rem; padding: 0 6px; }
  .ba-field .ba-edit-input { font-size: 0.85rem; padding: 2px 4px; width: 100%; border: 1px solid #dee2e6; border-radius: 3px; }
  .ba-field .ba-edit-input:focus { border-color: #0d6efd; outline: none; }
  .ba-wrap.dragging { cursor: move !important; }
  .ba-progress { height: 4px; }
  kbd { font-size: 0.75rem; }
  .ba-auto-badge { display: inline-block; font-size: 0.65rem; font-weight: 700; background: #198754; color: #fff; padding: 1px 8px; border-radius: 3px; animation: baPulse 1.5s ease-in-out infinite; }
  @keyframes baPulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-0"><i class="fas fa-layer-group me-2"></i>FS Overlay Annotate</h1>
    <span class="small text-muted">Position field labels on document images — drag boxes to correct locations</span>
  </div>
  <div class="btn-group btn-group-sm">
    <a href="{{ route('admin.ai.htr.bulkAnnotate') }}" class="btn atom-btn-white"><i class="fas fa-th me-1"></i>Bulk Annotate</a>
    <a href="{{ route('admin.ai.htr.annotate') }}" class="btn atom-btn-white"><i class="fas fa-pencil-alt me-1"></i>Manual</a>
  </div>
</div>

{{-- Folder + Spreadsheet Selection --}}
<div class="card mb-3">
  <div class="card-body py-2">
    <div class="row align-items-end">
      <div class="col-md-5">
        <label class="form-label small fw-bold">Folder with images + CSV</label>
        <input type="text" id="ba-folder" class="form-control form-control-sm" value="/usr/share/nginx/heratio/FamilySearch/" placeholder="/path/to/images">
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-bold">Spreadsheet <span class="text-muted fw-normal">(optional)</span></label>
        <select id="ba-spreadsheet" class="form-select form-select-sm">
          <option value="__none__">No spreadsheet (images only)</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold">Form type</label>
        <select id="ba-form-type" class="form-select form-select-sm">
          <option value="auto">Auto-detect</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-bold">&nbsp;</label>
        <button id="ba-load-btn" class="btn btn-sm atom-btn-outline-success w-100" onclick="loadBulkData()">
          <i class="fas fa-upload me-1"></i>Load
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Main workspace --}}
<div class="row g-3" id="ba-workspace" style="display:none;">
  {{-- Image canvas --}}
  <div class="col-md-8">
    <div class="card">
      <div class="card-header py-1 d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <span id="ba-image-name" class="small">No image loaded</span>
        <span>
          <span id="ba-counter" class="badge bg-light text-dark me-2">0/0</span>
          <span id="ba-auto-status" class="ba-auto-badge d-none">AUTO</span>
          <div class="btn-group btn-group-sm me-2">
            <button class="btn btn-light" id="ba-tool-hand" title="Pan (H)" onclick="baSetTool('hand')"><i class="fas fa-hand-paper"></i></button>
            <button class="btn btn-light" id="ba-tool-draw" title="Draw (R)" onclick="baSetTool('draw')"><i class="fas fa-vector-square"></i></button>
            <button class="btn btn-light" id="ba-tool-select" title="Select/Move (V)" onclick="baSetTool('select')"><i class="fas fa-mouse-pointer"></i></button>
          </div>
          <button class="btn btn-sm btn-light" onclick="baZoomIn()"><i class="fas fa-search-plus"></i></button>
          <button class="btn btn-sm btn-light" onclick="baZoomOut()"><i class="fas fa-search-minus"></i></button>
          <button class="btn btn-sm btn-light" onclick="baZoomFit()"><i class="fas fa-expand"></i></button>
        </span>
      </div>
      <div class="ba-wrap" id="ba-wrap">
        <canvas id="ba-canvas"></canvas>
      </div>
    </div>
    <div class="d-flex justify-content-between mt-2">
      <button class="btn btn-sm atom-btn-white" onclick="baPrev()" id="ba-prev-btn" disabled><i class="fas fa-arrow-left me-1"></i>Previous</button>
      <div>
        <div class="form-check form-switch d-inline-block me-1" style="vertical-align:middle">
          <input class="form-check-input" type="checkbox" id="ba-auto-detect" checked style="cursor:pointer">
          <label class="form-check-label small" for="ba-auto-detect" style="cursor:pointer" title="Auto-detect printed labels on each image">Detect</label>
        </div>
        <div class="form-check form-switch d-inline-block me-1" style="vertical-align:middle">
          <input class="form-check-input" type="checkbox" id="ba-auto-recog" onchange="baToggleAutoRecog(this.checked)" style="cursor:pointer">
          <label class="form-check-label small" for="ba-auto-recog" style="cursor:pointer">Auto</label>
        </div>
        <button class="btn btn-sm btn-outline-danger" onclick="baRecognise()" id="ba-recognise-btn" title="HTR: recognise text in drawn boxes"><i class="fas fa-brain me-1"></i>Recognise</button>
        <button class="btn btn-sm btn-outline-info" onclick="ocrAndPlace(images[imgIdx]); redraw();" title="OCR the form to detect printed labels"><i class="fas fa-eye me-1"></i>Detect labels</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="baStartCropDraw()" id="ba-crop-draw-btn" title="Draw crop rectangle"><i class="fas fa-crop-alt me-1"></i>Mark area</button>
        <button class="btn btn-sm btn-outline-dark d-none" onclick="baDoCrop()" id="ba-crop-do-btn" title="Crop to marked area"><i class="fas fa-cut me-1"></i>Crop now</button>
        <button class="btn btn-sm btn-outline-primary" onclick="baAutoPlace()" id="ba-autoplace-btn" title="Re-apply saved positions"><i class="fas fa-magic me-1"></i>Auto-place</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="baResetPositions()" title="Clear saved positions"><i class="fas fa-undo me-1"></i>Reset</button>
        <button class="btn btn-sm btn-outline-warning" onclick="baMigrateToServer()" id="ba-migrate-btn" title="Push browser positions to server"><i class="fas fa-cloud-upload-alt me-1"></i>Sync to server</button>
        <button class="btn btn-sm atom-btn-white" onclick="baSkip()"><i class="fas fa-forward me-1"></i>Skip</button>
      </div>
      <button class="btn btn-sm atom-btn-outline-success" onclick="baSaveAndNext()" id="ba-save-btn" disabled><i class="fas fa-save me-1"></i>Save & Next</button>
    </div>
  </div>

  {{-- Field list sidebar --}}
  <div class="col-md-4">
    <div class="card">
      <div class="card-header py-1" style="background:var(--ahg-primary);color:#fff">
        <span class="small">Fields — drag boxes to position on image</span>
      </div>
      <div class="card-body p-2 ba-sidebar" id="ba-fields"></div>
    </div>
    <div class="progress ba-progress mt-2">
      <div class="progress-bar bg-success" id="ba-progress" style="width:0%"></div>
    </div>
    <div class="mt-2 small text-muted">
      <kbd>V</kbd> select & drag boxes to correct positions · Positions are <strong>remembered</strong> for next images · <kbd>R</kbd> draw new box · <kbd>Ctrl+S</kbd> save & next
    </div>

    {{-- Session stats --}}
    <div class="card mt-3">
      <div class="card-header py-1" style="background:var(--ahg-primary);color:#fff">
        <span class="small">Session</span>
      </div>
      <div class="card-body p-2">
        <div class="d-flex justify-content-between">
          <span>Images done</span>
          <span class="badge bg-success" id="ba-done-count">0</span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span>Remaining</span>
          <span class="badge bg-warning text-dark" id="ba-remaining-count">0</span>
        </div>
        <div class="d-flex justify-content-between mt-1">
          <span>Fields annotated</span>
          <span class="badge bg-primary" id="ba-fields-count">0</span>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('js')
<script>
(function() {
  let COLUMNS = [];
  const COLORS = ['#cc0000','#cc0000','#cc0000','#cc0000','#cc0000','#cc0000','#cc0000','#cc0000','#cc0000'];

  let images = [];
  let imgIdx = -1;
  let fieldIdx = 0;
  let annotations = [];
  let img = null;
  let imgNatW = 0, imgNatH = 0; // store natural dimensions explicitly
  let scale = 1;
  let currentTool = 'select'; // default to select/move
  let drawing = false, sx = 0, sy = 0;
  let dragging = false, dragIdx = -1, dragOffX = 0, dragOffY = 0;
  let resizing = false, resizeIdx = -1, resizeHandle = '';
  let panning = false, panStartX = 0, panStartY = 0, panScrollX = 0, panScrollY = 0;
  let offsetX = 0, offsetY = 0;
  let skipped = [];
  let sessionDone = 0, sessionFields = 0;

  // Saved positions — per form type
  let savedPositions = {};
  let currentFolder = '';
  let currentFormType = '';

  // Fields to always skip
  // Only these 5 fields are used — everything else is skipped
  const ALLOWED_FIELDS = [
    'Name', 'Sex', 'Age', 'Event Date', 'Event Year', 'Residence Place',
    'Husband Name', 'Husband Race', 'Spouse', 'Spouse Race', 'Place of Marriage', 'District', 'Province', 'Marriage Date',
    'Text Block',
  ];
  // Display names — rename fields for the UI
  const FIELD_LABELS = {
    'Residence Place': 'Duration of last Illness',
    'Event Year': 'Year (digits)',
  };
  function displayLabel(col) { return FIELD_LABELS[col] || col; }
  function shouldSkip(col) { return !ALLOWED_FIELDS.includes(col); }

  // ── De-duplicate repeated text (TrOCR decoder loop bug) ──
  // "Femalefemalefemalefemale" → "Female"
  // "John SmithJohn SmithJohn Smith" → "John Smith"
  function dedupeRepeats(text) {
    if (!text || text.length < 4) return text;
    const s = text.trim();
    // Try pattern lengths from 1 to half the string
    for (let len = 1; len <= Math.floor(s.length / 2); len++) {
      const pattern = s.substring(0, len);
      // Check if the entire string is just this pattern repeated (case-insensitive)
      const regex = new RegExp('^(' + pattern.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')+$', 'i');
      if (regex.test(s)) {
        return pattern;
      }
    }
    // Also try: "Value value value" with spaces
    const words = s.split(/\s+/);
    if (words.length >= 2) {
      for (let wLen = 1; wLen <= Math.floor(words.length / 2); wLen++) {
        const chunk = words.slice(0, wLen).join(' ');
        const repeated = Array(Math.ceil(words.length / wLen)).fill(chunk).join(' ');
        if (repeated.toLowerCase().startsWith(s.toLowerCase()) || s.toLowerCase().startsWith(repeated.toLowerCase())) {
          return chunk;
        }
      }
    }
    return s;
  }

  // ── Auto-fix date spelling ──
  // Corrects common OCR/handwriting misreads in dates
  function autoFixDate(text) {
    if (!text) return text;
    let s = text.trim();

    // Fix month names (common OCR errors + Afrikaans variants)
    const monthFixes = {
      // January
      'januray':'January','janury':'January','januery':'January','janaury':'January',
      'janauray':'January','januarie':'January','jan':'January',
      // February
      'febuary':'February','feburary':'February','februray':'February','febriary':'February',
      'februari':'February','feb':'February',
      // March
      'marck':'March','mach':'March','maart':'March','mar':'March',
      // April
      'apirl':'April','aprile':'April','apr':'April',
      // May
      'mei':'May',
      // June
      'juen':'June','jnue':'June','junie':'June','jun':'June',
      // July
      'jully':'July','juley':'July','julie':'July','jul':'July',
      // August
      'augst':'August','agust':'August','auguts':'August','augustus':'August','aug':'August',
      // September
      'septmber':'September','setember':'September','septemer':'September',
      'sept':'September','sep':'September',
      // October
      'ocotber':'October','octber':'October','oktober':'October','oct':'October',
      // November
      'novmber':'November','noveber':'November','novemeber':'November',
      'nov':'November',
      // December
      'decmber':'December','deceber':'December','desember':'December','dec':'December',
    };

    // Fix ordinal words (common on SA death certs)
    const ordinalFixes = {
      'frist':'First','forst':'First','firat':'First',
      'secnd':'Second','secord':'Second','seccond':'Second',
      'thrid':'Third','thirc':'Third','thrd':'Third',
      'foruth':'Fourth','fouth':'Fourth','fourht':'Fourth',
      'fith':'Fifth','fifht':'Fifth',
      'sxith':'Sixth','sixht':'Sixth',
      'sevnth':'Seventh','sevenh':'Seventh',
      'eigth':'Eighth','eightb':'Eighth','eigthh':'Eighth',
      'nineth':'Ninth','ninth':'Ninth','nith':'Ninth',
      'teth':'Tenth','tenht':'Tenth',
      'elevnth':'Eleventh','elevenh':'Eleventh',
      'twelfth':'Twelfth','twelvth':'Twelfth','twelth':'Twelfth',
      'therteenth':'Thirteenth','thirteeth':'Thirteenth',
      'fourteeth':'Fourteenth','fourtheenth':'Fourteenth',
      'fifteeth':'Fifteenth','fiftheenth':'Fifteenth',
      'sixteeth':'Sixteenth',
      'seventeeth':'Seventeenth',
      'eighteeth':'Eighteenth',
      'nineteeth':'Nineteenth',
      'twenteth':'Twentieth','twentieh':'Twentieth',
      'twentyfirst':'Twenty First','twenty-first':'Twenty First',
      'twentysecond':'Twenty Second','twenty-second':'Twenty Second',
      'twentythird':'Twenty Third','twenty-third':'Twenty Third',
      'twentyfourth':'Twenty Fourth','twenty-fourth':'Twenty Fourth',
      'twentyfifth':'Twenty Fifth','twenty-fifth':'Twenty Fifth',
      'twentysixth':'Twenty Sixth','twenty-sixth':'Twenty Sixth',
      'twentyseventh':'Twenty Seventh','twenty-seventh':'Twenty Seventh',
      'twentyeighth':'Twenty Eighth','twenty-eighth':'Twenty Eighth',
      'twentyninth':'Twenty Ninth','twenty-ninth':'Twenty Ninth',
      'thirteth':'Thirtieth','thirtyeth':'Thirtieth',
      'thirtyfirst':'Thirty First','thirty-first':'Thirty First',
      // Afrikaans ordinals
      'eerste':'Eerste','tweede':'Tweede','derde':'Derde','vierde':'Vierde',
      'vyfde':'Vyfde','sesde':'Sesde','sewende':'Sewende','agste':'Agste',
      'negende':'Negende','tiende':'Tiende','elfde':'Elfde','twaalfde':'Twaalfde',
      'dertiende':'Dertiende','veertiende':'Veertiende','vyftiende':'Vyftiende',
      'sestiende':'Sestiende','sewentiende':'Sewentiende','agtiende':'Agtiende',
      'negentiende':'Negentiende','twintigste':'Twintigste',
      'een-en-twintigste':'Een-en-twintigste','twee-en-twintigste':'Twee-en-twintigste',
      'drie-en-twintigste':'Drie-en-twintigste','vier-en-twintigste':'Vier-en-twintigste',
      'vyf-en-twintigste':'Vyf-en-twintigste','ses-en-twintigste':'Ses-en-twintigste',
      'sewe-en-twintigste':'Sewe-en-twintigste','ag-en-twintigste':'Ag-en-twintigste',
      'nege-en-twintigste':'Nege-en-twintigste','dertigste':'Dertigste',
      'een-en-dertigste':'Een-en-dertigste',
      // Afrikaans months
      'januarie':'Januarie','februarie':'Februarie','maart':'Maart','april':'April',
      'mei':'Mei','junie':'Junie','julie':'Julie','augustus':'Augustus',
      'september':'September','oktober':'Oktober','november':'November','desember':'Desember',
    };

    // All valid date words for fuzzy matching
    const validMonths = ['January','February','March','April','May','June','July','August','September','October','November','December',
      'Januarie','Februarie','Maart','April','Mei','Junie','Julie','Augustus','September','Oktober','November','Desember'];
    const validOrdinals = [
      'First','Second','Third','Fourth','Fifth','Sixth','Seventh','Eighth','Ninth','Tenth',
      'Eleventh','Twelfth','Thirteenth','Fourteenth','Fifteenth','Sixteenth','Seventeenth',
      'Eighteenth','Nineteenth','Twentieth','Twenty','Thirty','One','Two','Three','Four',
      'Five','Six','Seven','Eight','Nine',
      'Eerste','Tweede','Derde','Vierde','Vyfde','Sesde','Sewende','Agste','Negende','Tiende',
      'Elfde','Twaalfde','Dertiende','Veertiende','Vyftiende','Sestiende','Sewentiende',
      'Agtiende','Negentiende','Twintigste','Dertigste',
    ];
    const allValidWords = [...validMonths, ...validOrdinals];

    // Levenshtein distance
    function levenshtein(a, b) {
      const m = a.length, n = b.length;
      const dp = Array.from({length: m + 1}, (_, i) => Array(n + 1).fill(0));
      for (let i = 0; i <= m; i++) dp[i][0] = i;
      for (let j = 0; j <= n; j++) dp[0][j] = j;
      for (let i = 1; i <= m; i++)
        for (let j = 1; j <= n; j++)
          dp[i][j] = Math.min(
            dp[i-1][j] + 1, dp[i][j-1] + 1,
            dp[i-1][j-1] + (a[i-1].toLowerCase() !== b[j-1].toLowerCase() ? 1 : 0)
          );
      return dp[m][n];
    }

    // Find closest valid word (max distance = 40% of word length)
    function fuzzyMatch(word) {
      if (!word || word.length < 3) return word;
      let best = null, bestDist = Infinity;
      const maxDist = Math.max(2, Math.floor(word.length * 0.4));
      for (const valid of allValidWords) {
        const d = levenshtein(word, valid);
        if (d < bestDist) { bestDist = d; best = valid; }
      }
      return (bestDist <= maxDist) ? best : word;
    }

    // Apply fixes word by word
    const words = s.split(/\s+/);
    const fixed = words.map(w => {
      const clean = w.replace(/[.,;:]/g, '');
      // Skip pure numbers (years like 1920)
      if (/^\d+$/.test(clean)) return w;
      const lower = clean.toLowerCase();
      // Check exact fixes first
      if (monthFixes[lower]) return monthFixes[lower];
      if (ordinalFixes[lower]) return ordinalFixes[lower];
      // Fuzzy match against fix keys (e.g. "twentyfirstl" → "twentyfirst" → "Twenty First")
      let bestFixKey = null, bestFixDist = Infinity;
      for (const key of Object.keys(ordinalFixes)) {
        const d = levenshtein(lower, key);
        if (d < bestFixDist) { bestFixDist = d; bestFixKey = key; }
      }
      for (const key of Object.keys(monthFixes)) {
        const d = levenshtein(lower, key);
        if (d < bestFixDist) { bestFixDist = d; bestFixKey = key; }
      }
      const maxFixDist = Math.max(2, Math.floor(lower.length * 0.4));
      if (bestFixKey && bestFixDist <= maxFixDist && bestFixDist > 0) {
        return ordinalFixes[bestFixKey] || monthFixes[bestFixKey];
      }
      // Fuzzy match against valid single date words
      const matched = fuzzyMatch(clean);
      if (matched !== clean) return matched;
      // Capitalise first letter
      if (w.length > 2 && /^[a-z]/.test(w)) return w.charAt(0).toUpperCase() + w.slice(1);
      return w;
    });

    return fixed.join(' ');
  }

  // Spellcheck age unit — keep the number as-is, just fix the unit text
  // Valid units: Days, Months, Years (and common OCR misreads)
  function spellcheckAge(ageStr) {
    if (!ageStr) return '';
    const s = ageStr.trim();

    // Already just a number — return as-is
    if (/^\d+$/.test(s)) return s;

    // Split into number part and unit part
    const m = s.match(/^(\d+)\s*(.*)/);
    if (!m) {
      // No number — might be all text like "Six Months", return as-is
      return s;
    }

    const num = m[1];
    const unitRaw = (m[2] || '').trim().toLowerCase();
    if (!unitRaw) return num;

    // Fuzzy match unit against known valid values
    const validUnits = {
      'days': 'Days', 'day': 'Days', 'dys': 'Days', 'das': 'Days', 'dae': 'Days', 'dag': 'Days', 'dage': 'Days',
      'months': 'Months', 'month': 'Months', 'mnths': 'Months', 'mths': 'Months', 'mos': 'Months', 'maande': 'Months', 'maand': 'Months',
      'years': 'Years', 'year': 'Years', 'yrs': 'Years', 'yr': 'Years', 'yeas': 'Years', 'jaar': 'Years', 'jare': 'Years',
      'weeks': 'Weeks', 'week': 'Weeks', 'wks': 'Weeks', 'weke': 'Weeks',
      'hours': 'Hours', 'hour': 'Hours', 'hrs': 'Hours', 'ure': 'Hours', 'uur': 'Hours',
    };

    // Exact match first
    if (validUnits[unitRaw]) return num + ' ' + validUnits[unitRaw];

    // Levenshtein fuzzy match
    let bestUnit = unitRaw.charAt(0).toUpperCase() + unitRaw.slice(1);
    let bestDist = 999;
    for (const [key, val] of Object.entries(validUnits)) {
      const d = levenshtein(unitRaw, key);
      if (d < bestDist) { bestDist = d; bestUnit = val; }
    }
    // Accept fuzzy match if distance <= 2
    if (bestDist <= 2) return num + ' ' + bestUnit;

    // Can't fix — return capitalised
    return num + ' ' + unitRaw.charAt(0).toUpperCase() + unitRaw.slice(1);
  }

  // ── Known form templates (positions as % of image width/height) ──
  // Form templates — positions as % of image width/height
  // Calibrated from actual scanned SA death certificates
  // Form templates with anchor-relative positioning:
  // - anchor: keywords to find the form title via OCR
  // - anchorRef: expected position of the anchor on the reference/calibration image (as % of image)
  // - fields: positions relative to the anchor reference
  // On each image, OCR finds the anchor, computes offset + scale vs anchorRef, then shifts all fields
  const FORM_TEMPLATES = {
    'sa-death-1923': {
      label: 'SA Death — Informasievorm (1923 Act, EN/AF bilingual)',
      detect: ['informasievorm', 'sterfgeval'],
      anchor: ['informasievorm', 'sterfgeval'],
      anchorRef: { x: 0.22, y: 0.04, w: 0.56, h: 0.02 }, // title position on reference image
      fields: {
        'Name':           { x: 0.25, y: 0.08, w: 0.45, h: 0.06 },
        'Residence Place':{ x: 0.25, y: 0.15, w: 0.55, h: 0.04 },
        'Sex':            { x: 0.25, y: 0.22, w: 0.15, h: 0.03 },
        'Age':            { x: 0.25, y: 0.25, w: 0.20, h: 0.03 },
        'Event Date':     { x: 0.25, y: 0.38, w: 0.45, h: 0.03 },
      }
    },
    'sa-death-1894': {
      label: 'SA Death — Form of Information / Kennisgewing (Act/Wet 7 of 1894)',
      detect: ['1894', 'act no', 'deceased', 'kennisgewing', 'oorledene', 'wet no'],
      anchor: ['death:', 'act'],  // "DEATH: ACT No. 7 OF 1894" — compact, consistent
      anchorRef: { x: 0.63, y: 0.09, w: 0.19, h: 0.01 },
      fields: {
        'Name':           { x: 0.51, y: 0.19, w: 0.42, h: 0.04 },  // 1. Christian Names and Surname
        'Residence Place':{ x: 0.51, y: 0.22, w: 0.42, h: 0.03 },  // 2. Usual place of Residence
        'Age':            { x: 0.51, y: 0.24, w: 0.20, h: 0.02 },  // 3. Age
        'Sex':            { x: 0.51, y: 0.26, w: 0.15, h: 0.02 },  // 4. Race(a) — Sex
        'Event Date':     { x: 0.51, y: 0.32, w: 0.40, h: 0.03 },  // 8. Date of Death
      }
    },
    'sa-death-generic': {
      label: 'SA Death — Generic (fallback)',
      detect: ['death', 'dood', 'form of information'],
      anchor: ['form', 'death'],
      anchorRef: { x: 0.22, y: 0.04, w: 0.56, h: 0.02 },
      fields: {
        'Name':           { x: 0.25, y: 0.08, w: 0.45, h: 0.06 },
        'Residence Place':{ x: 0.25, y: 0.15, w: 0.55, h: 0.04 },
        'Sex':            { x: 0.25, y: 0.22, w: 0.15, h: 0.03 },
        'Age':            { x: 0.25, y: 0.25, w: 0.20, h: 0.03 },
        'Event Date':     { x: 0.25, y: 0.38, w: 0.45, h: 0.03 },
      }
    },
    'sa-marriage-register': {
      label: 'SA Marriage — Duplicate Original Marriage Register (EN/AF)',
      detect: ['marriage', 'huwelik', 'huweliksregister', 'duplicate original marriage', 'duplikaat origineel'],
      anchor: ['duplicate', 'marriage', 'register'],
      anchorRef: { x: 0.12, y: 0.04, w: 0.50, h: 0.02 },
      fields: {
        'Husband Name':      { x: 0.08, y: 0.275, w: 0.19, h: 0.068 },   // husband name
        'Husband Race':      { x: 0.05, y: 0.13, w: 0.10, h: 0.05 },    // race column
        'Spouse':            { x: 0.19, y: 0.385, w: 0.19, h: 0.058 },  // wife/spouse name
        'Spouse Race':       { x: 0.16, y: 0.20, w: 0.10, h: 0.05 },    // spouse race
        'Place of Marriage': { x: 0.452, y: 0.175, w: 0.134, h: 0.055 },  // bottom-aligned at 0.23
        'District':          { x: 0.628, y: 0.175, w: 0.128, h: 0.055 },  // bottom-aligned at 0.23
        'Province':          { x: 0.817, y: 0.175, w: 0.13, h: 0.055 },   // bottom-aligned at 0.23
        'Event Date':        { x: 0.16, y: 0.70, w: 0.29, h: 0.05 },     // "solemnized by me on this the ..."
        'Marriage Date':     { x: 0.0, y: 0.43, w: 0.085, h: 0.14 },      // left edge of image
      }
    },
    'narrative': {
      label: 'Narrative — Free-form handwritten text (letters, diaries, notes)',
      detect: ['dear', 'sir', 'madam', 'letter', 'diary', 'note', 'beloved'],
      anchor: [],
      anchorRef: {},
      fields: {
        'Text Block': { x: 0.02, y: 0.02, w: 0.96, h: 0.96 },
      }
    },
    'manual': {
      label: 'Manual positioning (no template)',
      detect: [],
      fields: {}
    }
  };

  // Populate form type dropdown + change handler
  (function() {
    const sel = document.getElementById('ba-form-type');
    for (const [key, tpl] of Object.entries(FORM_TEMPLATES)) {
      const opt = document.createElement('option');
      opt.value = key;
      opt.textContent = tpl.label;
      sel.appendChild(opt);
    }
    sel.addEventListener('change', function() {
      if (img && images[imgIdx]) {
        const newType = this.value === 'auto' ? 'sa-death-generic' : this.value;
        currentFormType = newType;

        // Rebuild COLUMNS from the new template's fields
        const tpl = FORM_TEMPLATES[newType];
        if (tpl && tpl.fields && Object.keys(tpl.fields).length > 0) {
          COLUMNS = Object.keys(tpl.fields).filter(col => !shouldSkip(col));
          // Ensure empty field values exist on current image
          const entry = images[imgIdx];
          if (entry) {
            COLUMNS.forEach(col => { if (!entry.fields[col]) entry.fields[col] = ''; });
          }
        }

        loadSavedPositions(() => {
          applyFormTemplate(newType);
          buildFieldList();
          redraw();
        });
      }
    });
  })();

  // Auto-detect form type from first image OCR
  function detectFormType(ocrWords) {
    const allText = ocrWords.map(w => (typeof w === 'string' ? w : (w.text || '')).toLowerCase()).join(' ');
    let bestType = 'manual';
    let bestScore = 0;

    for (const [key, tpl] of Object.entries(FORM_TEMPLATES)) {
      if (!tpl.detect || !tpl.detect.length) continue;
      let score = 0;
      for (const kw of tpl.detect) {
        if (allText.includes(kw)) score++;
      }
      // Normalize score by number of detect keywords (higher % match = better fit)
      const normalized = score / tpl.detect.length;
      if (score > 0 && (score > bestScore || (score === bestScore && normalized > (bestScore / (FORM_TEMPLATES[bestType]?.detect?.length || 1))))) {
        bestScore = score;
        bestType = key;
      }
    }

    if (bestScore === 0) {
      bestType = 'sa-death-generic'; // fallback only when nothing matches at all
      document.getElementById('ba-image-name').textContent += ' — UNRECOGNISED FORM (using generic, select form type manually)';
    } else {
      const tpl = FORM_TEMPLATES[bestType];
      document.getElementById('ba-image-name').textContent += ' — Detected: ' + (tpl ? tpl.label : bestType);
    }

    return bestType;
  }

  // Current anchor detection result (set by OCR)
  let detectedAnchor = null;

  // Apply form template — positions fields relative to detected anchor
  function applyFormTemplate(templateKey, anchor) {
    const tpl = FORM_TEMPLATES[templateKey];
    if (!tpl) return;

    currentFormType = templateKey;
    document.getElementById('ba-form-type').value = templateKey;

    // Rebuild COLUMNS from the template's fields so the correct fields appear
    if (tpl.fields && Object.keys(tpl.fields).length > 0) {
      COLUMNS = Object.keys(tpl.fields).filter(col => !shouldSkip(col));
      // Ensure empty field values exist on current image
      const entry = images[imgIdx];
      if (entry) {
        COLUMNS.forEach(col => { if (!entry.fields[col]) entry.fields[col] = ''; });
      }
    }

    // If we have a detected anchor AND the template has an anchor reference,
    // compute offset + scale to adjust all field positions
    // Anchor-relative adjustment: shift field positions based on where the title was found
    let offsetXPct = 0, offsetYPct = 0, scaleX = 1, scaleY = 1;
    if (anchor && tpl.anchorRef && anchor.w_pct > 0.1) {
      // Only trust anchor if it's wide enough (>10% of image = real title, not a stray word)
      offsetXPct = anchor.x_pct - tpl.anchorRef.x;
      offsetYPct = anchor.y_pct - tpl.anchorRef.y;
      if (tpl.anchorRef.w > 0) {
        scaleX = anchor.w_pct / tpl.anchorRef.w;
        scaleY = scaleX;
      }
      // Sanity check: if offset or scale is too extreme, ignore anchor
      if (Math.abs(offsetXPct) > 0.3 || Math.abs(offsetYPct) > 0.3 || scaleX < 0.5 || scaleX > 2) {
        console.log('[FS Overlay] Anchor unreliable, ignoring. offset=(' + offsetXPct.toFixed(3) + ',' + offsetYPct.toFixed(3) + ') scale=' + scaleX.toFixed(3));
        offsetXPct = 0; offsetYPct = 0; scaleX = 1; scaleY = 1;
      } else {
        console.log('[FS Overlay] Anchor adjustment: offset=(' + offsetXPct.toFixed(3) + ',' + offsetYPct.toFixed(3) + ') scale=' + scaleX.toFixed(3));
      }
    }

    const entry = images[imgIdx];
    annotations = [];
    skipped = [];

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

      // Priority: 1) server-saved positions, 2) anchor-adjusted template, 3) raw template, 4) default
      if (savedPositions[col]) {
        const sp = savedPositions[col];
        annotations.push({ label: col, value: val,
          x: Math.round(sp.x * imgNatW), y: Math.round(sp.y * imgNatH),
          w: Math.round(sp.w * imgNatW), h: Math.round(sp.h * imgNatH),
        });
      } else if (tpl.fields[col]) {
        const f = tpl.fields[col];
        // Apply anchor offset + scale
        const adjX = (f.x + offsetXPct) * scaleX + (1 - scaleX) * tpl.anchorRef.x;
        const adjY = (f.y + offsetYPct) * scaleY + (1 - scaleY) * tpl.anchorRef.y;
        const adjW = f.w * scaleX;
        const adjH = f.h * scaleY;
        annotations.push({
          label: col, value: val,
          x: Math.round(adjX * imgNatW),
          y: Math.round(adjY * imgNatH),
          w: Math.round(adjW * imgNatW),
          h: Math.round(adjH * imgNatH),
        });
      } else {
        // No template position — stack below others
        const activeIdx = annotations.filter(a => a).length;
        annotations.push({
          label: col, value: val,
          x: Math.round(imgNatW * 0.05),
          y: Math.round(imgNatH * 0.08) + activeIdx * Math.round(imgNatH / 15),
          w: Math.round(imgNatW * 0.45),
          h: Math.round(imgNatH * 0.04),
        });
      }
    });

    highlightField();
    updateProgress();
    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const c = document.getElementById('ba-coords-' + i);
      if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
    });
  }

  window.baSetTool = function(t) {
    currentTool = t;
    document.getElementById('ba-tool-hand').classList.toggle('active', t === 'hand');
    document.getElementById('ba-tool-draw').classList.toggle('active', t === 'draw');
    document.getElementById('ba-tool-select').classList.toggle('active', t === 'select');
    cvs.style.setProperty('cursor', t === 'hand' ? 'grab' : (t === 'draw' ? 'crosshair' : 'default'), 'important');
  };

  function hitTest(px, py) {
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      if (px >= a.x && px <= a.x + a.w && py >= a.y && py <= a.y + a.h) return i;
    }
    return -1;
  }

  function hitResize(px, py) {
    const margin = 8 / scale;
    for (let i = annotations.length - 1; i >= 0; i--) {
      const a = annotations[i];
      if (!a) continue;
      // Corners
      if (Math.abs(px - a.x) < margin && Math.abs(py - a.y) < margin) return {idx: i, handle: 'tl'};
      if (Math.abs(px - (a.x + a.w)) < margin && Math.abs(py - a.y) < margin) return {idx: i, handle: 'tr'};
      if (Math.abs(px - a.x) < margin && Math.abs(py - (a.y + a.h)) < margin) return {idx: i, handle: 'bl'};
      if (Math.abs(px - (a.x + a.w)) < margin && Math.abs(py - (a.y + a.h)) < margin) return {idx: i, handle: 'br'};
      // Edges
      if (Math.abs(px - a.x) < margin && py >= a.y && py <= a.y + a.h) return {idx: i, handle: 'left'};
      if (Math.abs(px - (a.x + a.w)) < margin && py >= a.y && py <= a.y + a.h) return {idx: i, handle: 'right'};
      if (Math.abs(py - a.y) < margin && px >= a.x && px <= a.x + a.w) return {idx: i, handle: 'top'};
      if (Math.abs(py - (a.y + a.h)) < margin && px >= a.x && px <= a.x + a.w) return {idx: i, handle: 'bottom'};
    }
    return null;
  }

  const cvs = document.getElementById('ba-canvas');
  const ctx = cvs.getContext('2d');
  const wrap = document.getElementById('ba-wrap');

  // ── Auto-refresh spreadsheet dropdown when folder changes ──
  let folderRefreshTimer = null;
  document.getElementById('ba-folder').addEventListener('change', refreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('blur', refreshSpreadsheets);
  document.getElementById('ba-folder').addEventListener('keyup', function() {
    clearTimeout(folderRefreshTimer);
    folderRefreshTimer = setTimeout(refreshSpreadsheets, 500);
  });

  function refreshSpreadsheets() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) return;
    const ssSelect = document.getElementById('ba-spreadsheet');

    fetch('{{ route("admin.ai.htr.fsOverlayLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.needsSelection) {
        ssSelect.innerHTML = '<option value="__none__">No spreadsheet (images only)</option>';
        (data.spreadsheets || []).forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          ssSelect.appendChild(opt);
        });
        // Auto-select the spreadsheet if only one exists
        if (data.spreadsheets && data.spreadsheets.length === 1) {
          ssSelect.value = data.spreadsheets[0];
        }
        // If no spreadsheets at all, auto-select "No spreadsheet"
        if (data.noSpreadsheets) {
          ssSelect.value = '__none__';
        }
      }
    })
    .catch(() => {});
  }

  // Load spreadsheets on page load
  refreshSpreadsheets();

  // ── Load data (two-step: list spreadsheets → select → load) ──
  window.loadBulkData = function() {
    const folder = document.getElementById('ba-folder').value.trim();
    if (!folder) { alert('Enter folder path'); return; }

    const btn = document.getElementById('ba-load-btn');
    const ssSelect = document.getElementById('ba-spreadsheet');
    const selectedSS = ssSelect.value;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';

    fetch('{{ route("admin.ai.htr.fsOverlayLoad") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ folder: folder, spreadsheet: selectedSS }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      if (!data.success) { alert(data.error || 'Load failed'); return; }

      // Step 1: populate spreadsheet dropdown
      if (data.needsSelection) {
        ssSelect.innerHTML = '<option value="__none__">No spreadsheet (images only)</option>';
        (data.spreadsheets || []).forEach(name => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          ssSelect.appendChild(opt);
        });
        // Auto-select if only one spreadsheet
        if (data.spreadsheets && data.spreadsheets.length === 1) {
          ssSelect.value = data.spreadsheets[0];
          loadBulkData(); // re-call with selection
        }
        // If no spreadsheets at all, auto-load in images-only mode
        if (data.noSpreadsheets) {
          ssSelect.value = '__none__';
          loadBulkData(); // re-call with __none__
        }
        return;
      }

      // Step 2: data loaded
      images = data.images;
      COLUMNS = (data.columns || []).filter(col => !shouldSkip(col));
      imgIdx = -1;
      document.getElementById('ba-workspace').style.display = '';
      document.getElementById('ba-remaining-count').textContent = images.length;

      if (COLUMNS.length === 0 && images.length > 0) {
        COLUMNS = Object.keys(images[0].fields).filter(col => !shouldSkip(col));
      }

      // Ensure all template fields are in COLUMNS (even if not in CSV)
      // So user can always draw boxes for Name, Cause of Death, etc.
      const selFormType = document.getElementById('ba-form-type').value;
      const tplKey = (selFormType && selFormType !== 'auto') ? selFormType : 'sa-death-generic';
      const tpl = FORM_TEMPLATES[tplKey];
      if (tpl && tpl.fields) {
        for (const fieldName of Object.keys(tpl.fields)) {
          if (shouldSkip(fieldName)) continue;
          if (!COLUMNS.includes(fieldName)) {
            COLUMNS.push(fieldName);
            // Add empty field value to all images
            images.forEach(img => { if (!img.fields[fieldName]) img.fields[fieldName] = ''; });
          }
        }
      }

      currentFolder = folder;

      // Form type: use dropdown selection or default
      const selType = document.getElementById('ba-form-type').value;
      if (selType && selType !== 'auto') {
        currentFormType = selType;
      } else {
        currentFormType = 'sa-death-generic'; // will be refined by auto-detect
      }

      // Load saved positions for current form type from server
      loadSavedPositions(() => {
        console.log('[FS Overlay] Starting with form type:', currentFormType, 'saved fields:', Object.keys(savedPositions));
        nextImage();
        baSetTool('select');
      });
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-upload me-1"></i>Load';
      alert(err.message);
    });
  };

  function nextImage() {
    imgIdx++;
    if (imgIdx >= images.length) { alert('All images annotated!'); return; }
    fieldIdx = 0;
    annotations = [];
    skipped = [];
    loadImage(); // buildFieldList() is called inside afterFieldsPlaced() after image loads
    updateCounters();
  }

  function loadImage() {
    const entry = images[imgIdx];
    document.getElementById('ba-image-name').textContent = entry.fname + ' (' + (imgIdx + 1) + '/' + images.length + ')';
    document.getElementById('ba-counter').textContent = (imgIdx + 1) + '/' + images.length;

    img = new Image();
    img.onload = function() {
      imgNatW = img.naturalWidth || img.width;
      imgNatH = img.naturalHeight || img.height;
      offsetX = 0; offsetY = 0; cvs.style.transform = '';
      scale = wrap.clientWidth / imgNatW;
      cvs.width = imgNatW * scale;
      cvs.height = imgNatH * scale;

      const selectedType = document.getElementById('ba-form-type').value;

      function afterFieldsPlaced() {
        // Debug: show what we have
        const annCount = annotations.filter(a => a).length;
        console.log('[FS Overlay] COLUMNS:', COLUMNS, 'annotations:', annCount, 'skipped:', skipped.length, 'img:', img.width, 'x', img.height, 'savedPos:', Object.keys(savedPositions));
        if (annCount === 0) {
          console.warn('[FS Overlay] No annotations created. COLUMNS:', COLUMNS.join(', '), 'Form type:', currentFormType);
        }
        buildFieldList();
        redraw();
        if (autoRecogEnabled) setTimeout(baRecognise, 500);
      }

      // If a specific form type is selected, always use template positions
      if (selectedType && selectedType !== 'auto') {
        currentFormType = selectedType;
        // Always rebuild COLUMNS + apply template for the selected form type
        const tpl = FORM_TEMPLATES[selectedType];
        if (tpl && tpl.fields && Object.keys(tpl.fields).length > 0) {
          COLUMNS = Object.keys(tpl.fields).filter(col => !shouldSkip(col));
          const e = images[imgIdx];
          if (e) COLUMNS.forEach(col => { if (!e.fields[col]) e.fields[col] = ''; });
        }
        applyFormTemplate(selectedType);
        afterFieldsPlaced();
      } else if (COLUMNS.some(col => savedPositions[col])) {
        autoPlaceFields();
        afterFieldsPlaced();
      } else {
        // No form type selected — use OCR label detection if Detect is on
        const autoDetectLabels = document.getElementById('ba-auto-detect')?.checked;
        if (autoDetectLabels) {
          ocrAndPlace(entry);
          if (autoRecogEnabled) setTimeout(baRecognise, 1500);
          return;
        }
        // Auto-detect: quick OCR to identify form type, then apply template
        document.getElementById('ba-image-name').textContent += ' — detecting form type...';
        fetch('{{ route("admin.ai.htr.fsOverlayOcr") }}', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
          body: JSON.stringify({ image_path: entry.path, fields: [] }),
        })
        .then(r => r.json())
        .then(data => {
          if (data.success && data.words) {
            const formType = detectFormType(data.words);
            detectedAnchor = data.anchor || null;
            currentFormType = formType;
            loadSavedPositions(() => {
              applyFormTemplate(formType, detectedAnchor);
              afterFieldsPlaced();
            });
          } else {
            currentFormType = 'sa-death-generic';
            detectedAnchor = null;
            loadSavedPositions(() => {
              applyFormTemplate('sa-death-generic');
              afterFieldsPlaced();
            });
          }
        })
        .catch(() => { applyFormTemplate('sa-death-generic'); afterFieldsPlaced(); });
      }
    };
    img.src = '{{ route("admin.ai.htr.serveCroppedImage") }}?path=' + encodeURIComponent(entry.path) + '&_=' + Date.now();
  }

  // ── Auto-place: position all field boxes on the image ──
  // Priority: 1) saved positions from previous images, 2) smart defaults
  function autoPlaceFields() {
    const entry = images[imgIdx];
    annotations = [];

    // Count non-empty fields to calculate spacing
    const activeFields = COLUMNS.filter(col => entry.fields[col]);
    const fieldCount = activeFields.length;

    // Smart default sizing — spread fields vertically across the document
    const imgW = img.width;
    const imgH = img.height;
    const boxW = Math.round(imgW * 0.45);  // ~45% of image width
    const boxH = Math.round(Math.min(60, imgH / (fieldCount + 2))); // tall enough to read
    const startX = Math.round(imgW * 0.05); // 5% margin from left
    const startY = Math.round(imgH * 0.08); // start 8% from top
    const spacing = Math.round((imgH * 0.85) / Math.max(fieldCount, 1)); // even vertical spacing
    let activeIdx = 0;

    COLUMNS.forEach(function(col, i) {
      const val = entry.fields[col] || '';
      if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

      // Use saved position if we have one for this column
      if (savedPositions[col]) {
        const sp = savedPositions[col];
        annotations.push({
          label: col,
          value: val,
          x: Math.round(sp.x * imgNatW),
          y: Math.round(sp.y * imgNatH),
          w: Math.round(sp.w * imgNatW),
          h: Math.round(sp.h * imgNatH),
        });
      } else {
        // Smart default: distribute evenly down the page
        annotations.push({
          label: col,
          value: val,
          x: startX,
          y: startY + activeIdx * spacing,
          w: boxW,
          h: boxH,
        });
      }
      activeIdx++;
    });

    highlightField();
    updateProgress();

    // Update coord displays
    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const c = document.getElementById('ba-coords-' + i);
      if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
    });
  }

  // ── OCR labels: detect printed form labels and position boxes there ──
  function ocrAndPlace(entry) {
    document.getElementById('ba-image-name').textContent += ' — OCR detecting labels...';

    // Send ALL allowed fields for detection, not just ones with CSV data
    const activeFields = ALLOWED_FIELDS.slice();

    fetch('{{ route("admin.ai.htr.fsOverlayOcr") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        fields: activeFields,
      }),
    })
    .then(r => r.json())
    .then(data => {
      if (data.success && data.positions) {
        // Apply OCR-detected positions
        const positions = data.positions;
        annotations = [];
        let phraseCount = 0, contextCount = 0, kwCount = 0;
        COLUMNS.forEach(function(col, i) {
          const val = entry.fields[col] || '';
          if (shouldSkip(col)) { skipped.push(i); annotations.push(null); return; }

          if (positions[col]) {
            const p = positions[col];
            const ann = {
              label: col,
              value: val,
              x: p.x,
              y: p.y,
              w: p.w,
              h: p.h,
              // Store detected label position for highlighting
              labelBox: p.label_x !== undefined ? { x: p.label_x, y: p.label_y, w: p.label_w, h: p.label_h } : null,
              labelText: p.label_text || null,
              matchStrategy: p.match_strategy || null,
            };
            annotations.push(ann);
            // Save as reference for next images
            savedPositions[col] = { x: p.x / imgNatW, y: p.y / imgNatH, w: p.w / imgNatW, h: p.h / imgNatH };
            // Track match quality
            if (p.match_strategy === 'phrase') phraseCount++;
            else if (p.match_strategy === 'keyword+context') contextCount++;
            else kwCount++;
            // Show match info in sidebar
            const coordsEl = document.getElementById('ba-coords-' + i);
            if (coordsEl) {
              const prev = coordsEl.parentNode.querySelector('.ba-recog');
              if (prev) prev.remove();
              const badge = document.createElement('div');
              badge.className = 'ba-recog';
              const color = p.match_strategy === 'phrase' ? '#198754' : (p.match_strategy === 'keyword+context' ? '#0d6efd' : '#6c757d');
              badge.style.cssText = 'font-size:0.7rem; color:' + color + ';';
              badge.innerHTML = '<i class="fas fa-crosshairs" style="font-size:0.6rem"></i> ' + (p.match_strategy || 'keyword') + ': "' + (p.label_text || '').replace(/</g,'&lt;') + '"';
              coordsEl.parentNode.appendChild(badge);
            }
          } else {
            // No OCR match — use default position
            const activeIdx = annotations.filter(a => a).length;
            annotations.push({
              label: col,
              value: val,
              x: 20,
              y: 50 + activeIdx * Math.round(imgNatH / (activeFields.length + 2)),
              w: Math.round(imgNatW * 0.45),
              h: 40,
            });
          }
        });

        // Persist the OCR-detected positions to server
        persistPositions();

        highlightField();
        updateProgress();
        annotations.forEach(function(ann, i) {
          if (!ann) return;
          const c = document.getElementById('ba-coords-' + i);
          if (c) c.textContent = Math.round(ann.x) + ',' + Math.round(ann.y) + ' ' + Math.round(ann.w) + '×' + Math.round(ann.h);
        });
        redraw();

        const summary = phraseCount + ' phrase, ' + contextCount + ' context, ' + kwCount + ' keyword';
        document.getElementById('ba-image-name').textContent = entry.fname + ' (' + (imgIdx + 1) + '/' + images.length + ') — ' + Object.keys(positions).length + ' labels detected (' + summary + ')';
      } else {
        // OCR failed — fall back to default placement
        autoPlaceFields();
        redraw();
      }
    })
    .catch(() => {
      autoPlaceFields();
      redraw();
    });
  }

  // Reset saved positions (start fresh)
  // Migrate all localStorage positions to server (one-time sync)
  window.baMigrateToServer = function() {
    let count = 0;
    for (let i = 0; i < localStorage.length; i++) {
      const key = localStorage.key(i);
      if (!key.startsWith('fs-overlay-pos-')) continue;
      try {
        const positions = JSON.parse(localStorage.getItem(key));
        // Extract form type from key: fs-overlay-pos-sa-death-1894 → sa-death-1894
        const formType = key.replace('fs-overlay-pos-', '').replace(/_/g, '-');
        fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
          body: JSON.stringify({ form_type: formType, positions }),
        });
        count++;
      } catch(e) {}
    }
    // Also save current positions
    if (currentFormType && Object.keys(savedPositions).length) {
      fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({ form_type: currentFormType, positions: savedPositions }),
      });
      count++;
    }
    const btn = document.getElementById('ba-migrate-btn');
    btn.innerHTML = '<i class="fas fa-check me-1"></i>Synced (' + count + ')';
    btn.disabled = true;
    alert('Synced ' + count + ' form type position(s) to server.');
  };

  window.baResetPositions = function() {
    if (!confirm('Clear all saved field positions? You will need to reposition on the next image.')) return;
    savedPositions = {};
    persistPositions(); // clear on server too
    autoPlaceFields();
    redraw();
  };

  // ── Manual crop: mark area, then crop ──
  let cropMode = false;
  let cropRect = null; // {x, y, w, h} in image coords

  window.baStartCropDraw = function() {
    cropMode = true;
    cropRect = null;
    baSetTool('draw');
    const drawBtn = document.getElementById('ba-crop-draw-btn');
    drawBtn.innerHTML = '<i class="fas fa-crop-alt me-1"></i>Drawing...';
    drawBtn.classList.add('active');
    document.getElementById('ba-crop-do-btn').classList.add('d-none');
    document.getElementById('ba-image-name').textContent += ' — DRAW CROP RECTANGLE';
  };

  // Called from draw mouseup when cropMode is on — just stores the rect and shows it
  function handleCropDraw(x, y, w, h) {
    cropMode = false;
    cropRect = { x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) };

    const drawBtn = document.getElementById('ba-crop-draw-btn');
    drawBtn.innerHTML = '<i class="fas fa-crop-alt me-1"></i>Mark area';
    drawBtn.classList.remove('active');

    // Show the "Crop now" button
    const doBtn = document.getElementById('ba-crop-do-btn');
    doBtn.classList.remove('d-none');
    doBtn.innerHTML = '<i class="fas fa-cut me-1"></i>Crop now (' + cropRect.w + '×' + cropRect.h + ')';

    // Draw the crop rect on canvas
    redraw();
    ctx.save();
    // Dim everything outside the crop rect
    ctx.fillStyle = 'rgba(0,0,0,0.5)';
    ctx.fillRect(0, 0, cvs.width, cropRect.y * scale);
    ctx.fillRect(0, (cropRect.y + cropRect.h) * scale, cvs.width, cvs.height);
    ctx.fillRect(0, cropRect.y * scale, cropRect.x * scale, cropRect.h * scale);
    ctx.fillRect((cropRect.x + cropRect.w) * scale, cropRect.y * scale, cvs.width, cropRect.h * scale);
    // Green border on crop rect
    ctx.strokeStyle = '#00ff00';
    ctx.lineWidth = 3;
    ctx.setLineDash([6, 4]);
    ctx.strokeRect(cropRect.x * scale, cropRect.y * scale, cropRect.w * scale, cropRect.h * scale);
    ctx.restore();

    baSetTool('select');
  };

  window.baDoCrop = function() {
    if (!cropRect) { alert('Draw a crop area first'); return; }

    const entry = images[imgIdx];
    const doBtn = document.getElementById('ba-crop-do-btn');
    doBtn.disabled = true;
    doBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Cropping...';

    fetch('{{ route("admin.ai.htr.fsOverlayManualCrop") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        x: cropRect.x,
        y: cropRect.y,
        w: cropRect.w,
        h: cropRect.h,
      }),
    })
    .then(r => r.json())
    .then(data => {
      doBtn.disabled = false;
      doBtn.classList.add('d-none');
      cropRect = null;
      if (data.success) {
        loadImage();
        buildFieldList();
      } else {
        alert(data.error || 'Crop failed');
      }
    })
    .catch(err => {
      doBtn.disabled = false;
      alert('Crop error: ' + err.message);
    });
  };

  // ── Auto-recognise toggle ──
  let autoRecogEnabled = localStorage.getItem('fs-overlay-auto-recog') !== 'false';

  // Set initial state
  (function() {
    const cb = document.getElementById('ba-auto-recog');
    if (cb) cb.checked = autoRecogEnabled;
  })();

  window.baToggleAutoRecog = function(on) {
    autoRecogEnabled = on;
    localStorage.setItem('fs-overlay-auto-recog', on ? 'true' : 'false');
  };

  // ── Recognise: send field crops to HTR service ──
  window.baRecognise = function() {
    const entry = images[imgIdx];
    const btn = document.getElementById('ba-recognise-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Recognising...';

    // Build annotations list with current positions
    const anns = annotations.filter(a => a).map(a => ({
      label: a.label, x: a.x, y: a.y, w: a.w, h: a.h,
    }));

    fetch('{{ route("admin.ai.htr.fsOverlayRecognise") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        annotations: anns,
      }),
    })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-brain me-1"></i>Recognise';

      if (data.success && data.results) {
        // Show recognised text under each field — click to accept
        let count = 0;
        for (const [label, result] of Object.entries(data.results)) {
          const idx = COLUMNS.indexOf(label);
          if (idx < 0) continue;
          count++;
          const text = result.text || '';
          const error = result.error || '';

          // Find the coords div and add recognised text below it
          const coordsEl = document.getElementById('ba-coords-' + idx);
          if (!coordsEl) continue;

          // Remove any previous recognition result
          const prev = coordsEl.parentNode.querySelector('.ba-recog');
          if (prev) prev.remove();

          if (text) {
            // De-duplicate repeated text (TrOCR loop bug)
            let cleanText = dedupeRepeats(text);
            // Auto-fix: dates get date spellcheck, age gets unit spellcheck
            let fixedText = cleanText;
            if (label === 'Event Date') fixedText = autoFixDate(cleanText);
            else if (label === 'Age') fixedText = spellcheckAge(cleanText);

            // Auto-populate — skip CSV-prefilled name fields (reference data)
            const bulkCsvPrefilled = ['Husband Name', 'Spouse'];
            const inp = document.querySelector('.ba-edit-input[data-field-idx="' + idx + '"]');
            if (inp && !bulkCsvPrefilled.includes(label)) {
              inp.value = fixedText;
              entry.fields[label] = fixedText;
              if (annotations[idx]) annotations[idx].value = fixedText;
            }

            // Always show the recognised text below for reference / click to replace
            const recogDiv = document.createElement('div');
            recogDiv.className = 'ba-recog';
            recogDiv.style.cssText = 'font-size:0.75rem; color:#c00; cursor:pointer; padding:2px 0; border-top:1px dashed #ddd; margin-top:2px;';
            recogDiv.title = 'Click to use this value';
            recogDiv.innerHTML = '<i class="fas fa-robot" style="font-size:0.6rem"></i> ' +
              '<span style="text-decoration:underline dotted">' + fixedText.replace(/</g,'&lt;') + '</span>' +
              (fixedText !== text ? ' <span style="font-size:0.6rem;color:#999">(was: ' + text.replace(/</g,'&lt;') + ')</span>' : '');
            recogDiv.addEventListener('click', function() {
              if (inp) {
                inp.value = fixedText;
                entry.fields[label] = fixedText;
                if (annotations[idx]) annotations[idx].value = fixedText;
                redraw();
                this.style.color = '#198754';
                this.innerHTML = '<i class="fas fa-check"></i> Accepted';
              }
            });
            coordsEl.parentNode.appendChild(recogDiv);

            // Place match: "Add to dictionary" for place fields — only when NOT already in dict
            const bulkPlaceLabels = ['Place of Marriage', 'District', 'Province'];
            const bulkIsExact = result.place_match && result.place_match.match_type === 'exact';
            if (bulkPlaceLabels.includes(label) && fixedText.length >= 2 && !bulkIsExact) {
              const hasGoodMatch = result.place_match && (result.place_match.confidence || 0) >= 0.9;
              if (!hasGoodMatch) {
                const addDiv = document.createElement('div');
                addDiv.style.cssText = 'font-size:0.65rem; color:#6c757d; cursor:pointer; padding:1px 0;';
                addDiv.title = 'Add "' + fixedText + '" to SA towns dictionary';
                addDiv.innerHTML = '<i class="fas fa-plus-circle" style="font-size:0.55rem"></i> Add "' + fixedText.replace(/</g,'&lt;') + '" to dictionary';
                addDiv.addEventListener('click', function() {
                  const townName = inp ? inp.value.trim() : fixedText;
                  if (!townName) return;
                  this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.55rem"></i> Adding...';
                  fetch('{{ route("admin.ai.htr.addTown") }}', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}'},
                    body: JSON.stringify({ name: townName, province: '', district: '' }),
                  })
                  .then(r => r.json())
                  .then(d => {
                    if (d.success) { this.style.color = '#198754'; this.innerHTML = '<i class="fas fa-check"></i> Added (' + (d.stats?.sa_towns || '') + ' towns)'; }
                    else { this.style.color = '#dc3545'; this.innerHTML = '<i class="fas fa-times"></i> ' + (d.error || 'Failed'); }
                  })
                  .catch(() => { this.innerHTML = '<i class="fas fa-times"></i> Error'; });
                });
                coordsEl.parentNode.appendChild(addDiv);
              }
            }

            // Place match badge — clickable to replace text
            if (result.place_match) {
              const pm = result.place_match;
              const pmDiv = document.createElement('div');
              pmDiv.style.cssText = 'font-size:0.7rem; color:#0d6efd; padding:1px 0; cursor:pointer;';
              pmDiv.title = 'Click to use: ' + (pm.name || '');
              const conf = Math.round((pm.confidence || 0) * 100);
              const icon = pm.match_type === 'exact' ? 'fa-map-marker-alt' : 'fa-search-location';
              let info = '<i class="fas ' + icon + '" style="font-size:0.6rem"></i> <span style="text-decoration:underline dotted">' + (pm.name || '').replace(/</g,'&lt;') + '</span>';
              if (pm.province) info += ' <span style="color:#666">(' + (pm.historical_province || pm.province) + ')</span>';
              info += ' <span style="font-size:0.6rem;color:#999">' + conf + '%</span>';
              pmDiv.innerHTML = info;
              pmDiv.addEventListener('click', function() {
                if (inp && pm.name) {
                  inp.value = pm.name;
                  entry.fields[label] = pm.name;
                  if (annotations[idx]) annotations[idx].value = pm.name;
                  redraw();
                  this.style.color = '#198754';
                  this.innerHTML = '<i class="fas fa-check"></i> ' + pm.name;
                }
              });
              coordsEl.parentNode.appendChild(pmDiv);
            }

            // Blur handler: when user edits to a new value, offer "Add to dict"
            if (inp) {
              inp.setAttribute('data-orig-recog', fixedText);
              inp.addEventListener('blur', function() {
                const newVal = this.value.trim();
                const origVal = this.getAttribute('data-orig-recog') || '';
                const parent = coordsEl ? coordsEl.parentNode : null;
                if (!parent) return;
                const prevWd = parent.querySelector('.ba-add-word');
                if (prevWd) prevWd.remove();
                if (newVal.length >= 2 && newVal !== origVal) {
                  const wdDiv = document.createElement('div');
                  wdDiv.className = 'ba-add-word';
                  wdDiv.style.cssText = 'font-size:0.6rem; color:#6c757d; cursor:pointer; padding:1px 0;';
                  wdDiv.innerHTML = '<i class="fas fa-spell-check" style="font-size:0.5rem"></i> Add "' + newVal.replace(/</g,'&lt;') + '" to dict';
                  wdDiv.addEventListener('click', function() {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.5rem"></i> Adding...';
                    const isPlace = bulkPlaceLabels.includes(label);
                    const url = isPlace ? '{{ route("admin.ai.htr.addTown") }}' : '{{ route("admin.ai.htr.addWord") }}';
                    const body = isPlace ? { name: newVal, province: '', district: '' } : { word: newVal };
                    fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}'}, body:JSON.stringify(body) })
                    .then(r => r.json())
                    .then(d => { this.style.color = d.success ? '#198754' : '#dc3545'; this.innerHTML = d.success ? '<i class="fas fa-check"></i> Added' : '<i class="fas fa-times"></i> ' + (d.error||'Failed'); })
                    .catch(() => { this.innerHTML = '<i class="fas fa-times"></i> Error'; });
                  });
                  parent.appendChild(wdDiv);
                }
              });
            }
          } else if (error) {
            const errDiv = document.createElement('div');
            errDiv.className = 'ba-recog';
            errDiv.style.cssText = 'font-size:0.7rem; color:#999;';
            errDiv.textContent = '⚠ ' + error;
            coordsEl.parentNode.appendChild(errDiv);
          }
        }
        redraw();
        btn.innerHTML = '<i class="fas fa-brain me-1"></i>Done (' + count + ')';
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-brain me-1"></i>Recognise'; }, 5000);
      } else {
        alert(data.error || 'Recognition failed');
      }
    })
    .catch(err => {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-brain me-1"></i>Recognise';
      alert('Error: ' + err.message);
    });
  };

  // Recognise a single field after drag/resize/draw
  function recogniseSingleField(annIdx) {
    const ann = annotations[annIdx];
    if (!ann || !images[imgIdx]) return;
    const entry = images[imgIdx];
    const label = ann.label;

    // Show "Recognising..." badge on the field
    const coordsEl = document.getElementById('ba-coords-' + annIdx);
    if (coordsEl) {
      const prev = coordsEl.parentNode.querySelector('.ba-recog');
      if (prev) prev.remove();
      const badge = document.createElement('div');
      badge.className = 'ba-recog';
      badge.style.cssText = 'font-size:0.75rem; color:#0d6efd;';
      badge.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.6rem"></i> Recognising...';
      coordsEl.parentNode.appendChild(badge);
    }

    fetch('{{ route("admin.ai.htr.fsOverlayRecognise") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        annotations: [{ label: ann.label, x: ann.x, y: ann.y, w: ann.w, h: ann.h }],
      }),
    })
    .then(r => r.ok ? r.json() : Promise.reject('Server error'))
    .then(data => {
      if (!data.success || !data.results || !data.results[label]) return;
      const result = data.results[label];
      const text = result.text || '';
      if (!text) return;

      let cleanText = dedupeRepeats(text);
      let fixedText = cleanText;
      if (label === 'Event Date') fixedText = autoFixDate(cleanText);
      else if (label === 'Age') fixedText = spellcheckAge(cleanText);

      // Overwrite field with recognised text — but skip CSV-prefilled fields (names are reference data)
      const csvPrefilledFields = ['Husband Name', 'Spouse'];
      const inp = document.querySelector('.ba-edit-input[data-field-idx="' + annIdx + '"]');
      if (inp && !csvPrefilledFields.includes(label)) {
        inp.value = fixedText;
        entry.fields[label] = fixedText;
        if (annotations[annIdx]) annotations[annIdx].value = fixedText;
      }

      // Show result below coords
      if (coordsEl) {
        const prev = coordsEl.parentNode.querySelector('.ba-recog');
        if (prev) prev.remove();
        const recogDiv = document.createElement('div');
        recogDiv.className = 'ba-recog';
        recogDiv.style.cssText = 'font-size:0.75rem; color:#c00; cursor:pointer; padding:2px 0; border-top:1px dashed #ddd; margin-top:2px;';
        recogDiv.title = 'Click to use this value';
        recogDiv.innerHTML = '<i class="fas fa-robot" style="font-size:0.6rem"></i> ' +
          '<span style="text-decoration:underline dotted">' + fixedText.replace(/</g,'&lt;') + '</span>' +
          (fixedText !== text ? ' <span style="font-size:0.6rem;color:#999">(was: ' + text.replace(/</g,'&lt;') + ')</span>' : '');
        recogDiv.addEventListener('click', function() {
          if (inp) {
            inp.value = fixedText;
            entry.fields[label] = fixedText;
            if (annotations[annIdx]) annotations[annIdx].value = fixedText;
            redraw();
            this.style.color = '#198754';
            this.innerHTML = '<i class="fas fa-check"></i> Accepted';
          }
        });
        coordsEl.parentNode.appendChild(recogDiv);

        // "Add to dictionary" button for place fields — only when NOT already in dict
        const placeLabels = ['Place of Marriage', 'District', 'Province'];
        const isExactPlaceMatch = result.place_match && result.place_match.match_type === 'exact';
        if (placeLabels.includes(label) && fixedText.length >= 2 && !isExactPlaceMatch) {
          const hasGoodMatch = result.place_match && (result.place_match.confidence || 0) >= 0.9;
          if (!hasGoodMatch) {
            const addDiv = document.createElement('div');
            addDiv.style.cssText = 'font-size:0.65rem; color:#6c757d; cursor:pointer; padding:1px 0;';
            addDiv.title = 'Add "' + fixedText + '" to SA towns dictionary';
            addDiv.innerHTML = '<i class="fas fa-plus-circle" style="font-size:0.55rem"></i> Add "' + fixedText.replace(/</g,'&lt;') + '" to dictionary';
            addDiv.addEventListener('click', function() {
              const townName = inp ? inp.value.trim() : fixedText;
              if (!townName) return;
              this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.55rem"></i> Adding...';
              fetch('{{ route("admin.ai.htr.addTown") }}', {
                method: 'POST',
                headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}'},
                body: JSON.stringify({ name: townName, province: '', district: '' }),
              })
              .then(r => r.json())
              .then(d => {
                if (d.success) {
                  this.style.color = '#198754';
                  this.innerHTML = '<i class="fas fa-check"></i> Added (' + (d.stats?.sa_towns || '') + ' towns)';
                } else {
                  this.style.color = '#dc3545';
                  this.innerHTML = '<i class="fas fa-times"></i> ' + (d.error || 'Failed');
                }
              })
              .catch(() => { this.innerHTML = '<i class="fas fa-times"></i> Error'; });
            });
            coordsEl.parentNode.appendChild(addDiv);
          }
        }

        // Show place match badge if available — clickable to replace text
        if (result.place_match) {
          const pm = result.place_match;
          const pmDiv = document.createElement('div');
          pmDiv.style.cssText = 'font-size:0.7rem; color:#0d6efd; padding:1px 0; cursor:pointer;';
          pmDiv.title = 'Click to use: ' + (pm.name || '');
          const conf = Math.round((pm.confidence || 0) * 100);
          const icon = pm.match_type === 'exact' ? 'fa-map-marker-alt' : 'fa-search-location';
          let info = '<i class="fas ' + icon + '" style="font-size:0.6rem"></i> <span style="text-decoration:underline dotted">' + (pm.name || '').replace(/</g,'&lt;') + '</span>';
          if (pm.province) info += ' <span style="color:#666">(' + (pm.historical_province || pm.province) + ')</span>';
          info += ' <span style="font-size:0.6rem;color:#999">' + conf + '%</span>';
          pmDiv.innerHTML = info;
          pmDiv.addEventListener('click', function() {
            if (inp && pm.name) {
              inp.value = pm.name;
              entry.fields[label] = pm.name;
              if (annotations[annIdx]) annotations[annIdx].value = pm.name;
              redraw();
              this.style.color = '#198754';
              this.innerHTML = '<i class="fas fa-check"></i> ' + pm.name;
            }
          });
          coordsEl.parentNode.appendChild(pmDiv);
        }

        // Attach blur handler: when user edits field to a new value, offer "Add to dict"
        if (inp) {
          inp.setAttribute('data-orig-recog', fixedText);
          inp.addEventListener('blur', function() {
            const newVal = this.value.trim();
            const origVal = this.getAttribute('data-orig-recog') || '';
            const parent = coordsEl ? coordsEl.parentNode : null;
            if (!parent) return;
            // Remove previous add-word-dict element
            const prevWd = parent.querySelector('.ba-add-word');
            if (prevWd) prevWd.remove();
            // Show "Add to dict" if edited value differs from recognition and is >= 2 chars
            if (newVal.length >= 2 && newVal !== origVal) {
              const wdDiv = document.createElement('div');
              wdDiv.className = 'ba-add-word';
              wdDiv.style.cssText = 'font-size:0.6rem; color:#6c757d; cursor:pointer; padding:1px 0;';
              wdDiv.title = 'Add "' + newVal + '" to dictionary';
              wdDiv.innerHTML = '<i class="fas fa-spell-check" style="font-size:0.5rem"></i> Add "' + newVal.replace(/</g,'&lt;') + '" to dict';
              wdDiv.addEventListener('click', function() {
                this.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:0.5rem"></i> Adding...';
                const isPlace = ['Place of Marriage', 'District', 'Province'].includes(label);
                const url = isPlace ? '{{ route("admin.ai.htr.addTown") }}' : '{{ route("admin.ai.htr.addWord") }}';
                const body = isPlace ? { name: newVal, province: '', district: '' } : { word: newVal };
                fetch(url, {
                  method: 'POST',
                  headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}'},
                  body: JSON.stringify(body),
                })
                .then(r => r.json())
                .then(d => {
                  if (d.success) { this.style.color = '#198754'; this.innerHTML = '<i class="fas fa-check"></i> Added'; }
                  else { this.style.color = '#dc3545'; this.innerHTML = '<i class="fas fa-times"></i> ' + (d.error || 'Failed'); }
                })
                .catch(() => { this.innerHTML = '<i class="fas fa-times"></i> Error'; });
              });
              parent.appendChild(wdDiv);
            }
          });
        }
      }
      redraw();
    })
    .catch(() => {
      if (coordsEl) {
        const prev = coordsEl.parentNode.querySelector('.ba-recog');
        if (prev) prev.remove();
      }
    });
  }

  window.baAutoPlace = function() {
    autoPlaceFields();
    redraw();
  };

  function buildFieldList() {
    const entry = images[imgIdx];
    const container = document.getElementById('ba-fields');
    container.innerHTML = '';

    // Fields to clear (don't pre-fill from CSV — user reads from document)
    const CLEAR_FIELDS = ['Event Date'];

    COLUMNS.forEach(function(col, i) {
      let val = entry.fields[col] || '';
      if (CLEAR_FIELDS.includes(col)) val = '';
      // Don't modify Age CSV value — display as-is from spreadsheet
      const div = document.createElement('div');
      div.className = 'ba-field' + (i === fieldIdx ? ' active' : '');
      div.dataset.idx = i;

      const skipBtn = '<button class="btn btn-sm btn-outline-secondary ba-skip-btn" onclick="event.stopPropagation(); baSkipField(' + i + ')" title="Skip / unskip"><i class="fas fa-forward"></i></button>';
      const escapedVal = (val || '').replace(/"/g, '&quot;');

      div.innerHTML = skipBtn +
        '<div class="ba-label" style="color:' + COLORS[i % COLORS.length] + '">' + (i + 1) + '. ' + displayLabel(col) + '</div>' +
        '<input class="ba-edit-input" type="text" value="' + escapedVal + '" data-field-idx="' + i + '" placeholder="Type value..." onclick="event.stopPropagation()">' +
        '<div class="ba-coords" id="ba-coords-' + i + '"></div>';

      const input = div.querySelector('.ba-edit-input');
      input.addEventListener('change', function() {
        const idx = parseInt(this.dataset.fieldIdx);
        entry.fields[COLUMNS[idx]] = this.value;
        if (annotations[idx]) annotations[idx].value = this.value;
        redraw();
      });
      input.addEventListener('keydown', function(e) {
        e.stopPropagation();
        if (e.key === 'Enter') { this.blur(); advanceToNextField(); highlightField(); }
      });
      div.onclick = function() {
        if (!skipped.includes(i)) { fieldIdx = i; highlightField(); }
      };
      container.appendChild(div);
    });

    // Auto-skip non-allowed fields
    COLUMNS.forEach(function(col, i) {
      if (shouldSkip(col)) skipped.push(i);
    });

    highlightField();
    updateProgress();
  }

  window.baSkipField = function(idx) {
    if (!skipped.includes(idx)) {
      skipped.push(idx);
      annotations[idx] = null;
    } else {
      skipped = skipped.filter(i => i !== idx);
    }
    if (skipped.includes(fieldIdx)) advanceToNextField();
    highlightField();
    updateProgress();
    redraw();
  };

  function advanceToNextField() {
    let next = fieldIdx + 1;
    while (next < COLUMNS.length && skipped.includes(next)) next++;
    if (next < COLUMNS.length) fieldIdx = next;
  }

  function highlightField() {
    document.querySelectorAll('.ba-field').forEach(function(el, i) {
      el.classList.toggle('active', i === fieldIdx && !skipped.includes(i));
      el.classList.toggle('done', annotations[i] && annotations[i] !== null);
      el.classList.toggle('skipped', skipped.includes(i));
    });
    const active = document.querySelector('.ba-field.active');
    if (active) active.scrollIntoView({ block: 'nearest' });
    // Auto-select text in the active field's input
    const inp = document.querySelector('.ba-edit-input[data-field-idx="' + fieldIdx + '"]');
    if (inp) { inp.focus(); inp.select(); }
  }

  function updateProgress() {
    const active = COLUMNS.length - skipped.length;
    const done = annotations.filter(a => a !== null && a !== undefined).length;
    const pct = active > 0 ? (done / active * 100) : 100;
    document.getElementById('ba-progress').style.width = pct + '%';
    document.getElementById('ba-save-btn').disabled = done < 1;
  }

  function updateCounters() {
    document.getElementById('ba-done-count').textContent = sessionDone;
    document.getElementById('ba-remaining-count').textContent = Math.max(0, images.length - imgIdx - 1);
    document.getElementById('ba-fields-count').textContent = sessionFields;
    document.getElementById('ba-prev-btn').disabled = imgIdx <= 0;
  }

  // ── Drawing ──
  function pos(e) {
    const r = cvs.getBoundingClientRect();
    return { x: (e.clientX - r.left) / scale, y: (e.clientY - r.top) / scale };
  }

  cvs.addEventListener('mousedown', function(e) {
    if (e.button !== 0) return;
    const p = pos(e);

    if (currentTool === 'hand') {
      panning = true;
      panStartX = e.clientX; panStartY = e.clientY;
      panScrollX = offsetX; panScrollY = offsetY;
      cvs.style.cursor = 'grabbing';
      e.preventDefault();
      return;
    }

    if (currentTool === 'select') {
      const rh = hitResize(p.x, p.y);
      if (rh) { resizing = true; resizeIdx = rh.idx; resizeHandle = rh.handle; return; }
      const hit = hitTest(p.x, p.y);
      if (hit >= 0) {
        dragging = true; dragIdx = hit;
        dragOffX = p.x - annotations[hit].x;
        dragOffY = p.y - annotations[hit].y;
        fieldIdx = hit;
        highlightField();
        return;
      }
      return;
    }

    if (currentTool === 'draw') { drawing = true; sx = p.x; sy = p.y; }
  });

  document.addEventListener('mousemove', function(e) {
    if (panning) {
      offsetX = panScrollX + (e.clientX - panStartX);
      offsetY = panScrollY + (e.clientY - panStartY);
      cvs.style.transform = 'translate(' + offsetX + 'px, ' + offsetY + 'px)';
    }
  });
  document.addEventListener('mouseup', function(e) {
    if (panning) { panning = false; cvs.style.cursor = 'grab'; }
  });

  cvs.addEventListener('mousemove', function(e) {
    if (panning) return;
    const p = pos(e);

    if (resizing && annotations[resizeIdx]) {
      const a = annotations[resizeIdx];
      if (resizeHandle === 'right' || resizeHandle === 'br' || resizeHandle === 'tr') a.w = Math.max(10, p.x - a.x);
      if (resizeHandle === 'bottom' || resizeHandle === 'br' || resizeHandle === 'bl') a.h = Math.max(10, p.y - a.y);
      if (resizeHandle === 'left' || resizeHandle === 'tl' || resizeHandle === 'bl') { const oldRight = a.x + a.w; a.x = Math.min(p.x, oldRight - 10); a.w = oldRight - a.x; }
      if (resizeHandle === 'top' || resizeHandle === 'tl' || resizeHandle === 'tr') { const oldBottom = a.y + a.h; a.y = Math.min(p.y, oldBottom - 10); a.h = oldBottom - a.y; }
      redraw(); return;
    }

    if (dragging && annotations[dragIdx]) {
      annotations[dragIdx].x = Math.max(0, p.x - dragOffX);
      annotations[dragIdx].y = Math.max(0, p.y - dragOffY);
      redraw(); return;
    }

    if (drawing) {
      redraw();
      ctx.save();
      ctx.strokeStyle = COLORS[fieldIdx % COLORS.length];
      ctx.lineWidth = 2 / scale;
      ctx.setLineDash([4 / scale, 4 / scale]);
      ctx.strokeRect(sx * scale, sy * scale, (p.x - sx) * scale, (p.y - sy) * scale);
      ctx.restore();
      return;
    }

    if (currentTool === 'select') {
      const rh = hitResize(p.x, p.y);
      if (rh) {
        const cursors = { left:'ew-resize', right:'ew-resize', top:'ns-resize', bottom:'ns-resize', tl:'nwse-resize', br:'nwse-resize', tr:'nesw-resize', bl:'nesw-resize' };
        cvs.style.cursor = cursors[rh.handle] || 'nwse-resize';
      }
      else if (hitTest(p.x, p.y) >= 0) { cvs.style.cursor = 'move'; }
      else { cvs.style.cursor = 'default'; }
    }
  });

  cvs.addEventListener('mouseup', function(e) {
    if (panning) { panning = false; return; }

    if (resizing) {
      resizing = false;
      const a = annotations[resizeIdx];
      if (a) {
        savedPositions[a.label] = { x: a.x / imgNatW, y: a.y / imgNatH, w: a.w / imgNatW, h: a.h / imgNatH };
        const c = document.getElementById('ba-coords-' + resizeIdx);
        if (c) c.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
        persistPositions();
        recogniseSingleField(resizeIdx);
      }
      fieldIdx = resizeIdx;
      highlightField();
      const inp = document.querySelector('.ba-edit-input[data-field-idx="' + resizeIdx + '"]');
      if (inp) { inp.focus(); inp.select(); }
      redraw(); return;
    }

    if (dragging) {
      dragging = false;
      const a = annotations[dragIdx];
      if (a) {
        a.x = Math.round(a.x); a.y = Math.round(a.y);
        savedPositions[a.label] = { x: a.x / imgNatW, y: a.y / imgNatH, w: a.w / imgNatW, h: a.h / imgNatH };
        const c = document.getElementById('ba-coords-' + dragIdx);
        if (c) c.textContent = Math.round(a.x) + ',' + Math.round(a.y) + ' ' + Math.round(a.w) + '×' + Math.round(a.h);
        persistPositions();
        recogniseSingleField(dragIdx);
      }
      // Focus the field input in the sidebar
      fieldIdx = dragIdx;
      highlightField();
      const inp = document.querySelector('.ba-edit-input[data-field-idx="' + dragIdx + '"]');
      if (inp) { inp.focus(); inp.select(); }
      redraw(); return;
    }

    if (!drawing) return;
    drawing = false;
    const p = pos(e);
    let x = Math.min(sx, p.x), y = Math.min(sy, p.y);
    let w = Math.abs(p.x - sx), h = Math.abs(p.y - sy);
    if (w < 5 || h < 5) { redraw(); return; }

    // If in crop mode, do manual crop instead of annotation
    if (cropMode) {
      handleCropDraw(x, y, w, h);
      return;
    }

    const entry = images[imgIdx];
    const col = COLUMNS[fieldIdx];
    const val = entry.fields[col] || '';

    annotations[fieldIdx] = { label: col, value: val, x: Math.round(x), y: Math.round(y), w: Math.round(w), h: Math.round(h) };
    savedPositions[col] = { x: x / imgNatW, y: y / imgNatH, w: w / imgNatW, h: h / imgNatH };
    persistPositions();
    recogniseSingleField(fieldIdx);

    const coordsEl = document.getElementById('ba-coords-' + fieldIdx);
    if (coordsEl) coordsEl.textContent = Math.round(x) + ',' + Math.round(y) + ' ' + Math.round(w) + '×' + Math.round(h);

    advanceToNextField();
    highlightField();
    updateProgress();
    updateCounters();
    redraw();
  });

  function persistPositions() {
    if (!currentFormType) return;
    // Also save to localStorage as fallback in case session expires
    try { localStorage.setItem('fs-overlay-pos-' + currentFormType, JSON.stringify(savedPositions)); } catch(e) {}
    fetch('{{ route("admin.ai.htr.fsOverlaySavePositions") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}'},
      body: JSON.stringify({ form_type: currentFormType, positions: savedPositions }),
    })
    .then(r => {
      if (!r.ok) {
        // Session expired (419) or other error — refresh CSRF token
        if (r.status === 419) {
          console.warn('[FS Overlay] CSRF expired — positions saved to localStorage, refresh page to re-sync');
          const bar = document.getElementById('ba-image-name');
          if (bar) bar.textContent = bar.textContent.replace(/ — CSRF expired.*/, '') + ' — CSRF expired, refresh page to save to server';
        }
        return;
      }
      return r.json();
    })
    .catch(() => {});
  }

  function loadSavedPositions(cb) {
    if (!currentFormType) { savedPositions = {}; if (cb) cb(); return; }
    fetch('{{ route("admin.ai.htr.fsOverlayLoadPositions") }}?form_type=' + encodeURIComponent(currentFormType), {
      credentials: 'same-origin',
    })
    .then(r => r.json())
    .then(data => {
      savedPositions = data.positions || {};
      // Fallback: merge localStorage positions if server is empty
      if (!Object.keys(savedPositions).length) {
        try {
          const ls = JSON.parse(localStorage.getItem('fs-overlay-pos-' + currentFormType) || '{}');
          if (Object.keys(ls).length) {
            savedPositions = ls;
            console.log('[FS Overlay] Loaded positions from localStorage fallback');
          }
        } catch(e) {}
      }
      console.log('[FS Overlay] Loaded positions for:', currentFormType, 'fields:', Object.keys(savedPositions));
      if (cb) cb();
    })
    .catch(() => {
      // Server unreachable — try localStorage
      try {
        savedPositions = JSON.parse(localStorage.getItem('fs-overlay-pos-' + currentFormType) || '{}');
      } catch(e) { savedPositions = {}; }
      if (cb) cb();
    });
  }

  function redraw() {
    if (!img) return;
    ctx.clearRect(0, 0, cvs.width, cvs.height);
    ctx.drawImage(img, 0, 0, img.width * scale, img.height * scale);

    annotations.forEach(function(ann, i) {
      if (!ann) return;
      const color = COLORS[i % COLORS.length];
      const isActive = i === fieldIdx;
      ctx.save();

      // Box fill (semi-transparent)
      ctx.fillStyle = color;
      ctx.globalAlpha = isActive ? 0.15 : 0.08;
      ctx.fillRect(ann.x * scale, ann.y * scale, ann.w * scale, ann.h * scale);

      // Box border
      ctx.globalAlpha = 1;
      ctx.strokeStyle = color;
      ctx.lineWidth = isActive ? 3 : 1.5;
      ctx.strokeRect(ann.x * scale, ann.y * scale, ann.w * scale, ann.h * scale);

      // Label background (bigger, bolder)
      const labelText = (i + 1) + '. ' + displayLabel(ann.label);
      ctx.font = (isActive ? 'bold 14px' : '13px') + ' sans-serif';
      const tw = ctx.measureText(labelText).width + 12;
      ctx.fillStyle = color;
      ctx.globalAlpha = 0.92;
      ctx.fillRect(ann.x * scale, ann.y * scale - 20, tw, 20);

      // Label text
      ctx.globalAlpha = 1;
      ctx.fillStyle = '#fff';
      ctx.fillText(labelText, ann.x * scale + 5, ann.y * scale - 5);

      // Value preview below box (bigger)
      if (ann.value) {
        ctx.font = 'bold 12px sans-serif';
        const vw = ctx.measureText(ann.value).width + 10;
        ctx.fillStyle = 'rgba(255,255,255,0.92)';
        ctx.fillRect(ann.x * scale, (ann.y + ann.h) * scale + 2, vw, 16);
        ctx.fillStyle = '#333';
        ctx.fillText(ann.value, ann.x * scale + 4, (ann.y + ann.h) * scale + 14);
      }

      // Highlight detected printed label (dashed blue box around the label text)
      if (ann.labelBox) {
        const lb = ann.labelBox;
        ctx.strokeStyle = '#0d6efd';
        ctx.lineWidth = 2;
        ctx.setLineDash([4, 3]);
        ctx.globalAlpha = 0.8;
        ctx.strokeRect(lb.x * scale, lb.y * scale, lb.w * scale, lb.h * scale);
        ctx.setLineDash([]);
        // Label tag above the printed label box
        const tagText = '⎯ ' + (ann.labelText || 'label');
        ctx.font = '10px sans-serif';
        const tagW = ctx.measureText(tagText).width + 6;
        ctx.fillStyle = '#0d6efd';
        ctx.globalAlpha = 0.85;
        ctx.fillRect(lb.x * scale, lb.y * scale - 14, tagW, 14);
        ctx.globalAlpha = 1;
        ctx.fillStyle = '#fff';
        ctx.fillText(tagText, lb.x * scale + 3, lb.y * scale - 3);
        // Draw a connecting line from label to answer box
        ctx.strokeStyle = '#0d6efd';
        ctx.lineWidth = 1;
        ctx.setLineDash([2, 2]);
        ctx.globalAlpha = 0.5;
        ctx.beginPath();
        ctx.moveTo((lb.x + lb.w) * scale, (lb.y + lb.h / 2) * scale);
        ctx.lineTo(ann.x * scale, (ann.y + ann.h / 2) * scale);
        ctx.stroke();
        ctx.setLineDash([]);
      }

      ctx.restore();
    });
  }

  // ── Keyboard shortcuts ──
  document.addEventListener('keydown', function(e) {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.key === 'Enter') {
      // If Event Date is filled in, Save & Next
      const eventDateInput = document.querySelector('.ba-edit-input[data-field-idx]');
      const eventDateIdx = COLUMNS.indexOf('Event Date');
      let eventDateFilled = false;
      if (eventDateIdx >= 0) {
        const inp = document.querySelector('.ba-edit-input[data-field-idx="' + eventDateIdx + '"]');
        eventDateFilled = inp && inp.value.trim().length > 0;
      }
      if (eventDateFilled) {
        e.preventDefault();
        baSaveAndNext();
      } else {
        advanceToNextField(); highlightField(); e.preventDefault();
      }
    }
    if (e.key === 'Backspace') {
      if (annotations[fieldIdx]) { annotations[fieldIdx] = null; highlightField(); updateProgress(); redraw(); }
      e.preventDefault();
    }
    if (e.key === 'ArrowRight') {
      if (!skipped.includes(fieldIdx)) { skipped.push(fieldIdx); annotations[fieldIdx] = null; }
      advanceToNextField(); highlightField(); updateProgress(); redraw(); e.preventDefault();
    }
    if (e.key === 'ArrowLeft') { fieldIdx = Math.max(0, fieldIdx - 1); highlightField(); e.preventDefault(); }
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); baSaveAndNext(); }
    if (e.key === 'h' || e.key === 'H') baSetTool('hand');
    if (e.key === 'r' || e.key === 'R') baSetTool('draw');
    if (e.key === 'v' || e.key === 'V') baSetTool('select');
  });

  // ── Zoom ──
  window.baZoomIn = function() { scale *= 1.2; cvs.width = imgNatW * scale; cvs.height = imgNatH * scale; redraw(); };
  window.baZoomOut = function() { scale = Math.max(0.1, scale / 1.2); cvs.width = imgNatW * scale; cvs.height = imgNatH * scale; redraw(); };
  window.baZoomFit = function() { scale = wrap.clientWidth / imgNatW; cvs.width = imgNatW * scale; cvs.height = imgNatH * scale; offsetX = 0; offsetY = 0; cvs.style.transform = ''; redraw(); };

  wrap.addEventListener('wheel', function(e) {
    if (!img) return;
    e.preventDefault();
    const rect = wrap.getBoundingClientRect();
    const mx = e.clientX - rect.left + wrap.scrollLeft;
    const my = e.clientY - rect.top + wrap.scrollTop;
    const oldScale = scale;
    scale = e.deltaY < 0 ? Math.min(scale * 1.15, 10) : Math.max(scale / 1.15, 0.1);
    cvs.width = imgNatW * scale; cvs.height = imgNatH * scale;
    redraw();
    const ratio = scale / oldScale;
    wrap.scrollLeft = mx * ratio - (e.clientX - rect.left);
    wrap.scrollTop = my * ratio - (e.clientY - rect.top);
  }, { passive: false });

  // ── Navigation ──
  window.baPrev = function() { if (imgIdx > 0) { imgIdx -= 2; nextImage(); } };
  window.baSkip = function() {
    const entry = images[imgIdx];
    const folder = document.getElementById('ba-folder').value.trim();
    // Move to rework folder
    fetch('{{ route("admin.ai.htr.fsOverlayManualCrop") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({ action: 'skip', image_path: entry.path, folder: folder }),
    }).catch(() => {});
    nextImage();
  };

  // ── Save ──
  window.baSaveAndNext = function() {
    const entry = images[imgIdx];
    const btn = document.getElementById('ba-save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

    // Update savedPositions from ALL current annotations — save as % of image
    console.log('[FS Overlay] Saving positions, image size:', imgNatW, 'x', imgNatH);
    annotations.forEach(function(ann) {
      if (ann && imgNatW > 0 && imgNatH > 0) {
        savedPositions[ann.label] = {
          x: ann.x / imgNatW,
          y: ann.y / imgNatH,
          w: ann.w / imgNatW,
          h: ann.h / imgNatH,
        };
      }
    });

    // Save positions to server, then save annotation
    persistPositions();

    fetch('{{ route("admin.ai.htr.fsOverlaySave") }}', {
      method: 'POST',
      headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      body: JSON.stringify({
        image_path: entry.path,
        fname: entry.fname,
        fields: entry.fields,
        annotations: annotations.filter(a => a),
        folder: document.getElementById('ba-folder').value.trim(),
      }),
    })
    .then(r => r.json())
    .then(data => {
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Save & Next';
      btn.disabled = false;
      if (data.success) {
        sessionDone++;
        sessionFields += annotations.filter(a => a).length;
        updateCounters();
        nextImage();
      } else {
        alert(data.error || 'Save failed');
      }
    })
    .catch(err => {
      btn.innerHTML = '<i class="fas fa-save me-1"></i>Save & Next';
      btn.disabled = false;
      alert(err.message);
    });
  };
})();
</script>
@endpush
