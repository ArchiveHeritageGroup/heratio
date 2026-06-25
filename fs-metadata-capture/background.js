/* FS Metadata Capture — background service worker v3 */

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {

  // Relay "start capture" from popup to the active tab's content script
  if (msg.action === 'startCapture') {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs[0]) { sendResponse({ ok: false }); return; }
      const tabId = tabs[0].id;
      chrome.tabs.sendMessage(tabId, { action: 'showPanel' }, (resp) => {
        if (chrome.runtime.lastError) {
          chrome.scripting.executeScript({
            target: { tabId },
            files: ['content.js'],
          }, () => {
            chrome.scripting.insertCSS({
              target: { tabId },
              files: ['content.css'],
            }, () => {
              setTimeout(() => {
                chrome.tabs.sendMessage(tabId, { action: 'showPanel' });
                sendResponse({ ok: true });
              }, 200);
            });
          });
        } else {
          sendResponse({ ok: true });
        }
      });
    });
    return true;
  }

  // Save a single row
  if (msg.action === 'saveRow') {
    chrome.storage.session.get({ rows: [] }, (data) => {
      const rows = data.rows || [];
      rows.push(msg.row);
      chrome.storage.session.set({ rows }, () => {
        sendResponse({ ok: true, count: rows.length });
      });
    });
    return true;
  }

  // Save multiple rows at once (atomic — avoids race conditions)
  if (msg.action === 'saveRows') {
    chrome.storage.session.get({ rows: [] }, (data) => {
      const rows = data.rows || [];
      const newRows = msg.rows || [];
      newRows.forEach(r => rows.push(r));
      chrome.storage.session.set({ rows }, () => {
        sendResponse({ ok: true, count: rows.length, added: newRows.length });
      });
    });
    return true;
  }

  // Get all rows
  if (msg.action === 'getRows') {
    chrome.storage.session.get({ rows: [] }, (data) => {
      sendResponse({ rows: data.rows || [] });
    });
    return true;
  }

  // Clear all rows
  if (msg.action === 'clearRows') {
    chrome.storage.session.set({ rows: [] }, () => {
      sendResponse({ ok: true });
    });
    return true;
  }
});
