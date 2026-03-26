/* FS Metadata Capture — popup script */

const badge     = document.getElementById('badge');
const rowsBody  = document.getElementById('rows-body');
const emptyMsg  = document.getElementById('empty-msg');
const rowsTable = document.getElementById('rows-table');
const btnCapture  = document.getElementById('btn-capture');
const btnDownload = document.getElementById('btn-download');
const btnClear    = document.getElementById('btn-clear');

loadRows();

// Capture — relay to content script via background, then close popup
btnCapture.addEventListener('click', () => {
  chrome.runtime.sendMessage({ action: 'startCapture' }, () => {
    window.close();
  });
});

// Download CSV
btnDownload.addEventListener('click', () => {
  chrome.runtime.sendMessage({ action: 'getRows' }, (resp) => {
    const rows = resp?.rows || [];
    if (!rows.length) return;
    downloadCsv(rows);
  });
});

// Clear all
btnClear.addEventListener('click', () => {
  if (!confirm('Clear all captured rows?')) return;
  chrome.runtime.sendMessage({ action: 'clearRows' }, () => {
    loadRows();
  });
});

function loadRows() {
  chrome.runtime.sendMessage({ action: 'getRows' }, (resp) => {
    const rows = resp?.rows || [];
    badge.textContent = rows.length;
    btnDownload.disabled = rows.length === 0;
    btnClear.disabled = rows.length === 0;

    if (rows.length === 0) {
      rowsTable.style.display = 'none';
      emptyMsg.style.display = 'block';
      return;
    }

    rowsTable.style.display = 'table';
    emptyMsg.style.display = 'none';
    rowsBody.innerHTML = '';

    rows.forEach((r, i) => {
      const name = r.fields?.Name || r.name || '-';
      const event = r.fields?.['Event Type'] || r.recordType || '-';
      const date = r.fields?.['Event Date'] || r.eventDate || '-';
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${i + 1}</td>
        <td title="${esc(r.arkId)}">${esc(truncate(r.arkId, 16) || '-')}</td>
        <td title="${esc(name)}">${esc(truncate(name, 18))}</td>
        <td>${esc(truncate(event, 10))}</td>
        <td>${esc(truncate(date, 14))}</td>
      `;
      rowsBody.appendChild(tr);
    });
  });
}

function truncate(s, n) {
  if (!s) return '';
  return s.length > n ? s.substring(0, n) + '...' : s;
}

function esc(s) {
  if (!s) return '';
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

// ── CSV download with standardized columns ──

function downloadCsv(rows) {
  const today = new Date().toISOString().slice(0, 10);

  // Fixed columns that always appear
  const fixedCols = ['Row', 'ARK ID', 'Image #'];

  // Collect all standard field names used across all rows
  const fieldNamesSet = new Set();
  rows.forEach(r => {
    if (r.fields) {
      Object.keys(r.fields).forEach(k => fieldNamesSet.add(k));
    }
  });

  // Sort field columns in a logical order
  const COLUMN_ORDER = [
    'Name', 'Surname', 'Sex', 'Age', 'Birth Year', 'Birth Date', 'Birth Place',
    'Event Type', 'Event Date', 'Event Place', 'Event Year', 'Cause of Death',
    'Father', 'Mother', 'Spouse', 'Race', 'Marital Status', 'Occupation',
    'Residence', 'Relationship', 'Nationality', 'Religion', 'District',
    'Registration No', 'Entry No', 'Page No', 'Reference No',
    'Informant', 'Registrar', 'Notes',
  ];

  const fieldCols = [];
  // First add columns in standard order
  COLUMN_ORDER.forEach(col => {
    if (fieldNamesSet.has(col)) {
      fieldCols.push(col);
      fieldNamesSet.delete(col);
    }
  });
  // Then add any remaining columns not in the standard order
  fieldNamesSet.forEach(col => fieldCols.push(col));

  const tailCols = ['Page URL', 'Timestamp'];
  const allCols = [...fixedCols, ...fieldCols, ...tailCols];

  // Header row
  const csvHeader = allCols.map(c => csvVal(c)).join(',') + '\n';

  // Data rows
  let csvRows = '';
  rows.forEach((r, i) => {
    const vals = allCols.map(col => {
      if (col === 'Row') return i + 1;
      if (col === 'ARK ID') return csvVal(r.arkId);
      if (col === 'Image #') return csvVal(r.imageNum);
      if (col === 'Page URL') return csvVal(r.pageUrl);
      if (col === 'Timestamp') return csvVal(r.timestamp);
      // Field columns
      return csvVal(r.fields?.[col] || '');
    });
    csvRows += vals.join(',') + '\n';
  });

  const blob = new Blob([csvHeader + csvRows], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `fs-capture-${today}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

function csvVal(v) {
  if (v == null) return '';
  const s = String(v).replace(/\r?\n/g, ' ');
  if (s.includes(',') || s.includes('"') || s.includes('\n')) {
    return '"' + s.replace(/"/g, '""') + '"';
  }
  return s;
}
