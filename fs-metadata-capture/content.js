/* FS Metadata Capture — content script v2 */

(() => {
  if (window.__fsCaptureLoaded) return;
  window.__fsCaptureLoaded = true;

  let panel = null;
  let autoMode = false;
  let autoTimer = null;
  let autoStartImage = 0; // the image we started auto-mode on
  let lastProcessedArk = ''; // prevent duplicate downloads

  // ── Standardized field mapping ───────────────────────────
  // Maps FS table header variations → standard CSV column names
  // This ensures different collections all map to the same CSV columns

  const FIELD_MAP = {
    // Name
    'name':             'Name',
    'full name':        'Name',
    'given name':       'Name',
    'first name':       'Name',
    'surname':          'Surname',
    'last name':        'Surname',
    // Sex
    'sex':              'Sex',
    'gender':           'Sex',
    // Age
    'age':              'Age',
    'age at death':     'Age',
    'age at event':     'Age',
    // Birth
    'birth year (estimated)': 'Birth Year',
    'birth year':       'Birth Year',
    'estimated birth year': 'Birth Year',
    'birthdate':        'Birth Date',
    'birth date':       'Birth Date',
    'birthplace':       'Birth Place',
    'birth place':      'Birth Place',
    // Event
    'event type':       'Event Type',
    'event date':       'Event Date',
    'event place':      'Event Place',
    'event year':       'Event Year',
    // Death specific
    'death date':       'Event Date',
    'death place':      'Event Place',
    'death year':       'Event Year',
    'cause of death':   'Cause of Death',
    // Marriage
    'marriage date':    'Event Date',
    'marriage place':   'Event Place',
    'marriage year':    'Event Year',
    // Relations
    'father':           'Father',
    "father's name":    'Father',
    'mother':           'Mother',
    "mother's name":    'Mother',
    'spouse':           'Spouse',
    "spouse's name":    'Spouse',
    // Other common fields
    'race':             'Race',
    'color':            'Race',
    'marital status':   'Marital Status',
    'occupation':       'Occupation',
    'residence':        'Residence',
    'relationship':     'Relationship',
    'relationship to head': 'Relationship',
    'nationality':      'Nationality',
    'religion':         'Religion',
    'district':         'District',
    'registration number': 'Registration No',
    'entry number':     'Entry No',
    'page number':      'Page No',
    'reference number': 'Reference No',
    'informant':        'Informant',
    'registrar':        'Registrar',
  };

  // All possible standard columns (in CSV output order)
  const ALL_STANDARD_COLUMNS = [
    'Name', 'Surname', 'Sex', 'Age', 'Birth Year', 'Birth Date', 'Birth Place',
    'Event Type', 'Event Date', 'Event Place', 'Event Year', 'Cause of Death',
    'Father', 'Mother', 'Spouse', 'Race', 'Marital Status', 'Occupation',
    'Residence', 'Relationship', 'Nationality', 'Religion', 'District',
    'Registration No', 'Entry No', 'Page No', 'Reference No',
    'Informant', 'Registrar',
  ];

  // Map a raw FS header to a standard column name
  function mapHeader(rawHeader) {
    const h = rawHeader.toLowerCase().trim();
    // Direct match
    if (FIELD_MAP[h]) return FIELD_MAP[h];
    // Partial match
    for (const [key, val] of Object.entries(FIELD_MAP)) {
      if (h.includes(key) || key.includes(h)) return val;
    }
    return null;
  }

  // ── FamilySearch helpers ─────────────────────────────────

  function extractArkId() {
    const m = window.location.href.match(/ark:\/61903\/3:1:([A-Za-z0-9-]+)/);
    return m ? m[1] : '';
  }

  function getImageNumber() {
    const inp = document.querySelector('input[aria-label="Image Number"]');
    return inp ? inp.value.trim() : '';
  }

  function getImageTotal() {
    const els = document.querySelectorAll('span, div, label');
    for (const el of els) {
      const m = el.textContent.trim().match(/of\s+(\d+)/i);
      if (m) return m[1];
    }
    return '';
  }

  // Scrape the FS metadata table — returns { fields: [{header, stdName, value}], collection }
  function scrapeMetadataTable() {
    const result = { fields: [], collection: '' };
    const tables = document.querySelectorAll('table');

    for (const table of tables) {
      const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent.trim());
      const headersLower = headers.map(h => h.toLowerCase());

      // Skip tables that are clearly not the metadata table
      if (headers.length < 2) continue;
      // Skip "Attach" / "More" only tables
      const realHeaders = headers.filter(h => !['more', 'attach', ''].includes(h.toLowerCase()));
      if (realHeaders.length < 2) continue;

      const trs = table.querySelectorAll('tbody tr');
      if (trs.length === 0) continue;

      const cells = trs[0].querySelectorAll('td');

      // FS table structure (confirmed from debug):
      //   Headers (9): Attach to Tree | Name | Sex | Age | Birth Year | Event Type | Event Date | Event Place | <PERSON NAME>
      //   Cells   (8): More | Attach | Female | 1 years | 1941 | Death | 5 February 1942 | Komgha...
      //
      // The person name is the LAST header (no matching cell).
      // First 2 headers (Attach to Tree, Name) map to junk cells (More, Attach).
      // Real data starts at header index 2 → cell index 2.

      // Extract person name from last header (if it doesn't match a known field name)
      const lastHeader = headers[headers.length - 1] || '';
      const lastHeaderLower = lastHeader.toLowerCase();
      const knownFieldWords = ['name','sex','age','birth','event','place','date','type','more','attach','year','race','occupation','marital','father','mother','spouse','residence','relationship'];
      const isPersonName = lastHeader.length > 0 && !knownFieldWords.some(w => lastHeaderLower.includes(w));

      if (isPersonName) {
        result.fields.push({
          header: 'Name',
          stdName: 'Name',
          value: lastHeader,
        });
      }

      // Map remaining headers (skip first 2: "Attach to Tree" and "Name") to cells (skip first 2: "More" and "Attach")
      for (let i = 2; i < headers.length; i++) {
        const rawHeader = headers[i];
        const cell = cells[i];
        if (!cell || !rawHeader) continue;

        // Skip the last header if we already used it as person name
        if (i === headers.length - 1 && isPersonName) continue;

        const val = cell.textContent.trim();

        const stdName = mapHeader(rawHeader);
        result.fields.push({
          header: rawHeader,
          stdName: stdName || rawHeader,
          value: val || '',
        });
      }

      break;
    }

    // Collection from breadcrumb
    const bc = document.querySelector('a[href*="search/collection"]');
    if (bc) result.collection = bc.textContent.trim();

    return result;
  }

  // Find full-resolution document image URL
  function getFullImageUrl() {
    const imgs = document.querySelectorAll('img');
    for (const img of imgs) {
      const src = img.src || '';
      if (src.includes('/das/') || src.includes('/dgs/') || src.includes('/recapi/')) {
        return src;
      }
    }
    // Fallback: largest image
    let biggest = null, biggestArea = 0;
    for (const img of imgs) {
      const area = (img.naturalWidth || 0) * (img.naturalHeight || 0);
      if (area > biggestArea && img.src && !img.src.includes('icon') && !img.src.includes('logo') && !img.src.includes('avatar')) {
        biggest = img;
        biggestArea = area;
      }
    }
    if (biggest && biggestArea > 50000) return biggest.src;
    return null;
  }

  // Click Next Image
  function clickNextImage() {
    for (const btn of document.querySelectorAll('button')) {
      const label = (btn.getAttribute('aria-label') || btn.title || '').toLowerCase();
      if (label.includes('next') && (label.includes('image') || label.includes('page'))) {
        if (!btn.disabled) { btn.click(); return true; }
      }
    }
    return false;
  }

  // ── Messages ─────────────────────────────────────────────

  chrome.runtime.onMessage.addListener((msg) => {
    if (msg.action === 'showPanel') {
      autoMode = true;
      autoStartImage = parseInt(getImageNumber(), 10) || 1;
      lastProcessedArk = '';
      showPanel();
    }
  });

  // ── Panel ────────────────────────────────────────────────

  function showPanel() {
    if (panel) panel.remove();

    const arkId = extractArkId();
    const imgNum = getImageNumber();
    const imgTotal = getImageTotal();
    const scraped = scrapeMetadataTable();

    // Build dynamic field inputs
    let fieldsHtml = '';
    if (scraped.fields.length === 0) {
      fieldsHtml = '<div class="fs-rp-field fs-rp-field-wide"><label>No metadata table found</label><input type="text" id="fs-rp-manual-notes" placeholder="Type notes manually"></div>';
    } else {
      scraped.fields.forEach((f, i) => {
        fieldsHtml += `
          <div class="fs-rp-field">
            <label title="${esc(f.header)}">${esc(f.stdName)}</label>
            <input type="text" data-std="${escAttr(f.stdName)}" data-raw="${escAttr(f.header)}" value="${escAttr(f.value)}">
          </div>
        `;
      });
    }
    // Always add a Notes field
    fieldsHtml += `
      <div class="fs-rp-field">
        <label>Notes</label>
        <input type="text" data-std="Notes" data-raw="Notes" value="" placeholder="optional">
      </div>
    `;

    panel = document.createElement('div');
    panel.id = 'fs-capture-result-panel';
    panel.innerHTML = `
      <div class="fs-rp-header">
        <span class="fs-rp-title">FS Capture</span>
        <span class="fs-rp-ark">${esc(arkId) || 'No ARK'}</span>
        ${imgNum ? `<span class="fs-rp-img-num">Image ${esc(imgNum)}${imgTotal ? '/' + esc(imgTotal) : ''}</span>` : ''}
        ${autoMode ? '<span class="fs-rp-auto">AUTO</span>' : ''}
        ${scraped.collection ? `<span class="fs-rp-collection" title="${esc(scraped.collection)}">${esc(scraped.collection)}</span>` : ''}
        <button class="fs-rp-close" title="Stop &amp; Close (Escape)">&times;</button>
      </div>
      <div class="fs-rp-body">
        <div class="fs-rp-fields">${fieldsHtml}</div>
        <div class="fs-rp-actions">
          <button id="fs-rp-save-next" class="fs-btn-save-next">Save + Download + Next &rarr;</button>
          <button id="fs-rp-save" class="fs-btn-save">Save only</button>
          <button id="fs-rp-download" class="fs-btn-download">Download image</button>
          <button id="fs-rp-stop" class="fs-btn-cancel">${autoMode ? 'Stop auto (Esc)' : 'Close'}</button>
        </div>
      </div>
    `;
    document.body.appendChild(panel);
    document.addEventListener('keydown', onEscape);

    // Save + Download + Next
    panel.querySelector('#fs-rp-save-next').addEventListener('click', () => doSaveDownloadNext());

    // Save only
    panel.querySelector('#fs-rp-save').addEventListener('click', () => {
      const currentArkId = extractArkId();
      saveCurrentRow(currentArkId, (count) => {
        const btn = panel.querySelector('#fs-rp-save');
        btn.textContent = `Saved (${count})`;
        btn.disabled = true;
        btn.style.opacity = '0.6';
      });
    });

    // Download image only
    panel.querySelector('#fs-rp-download').addEventListener('click', () => {
      downloadFullImage(extractArkId());
    });

    // Stop / Close
    panel.querySelector('#fs-rp-stop').addEventListener('click', () => stopAndClose());
    panel.querySelector('.fs-rp-close').addEventListener('click', () => stopAndClose());

    // No metadata? Auto-skip this image
    if (autoMode && scraped.fields.length === 0) {
      const btn = panel.querySelector('#fs-rp-save-next');
      if (btn) btn.textContent = 'No metadata — skipping...';
      autoTimer = setTimeout(() => {
        if (autoMode) {
          clickNextImage();
          setTimeout(() => showPanel(), 3000);
        }
      }, 500);
      return; // don't show the panel for long
    }

    // Auto-mode: automatically save + download + next after a short delay
    if (autoMode && scraped.fields.length > 0) {
      const currentImg = parseInt(getImageNumber(), 10) || 0;
      const totalImg = parseInt(getImageTotal(), 10) || 0;
      const remaining = totalImg > 0 ? totalImg - currentImg : 999;
      const processed = currentImg - autoStartImage;
      const stopBuffer = 10; // stop when 10 images from the end

      if (remaining <= stopBuffer) {
        // Stop auto-mode — near the end
        autoMode = false;
        const btn = panel.querySelector('#fs-rp-save-next');
        btn.textContent = `Done! Processed ${processed} images (${remaining} left at end)`;
        const stopBtn = panel.querySelector('#fs-rp-stop');
        if (stopBtn) stopBtn.textContent = 'Close';
        const autoSpan = panel.querySelector('.fs-rp-auto');
        if (autoSpan) { autoSpan.textContent = 'DONE'; autoSpan.style.background = '#27ae60'; autoSpan.style.animation = 'none'; }
      } else {
        const btn = panel.querySelector('#fs-rp-save-next');
        btn.textContent = `Auto: ${currentImg}/${totalImg} (${processed} done) — saving in 2s (Esc to stop)`;
        autoTimer = setTimeout(() => {
          if (autoMode) doSaveDownloadNext();
        }, 2000);
      }
    }
  }

  function doSaveDownloadNext() {
    if (!panel) return;
    const btn = panel.querySelector('#fs-rp-save-next');
    if (btn) {
      btn.textContent = 'Saving...';
      btn.disabled = true;
    }
    const currentArkId = extractArkId();

    // Prevent downloading the same image twice (happens on last image)
    if (currentArkId && currentArkId !== lastProcessedArk) {
      lastProcessedArk = currentArkId;
      downloadFullImage(currentArkId);
      saveCurrentRow(currentArkId, () => {
        clickNextImage();
        setTimeout(() => showPanel(), 3000);
      });
    } else {
      // Same image as last — stop
      autoMode = false;
      if (btn) btn.textContent = 'Already processed — stopped';
    }
  }

  function stopAndClose() {
    autoMode = false;
    if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
    removePanel();
  }

  function downloadFullImage(arkId) {
    const imgUrl = getFullImageUrl();
    if (!imgUrl) return;
    const ext = (imgUrl.includes('.jpg') || imgUrl.includes('jpeg')) ? '.jpg' : '.png';
    chrome.runtime.sendMessage({
      action: 'downloadFile',
      url: imgUrl,
      filename: 'FamilySearch/' + (arkId || 'fs-image') + ext,
    });
  }

  function saveCurrentRow(arkId, cb) {
    // Read all field values from the panel inputs
    const fields = {};
    panel.querySelectorAll('.fs-rp-fields input[data-std]').forEach(inp => {
      const stdName = inp.getAttribute('data-std');
      if (stdName && inp.value.trim()) {
        fields[stdName] = inp.value.trim();
      }
    });

    const row = {
      arkId,
      imageNum: getImageNumber(),
      fields, // { "Name": "Singana", "Event Type": "Death", ... }
      pageUrl: window.location.href,
      timestamp: new Date().toISOString(),
    };
    chrome.runtime.sendMessage({ action: 'saveRow', row }, (resp) => {
      cb(resp?.count || '?');
    });
  }

  function onEscape(e) {
    if (e.key === 'Escape') stopAndClose();
  }

  function esc(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function escAttr(s) {
    if (!s) return '';
    return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function removePanel() {
    document.removeEventListener('keydown', onEscape);
    if (panel) { panel.remove(); panel = null; }
  }
})();
