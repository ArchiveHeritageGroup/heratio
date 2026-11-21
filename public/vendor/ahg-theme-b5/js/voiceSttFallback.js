/**
 * AHG Voice - server speech-to-text fallback (heratio#1767)
 *
 * Load AFTER voiceCommands.js and before DOMContentLoaded. It extends the
 * AHGVoiceCommands class in place (no second copy of the engine):
 *
 *  - no SpeechRecognition in the browser (Firefox and others), or the user
 *    switched on "Use server speech recognition" in the voice help: the mic
 *    button records with MediaRecorder (webm/opus or ogg/opus, up to the
 *    configured seconds), posts the clip to /voice/stt, and feeds the text it
 *    gets back into processCommand() - the same path as browser speech, so
 *    "search for ..." and every other command work unchanged;
 *  - server speech-to-text off, or no MediaRecorder: behaviour is exactly
 *    what voiceCommands.js does on its own.
 *
 * The recording lives only in memory until it is uploaded; the server
 * deletes it when the request ends.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
(function () {
  'use strict';

  if (typeof AHGVoiceCommands === 'undefined' || AHGVoiceCommands.prototype._sttPatched) return;

  var P = AHGVoiceCommands.prototype;
  P._sttPatched = true;

  var CONFIG_URL = '/voice/stt/config';
  var PREF_KEY = 'ahg_voice_engine';
  var MIME_CANDIDATES = ['audio/webm;codecs=opus', 'audio/ogg;codecs=opus', 'audio/webm', 'audio/ogg'];

  function hasBrowserSpeech() {
    return !!(window.SpeechRecognition || window.webkitSpeechRecognition);
  }

  function recordMime() {
    if (!window.MediaRecorder || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return null;
    if (typeof MediaRecorder.isTypeSupported !== 'function') return null;
    for (var i = 0; i < MIME_CANDIDATES.length; i++) {
      if (MediaRecorder.isTypeSupported(MIME_CANDIDATES[i])) return MIME_CANDIDATES[i];
    }
    return null; // e.g. a browser that records only mp4: the server would refuse it
  }

  function prefersServer() {
    try { return localStorage.getItem(PREF_KEY) === 'server'; } catch (e) { return false; }
  }

  function setPreference(server) {
    try {
      if (server) localStorage.setItem(PREF_KEY, 'server'); else localStorage.removeItem(PREF_KEY);
    } catch (e) { /* storage blocked: choice lasts for this page only */ }
  }

  var configPromise = null;
  function loadConfig() {
    if (!configPromise) {
      configPromise = fetch(CONFIG_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .catch(function () { return null; });
    }
    return configPromise;
  }

  /** "Search for maps." -> "search for maps" so string patterns still match. */
  function normalise(text) {
    return String(text || '').replace(/\s+/g, ' ').replace(/^[\s"'.,!?]+|[\s"'.,!?]+$/g, '');
  }

  var originalInit = P.init;
  var originalStart = P.startListening;
  var originalStop = P.stopListening;
  var originalStartDictation = P.startDictation;

  P.init = function () {
    var self = this;
    var mime = recordMime();
    var browser = hasBrowserSpeech();

    if (browser) {
      // Browser speech works: start as before, and offer the server choice in help.
      if (mime && prefersServer()) {
        loadConfig().then(function (cfg) { if (cfg && cfg.enabled) self._sttActivate(cfg, mime); });
      }
      originalInit.call(this);
      this._sttWireChoice(mime);
      return;
    }

    if (!mime) { originalInit.call(this); return; } // hides the voice UI, as before

    loadConfig().then(function (cfg) {
      if (cfg && cfg.enabled) {
        self._sttActivate(cfg, mime);
        self._continuousMode = false; // never start the microphone without a click
      }
      originalInit.call(self);
    });
  };

  P._sttActivate = function (cfg, mime) {
    this._stt = cfg;
    this._sttMime = mime;
    this._sttMode = true;
    this.isSupported = true;
    if (!this.recognition) {
      this._injectFieldMics = function () {}; // field dictation needs browser speech
    }
  };

  P._sttWireChoice = function (mime) {
    var self = this;
    var modal = document.getElementById('voice-help-modal');
    if (!modal || !mime) return;
    modal.addEventListener('show.bs.modal', function () {
      loadConfig().then(function (cfg) {
        var wrap = document.getElementById('voice-stt-choice');
        var box = document.getElementById('voice-stt-prefer-server');
        if (!wrap || !box || !cfg || !cfg.enabled) return;
        wrap.style.display = '';
        box.checked = !!self._sttMode;
        if (box._sttBound) return;
        box._sttBound = true;
        box.addEventListener('change', function () {
          setPreference(box.checked);
          if (self.isListening) self.stopListening();
          if (box.checked) { self._sttActivate(cfg, mime); } else { self._sttMode = false; }
        });
      });
    });
  };

  P.startListening = function () {
    if (!this._sttMode || this.mode === 'dictation') return originalStart.call(this);
    if (this.isListening) return;
    this._sttRecord();
  };

  P.stopListening = function () {
    if (!this._sttMode || this.mode === 'dictation') return originalStop.call(this);
    if (this._sttRecorder && this._sttRecorder.state === 'recording') {
      this._sttRecorder.stop(); // onstop uploads what was said so far
    }
  };

  P.startDictation = function (field) {
    if (!this.recognition) {
      if (this._stt) this.showToast(this._stt.messages.no_dictation, 'warning');
      return;
    }
    return originalStartDictation.call(this, field);
  };

  P._sttSetRecording = function (on) {
    var label = on ? this._stt.messages.recording : 'Voice commands';
    [this.navbarBtn, this.floatingBtn].forEach(function (btn) {
      if (!btn) return;
      btn.classList.toggle('voice-recording', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.title = label;
    });
  };

  P._sttRecord = function () {
    var self = this;
    var cfg = this._stt;
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      var rec;
      try {
        rec = new MediaRecorder(stream, { mimeType: self._sttMime });
      } catch (e) {
        stream.getTracks().forEach(function (t) { t.stop(); });
        self.showToast(cfg.messages.unavailable, 'danger');
        return;
      }
      var chunks = [];
      rec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
      rec.onstop = function () {
        clearTimeout(self._sttTimer);
        stream.getTracks().forEach(function (t) { t.stop(); });
        self._sttRecorder = null;
        self.isListening = false;
        self._updateUI(false);
        self._sttSetRecording(false);
        var type = String(rec.mimeType || self._sttMime).split(';')[0];
        var blob = new Blob(chunks, { type: type });
        if (!blob.size) { self.showToast(cfg.messages.no_speech, 'warning'); return; }
        if (cfg.max_bytes && blob.size > cfg.max_bytes) { self.showToast(cfg.messages.unavailable, 'warning'); return; }
        self._sttUpload(blob, type);
      };
      rec.start();
      self._sttRecorder = rec;
      self.isListening = true;
      self._updateUI(true);
      self._sttSetRecording(true);
      self.showToast(cfg.messages.recording + ' (' + cfg.max_seconds + ' s)', 'info');
      self._sttTimer = setTimeout(function () {
        if (rec.state === 'recording') rec.stop();
      }, (cfg.max_seconds || 15) * 1000);
    }).catch(function (err) {
      var denied = err && (err.name === 'NotAllowedError' || err.name === 'SecurityError');
      self.showToast(denied ? cfg.messages.denied : cfg.messages.unavailable, 'danger');
    });
  };

  P._sttUpload = function (blob, type) {
    var self = this;
    var cfg = this._stt;
    var form = new FormData();
    form.append('audio', blob, 'speech.' + (type.indexOf('ogg') !== -1 ? 'ogg' : 'webm'));
    var token = document.querySelector('meta[name="csrf-token"]');
    self.showToast(cfg.messages.sending, 'info');

    fetch(cfg.upload_url, {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (data) {
        if (data && data.ok) {
          var text = normalise(data.text);
          if (text) { self.processCommand(text, 1.0); } else { self.showToast(cfg.messages.no_speech, 'warning'); }
        } else {
          self.showToast((data && data.message) || cfg.messages.unavailable, 'warning');
        }
      })
      .catch(function () { self.showToast(cfg.messages.unavailable, 'warning'); });
  };
})();
