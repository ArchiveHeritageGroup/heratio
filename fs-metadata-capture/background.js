/* FS Metadata Capture — background service worker */

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {

  // Relay "start capture" from popup to the active tab's content script
  if (msg.action === 'startCapture') {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (!tabs[0]) { sendResponse({ ok: false }); return; }
      const tabId = tabs[0].id;
      // Try sending to existing content script first
      chrome.tabs.sendMessage(tabId, { action: 'showPanel' }, (resp) => {
        if (chrome.runtime.lastError) {
          // Content script not loaded yet — inject it, then send message
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
    return true; // async response
  }

  // Save a row to session storage
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
