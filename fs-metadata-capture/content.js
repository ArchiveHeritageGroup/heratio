/* FS Metadata Capture — content script v3 (multi-row) */

(() => {
  if (window.__fsCaptureLoaded) return;
  window.__fsCaptureLoaded = true;

  let panel = null;
  let autoMode = false;
  let autoTimer = null;
  let autoStartImage = 0;
  let lastProcessedArk = '';

  // ── Standardized field mapping ───────────────────────────
  const FIELD_MAP = {
    'name':             'Name',
    'full name':        'Name',
    'given name':       'Name',
    'first name':       'Name',
    'surname':          'Surname',
    'last name':        'Surname',
    'parent name':      'Father',
    "parent's name":    'Father',
    "second parent's name": 'Mother',
    'sex':              'Sex',
    'gender':           'Sex',
    'age':              'Age',
    'age at death':     'Age',
    'age at event':     'Age',
    'birth year (estimated)': 'Birth Year',
    'birth year':       'Birth Year',
    'estimated birth year': 'Birth Year',
    'birthdate':        'Birth Date',
    'birth date':       'Birth Date',
    'birthplace':       'Birth Place',
    'birth place':      'Birth Place',
    'event type':       'Event Type',
    'event date':       'Event Date',
    'event place':      'Event Place',
    'event year':       'Event Year',
    'death date':       'Event Date',
    'death place':      'Event Place',
    'death year':       'Event Year',
    'cause of death':   'Cause of Death',
    'marriage date':    'Event Date',
    'marriage place':   'Event Place',
    'marriage year':    'Event Year',
    'father':           'Father',
    "father's name":    'Father',
    'mother':           'Mother',
    "mother's name":    'Mother',
    'spouse':           'Spouse',
    "spouse's name":    'Spouse',
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

  const ALL_STANDARD_COLUMNS = [
    'Name', 'Surname', 'Sex', 'Age', 'Birth Year', 'Birth Date', 'Birth Place',
    'Event Type', 'Event Date', 'Event Place', 'Event Year', 'Cause of Death',
    'Father', 'Mother', 'Spouse', 'Race', 'Marital Status', 'Occupation',
    'Residence', 'Relationship', 'Nationality', 'Religion', 'District',
    'Registration No', 'Entry No', 'Page No', 'Reference No',
    'Informant', 'Registrar',
  ];

  function mapHeader(rawHeader) {
    const h = rawHeader.toLowerCase().trim();
    if (FIELD_MAP[h]) return FIELD_MAP[h];
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

  // ── Scrape ALL rows from the FS metadata table ──────────
  // Returns { headers: [{raw, std}], rows: [{fields}], collection, rowCount }

  function scrapeMetadataTable() {
    const result = { headers: [], rows: [], collection: '', rowCount: 0 };
    const tables = document.querySelectorAll('table');

    for (const table of tables) {
      const thEls = Array.from(table.querySelectorAll('th'));
      const headers = thEls.map(th => th.textContent.trim());

      if (headers.length < 2) continue;
      const realHeaders = headers.filter(h => !['more', 'attach', ''].includes(h.toLowerCase()));
      if (realHeaders.length < 2) continue;

      const trs = table.querySelectorAll('tbody tr');
      if (trs.length === 0) continue;

      // Build header mapping — skip "Attach to Tree" (index 0) and "Name" (index 1)
      // FS puts the person's name as a clickable link in cell index 1
      const knownFieldWords = ['name','sex','age','birth','event','place','date','type','more','attach','year','race','occupation','marital','father','mother','spouse','residence','relationship','entry','page','number','parent','second','district','religion','registration','reference','informant','registrar','nationality'];

      // Identify which headers are data field columns vs person name overflows
      // FS pattern: real field headers come first, then person names as extra <th>
      // Person names = headers that don't map to any known field
      const dataHeaders = []; // [{raw, std, colIndex}]
      const nameHeaders = []; // person names as extra <th> elements (one per row)

      for (let i = 2; i < headers.length; i++) {
        const raw = headers[i];
        const std = mapHeader(raw);
        if (std) {
          dataHeaders.push({ raw, std, colIndex: i });
        } else {
          // Check if this looks like a person name (not a known field)
          const lower = raw.toLowerCase();
          const isField = knownFieldWords.some(w => lower.includes(w));
          if (!isField && raw.length > 0) {
            nameHeaders.push(raw);
          } else {
            dataHeaders.push({ raw, std: raw, colIndex: i });
          }
        }
      }

      const headerMap = dataHeaders;
      result.headers = headerMap.map(h => ({ raw: h.raw, std: h.std }));

      // Process EVERY row
      let rowIdx = 0;
      for (const tr of trs) {
        const cells = tr.querySelectorAll('td');
        if (cells.length < 3) { rowIdx++; continue; }

        const fields = {};

        // Extract person name
        // Priority 1: nameHeaders array (FS puts one name per <th> matching each <tr>)
        let personName = '';
        if (rowIdx < nameHeaders.length) {
          personName = nameHeaders[rowIdx];
        }

        // Priority 2: look for person name links in the row
        if (!personName) {
          const nameLinks = tr.querySelectorAll('a');
          for (const link of nameLinks) {
            const text = link.textContent.trim();
            const lower = text.toLowerCase();
            if (!text || ['more', 'attach', 'view', ''].includes(lower)) continue;
            if (text.length <= 1) continue;
            personName = text;
            break;
          }
        }

        // Priority 3: check cell[1] text
        if (!personName && cells.length > 1) {
          const cellText = cells[1]?.textContent?.trim() || '';
          const lower = cellText.toLowerCase();
          if (cellText && !['more', 'attach', ''].includes(lower)) {
            personName = cellText;
          }
        }

        fields['Name'] = personName;

        // Extract data fields matching header positions
        for (const hm of headerMap) {
          const cell = cells[hm.colIndex];
          if (cell) {
            const val = cell.textContent.trim();
            if (val && val !== 'More' && val !== 'Attach') {
              fields[hm.std] = val;
            }
          }
        }

        // Only add row if it has at least one non-empty field besides Name
        const hasData = Object.entries(fields).some(([k, v]) => k !== 'Name' && v);
        if (hasData || personName) {
          result.rows.push({ fields });
        }
        rowIdx++;
      }

      result.rowCount = result.rows.length;
      break;
    }

    // Collection from breadcrumb
    const bc = document.querySelector('a[href*="search/collection"]');
    if (bc) result.collection = bc.textContent.trim();

    return result;
  }

  // ── Messages ─────────────────────────────────────────────

  chrome.runtime.onMessage.addListener((msg) => {
    if (msg.action === 'showPanel') {
      autoMode = false;
      autoStartImage = parseInt(getImageNumber(), 10) || 1;
      lastProcessedArk = '';
      showPanel();
    }
  });

  // ── Panel — multi-row table view ────────────────────────

  function showPanel() {
    if (panel) panel.remove();

    const arkId = extractArkId();
    const imgNum = getImageNumber();
    const imgTotal = getImageTotal();
    const scraped = scrapeMetadataTable();
    const isSingleRow = scraped.rows.length <= 1;

    let bodyHtml = '';

    if (scraped.rows.length === 0) {
      // No metadata — show manual entry
      bodyHtml = `
        <div class="fs-rp-fields">
          <div class="fs-rp-field"><label>Name</label><input type="text" data-std="Name" data-raw="Name" data-row="0" value=""></div>
          <div class="fs-rp-field"><label>Event Type</label><input type="text" data-std="Event Type" data-raw="Event Type" data-row="0" value=""></div>
          <div class="fs-rp-field"><label>Event Date</label><input type="text" data-std="Event Date" data-raw="Event Date" data-row="0" value=""></div>
          <div class="fs-rp-field"><label>Event Place</label><input type="text" data-std="Event Place" data-raw="Event Place" data-row="0" value=""></div>
          <div class="fs-rp-field"><label>Notes</label><input type="text" data-std="Notes" data-raw="Notes" data-row="0" value="" placeholder="optional"></div>
        </div>`;
    } else if (isSingleRow) {
      // Single row — original compact field grid
      let fieldsHtml = '';
      const row = scraped.rows[0];
      Object.entries(row.fields).forEach(([std, val]) => {
        fieldsHtml += `
          <div class="fs-rp-field">
            <label title="${escAttr(std)}">${esc(std)}</label>
            <input type="text" data-std="${escAttr(std)}" data-raw="${escAttr(std)}" data-row="0" value="${escAttr(val)}">
          </div>`;
      });
      fieldsHtml += `<div class="fs-rp-field"><label>Notes</label><input type="text" data-std="Notes" data-raw="Notes" data-row="0" value="" placeholder="optional"></div>`;
      bodyHtml = `<div class="fs-rp-fields">${fieldsHtml}</div>`;
    } else {
      // Multi-row — editable table
      const cols = ['Name'];
      scraped.headers.forEach(h => {
        if (!cols.includes(h.std)) cols.push(h.std);
      });

      let tableHtml = '<div class="fs-rp-multi-wrap"><table class="fs-rp-multi-table"><thead><tr>';
      tableHtml += '<th class="fs-rp-row-num">#</th>';
      tableHtml += '<th class="fs-rp-row-check"><input type="checkbox" id="fs-rp-check-all" checked title="Select/deselect all"></th>';
      cols.forEach(c => { tableHtml += `<th>${esc(c)}</th>`; });
      tableHtml += '</tr></thead><tbody>';

      scraped.rows.forEach((row, ri) => {
        tableHtml += `<tr data-row-idx="${ri}">`;
        tableHtml += `<td class="fs-rp-row-num">${ri + 1}</td>`;
        tableHtml += `<td class="fs-rp-row-check"><input type="checkbox" class="fs-rp-row-cb" data-row="${ri}" checked></td>`;
        cols.forEach(c => {
          const val = row.fields[c] || '';
          tableHtml += `<td><input type="text" data-std="${escAttr(c)}" data-row="${ri}" value="${escAttr(val)}" class="fs-rp-cell-input"></td>`;
        });
        tableHtml += '</tr>';
      });

      tableHtml += '</tbody></table></div>';
      bodyHtml = tableHtml;
    }

    panel = document.createElement('div');
    panel.id = 'fs-capture-result-panel';
    if (!isSingleRow && scraped.rows.length > 0) panel.classList.add('fs-multi-mode');

    panel.innerHTML = `
      <div class="fs-rp-header">
        <span class="fs-rp-title">FS Capture</span>
        <span class="fs-rp-ark">${esc(arkId) || 'No ARK'}</span>
        ${imgNum ? `<span class="fs-rp-img-num">Image ${esc(imgNum)}${imgTotal ? '/' + esc(imgTotal) : ''}</span>` : ''}
        ${scraped.rowCount > 1 ? `<span class="fs-rp-row-count">${scraped.rowCount} rows</span>` : ''}
        ${autoMode ? '<span class="fs-rp-auto">AUTO</span>' : ''}
        ${scraped.collection ? `<span class="fs-rp-collection" title="${esc(scraped.collection)}">${esc(scraped.collection)}</span>` : ''}
        <button class="fs-rp-close" title="Stop &amp; Close (Escape)">&times;</button>
      </div>
      <div class="fs-rp-body">
        ${bodyHtml}
        <div class="fs-rp-actions">
          <button id="fs-rp-save-next" class="fs-btn-save-next">Save all + Next &rarr;</button>
          <button id="fs-rp-save" class="fs-btn-save">Save only</button>
          <button id="fs-rp-stop" class="fs-btn-cancel">${autoMode ? 'Stop auto (Esc)' : 'Close'}</button>
        </div>
      </div>
    `;
    document.body.appendChild(panel);
    document.addEventListener('keydown', onEscape);

    // Select all checkbox
    const checkAll = panel.querySelector('#fs-rp-check-all');
    if (checkAll) {
      checkAll.addEventListener('change', () => {
        panel.querySelectorAll('.fs-rp-row-cb').forEach(cb => { cb.checked = checkAll.checked; });
      });
    }

    // Save + Next
    panel.querySelector('#fs-rp-save-next').addEventListener('click', () => { doSaveDownloadNext(); });

    // Save only
    panel.querySelector('#fs-rp-save').addEventListener('click', () => {
      const currentArkId = extractArkId();
      saveAllRows(currentArkId, (count) => {
        const btn = panel.querySelector('#fs-rp-save');
        btn.textContent = `Saved (${count})`;
        btn.disabled = true;
        btn.style.opacity = '0.6';
      });
    });

    // Stop / Close
    panel.querySelector('#fs-rp-stop').addEventListener('click', () => stopAndClose());
    panel.querySelector('.fs-rp-close').addEventListener('click', () => stopAndClose());

    // No metadata? Auto-skip
    if (autoMode && scraped.rows.length === 0) {
      const btn = panel.querySelector('#fs-rp-save-next');
      if (btn) btn.textContent = 'No metadata — skipping...';
      autoTimer = setTimeout(() => {
        if (autoMode) {
          clickNextImage();
          setTimeout(() => showPanel(), 3000);
        }
      }, 500);
      return;
    }

    // Auto-mode
    if (autoMode && scraped.rows.length > 0) {
      const currentImg = parseInt(getImageNumber(), 10) || 0;
      const totalImg = parseInt(getImageTotal(), 10) || 0;
      const remaining = totalImg > 0 ? totalImg - currentImg : 999;
      const processed = currentImg - autoStartImage;
      const stopBuffer = 10;

      if (remaining <= stopBuffer) {
        autoMode = false;
        const btn = panel.querySelector('#fs-rp-save-next');
        btn.textContent = `Done! Processed ${processed} images (${remaining} left at end)`;
        const stopBtn = panel.querySelector('#fs-rp-stop');
        if (stopBtn) stopBtn.textContent = 'Close';
        const autoSpan = panel.querySelector('.fs-rp-auto');
        if (autoSpan) { autoSpan.textContent = 'DONE'; autoSpan.style.background = '#27ae60'; autoSpan.style.animation = 'none'; }
      } else {
        const rowLabel = scraped.rowCount > 1 ? ` (${scraped.rowCount} rows)` : '';
        const btn = panel.querySelector('#fs-rp-save-next');
        btn.textContent = `Auto: ${currentImg}/${totalImg}${rowLabel} (${processed} done) — next in 2s (Esc to stop)`;
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

    if (currentArkId && currentArkId !== lastProcessedArk) {
      lastProcessedArk = currentArkId;
      saveAllRows(currentArkId, () => {
        clickNextImage();
        setTimeout(() => showPanel(), 3000);
      });
    } else {
      autoMode = false;
      if (btn) btn.textContent = 'Already processed — stopped';
    }
  }

  // ── Save all checked rows ───────────────────────────────

  function saveAllRows(arkId, cb) {
    const imgNum = getImageNumber();
    const pageUrl = window.location.href;
    const timestamp = new Date().toISOString();

    const checkboxes = panel.querySelectorAll('.fs-rp-row-cb');
    const isMultiRow = checkboxes.length > 0;

    if (isMultiRow) {
      // Collect checked row indices
      const checkedRows = new Set();
      checkboxes.forEach(cb => {
        if (cb.checked) checkedRows.add(parseInt(cb.getAttribute('data-row'), 10));
      });

      // Group inputs by row index
      const rowMap = {};
      panel.querySelectorAll('.fs-rp-cell-input').forEach(inp => {
        const ri = parseInt(inp.getAttribute('data-row'), 10);
        if (!checkedRows.has(ri)) return;
        if (!rowMap[ri]) rowMap[ri] = {};
        const std = inp.getAttribute('data-std');
        if (std && inp.value.trim()) {
          rowMap[ri][std] = inp.value.trim();
        }
      });

      // Build all entries
      const entries = Object.keys(rowMap).sort((a, b) => a - b).map(ri => ({
        arkId,
        imageNum: imgNum,
        rowNum: parseInt(ri, 10) + 1,
        fields: rowMap[ri],
        pageUrl,
        timestamp,
      }));

      if (entries.length === 0) {
        cb(0);
        return;
      }

      // Save all rows in one atomic call (avoids race condition)
      chrome.runtime.sendMessage({ action: 'saveRows', rows: entries }, (resp) => {
        cb(resp?.count || entries.length);
      });
    } else {
      // Single row
      const fields = {};
      panel.querySelectorAll('input[data-std]').forEach(inp => {
        const std = inp.getAttribute('data-std');
        if (std && inp.value.trim()) {
          fields[std] = inp.value.trim();
        }
      });

      const row = { arkId, imageNum: imgNum, fields, pageUrl, timestamp };
      chrome.runtime.sendMessage({ action: 'saveRow', row }, (resp) => {
        cb(resp?.count || '?');
      });
    }
  }

  function stopAndClose() {
    autoMode = false;
    if (autoTimer) { clearTimeout(autoTimer); autoTimer = null; }
    removePanel();
  }

  function clickNextImage() {
    for (const btn of document.querySelectorAll('button')) {
      const label = (btn.getAttribute('aria-label') || btn.title || '').toLowerCase();
      if (label.includes('next') && (label.includes('image') || label.includes('page'))) {
        if (!btn.disabled) { btn.click(); return true; }
      }
    }
    return false;
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
