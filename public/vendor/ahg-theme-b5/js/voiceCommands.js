/**
 * AHG Voice Commands — Core Engine
 *
 * Uses Web Speech API (SpeechRecognition + SpeechSynthesis) for
 * voice-driven navigation and actions in AtoM Heratio.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class AHGVoiceCommands {
  constructor() {
    this.recognition = null;
    this.synthesis = window.speechSynthesis || null;
    this.isListening = false;
    this.isSupported = false;
    this.mode = 'command'; // 'command' | 'dictation'
    this.confidenceThreshold = 0.4;
    this.language = 'en-US';
    this.speechRate = 1.0;

    // Dictation state
    this.dictationField = null;        // Currently focused field
    this.dictationHistory = [];        // Segments for undo
    this.dictationConfirmClear = false; // Waiting for yes/no after "clear field"

    // "Did you mean?" confirmation state
    this._suggestedCommand = null;     // The suggested command object
    this._suggestedText = null;        // The suggested pattern text

    // Continuous listening mode (stays on after each command) — restore from session
    this._continuousMode = false;
    try { this._continuousMode = sessionStorage.getItem('ahg_voice_continuous') === '1'; } catch (e) { /* ignore */ }
    this._isAutoRestart = false; // True during auto-restart cycle (skip "Listening" speech)
    this._isSpeaking = false;    // True while system is speaking (prevents self-hearing)

    // Mouseover read-aloud settings (loaded from server, can be toggled)
    this._hoverReadEnabled = true;
    this._hoverReadDelay = 400;

    // UI elements (set after DOM ready)
    this.navbarBtn = null;
    this.floatingBtn = null;
    this.indicator = null;
    this.toastContainer = null;

    // Detect support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRecognition) {
      this.isSupported = true;
      this.recognition = new SpeechRecognition();
      this.recognition.continuous = false;
      this.recognition.interimResults = false;
      this.recognition.lang = this.language;
      this.recognition.maxAlternatives = 3;
      this._bindRecognitionEvents();
    }
  }

  /**
   * Initialize — call on DOMContentLoaded.
   */
  init() {
    if (!this.isSupported) {
      // Hide all voice UI if not supported
      document.querySelectorAll('.voice-ui').forEach(el => el.style.display = 'none');
      return;
    }

    // Load settings from server
    this._loadSettings();

    this.floatingBtn = document.getElementById('voice-floating-btn');
    this.indicator = document.getElementById('voice-indicator');
    this.toastContainer = document.getElementById('voice-toast-container');

    // Inject navbar mic button next to the search box
    this._injectNavbarButton();

    // Bind click handlers
    if (this.navbarBtn) {
      this.navbarBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggle();
      });
      this.navbarBtn.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        this._toggleTypeInput();
      });
    }
    if (this.floatingBtn) {
      this.floatingBtn.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggle();
      });
      this.floatingBtn.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        this._toggleTypeInput();
      });
    }

    // Inject field mic icons on edit pages
    this._injectFieldMics();

    // Keyboard shortcuts: Ctrl+Shift+V = toggle voice, Ctrl+Shift+H = help modal
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.shiftKey && e.key === 'V') { e.preventDefault(); this.toggle(); }
        if (e.ctrlKey && e.shiftKey && e.key === 'H') {
            e.preventDefault();
            var modal = document.getElementById('voice-help-modal');
            if (modal && typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });

    // Help modal search filter
    var helpSearch = document.getElementById('voice-help-search');
    if (helpSearch) {
        helpSearch.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            document.querySelectorAll('#voice-help-modal .voice-cmd-list li').forEach(function(li) {
                var text = li.textContent.toLowerCase();
                li.style.display = text.indexOf(query) !== -1 ? '' : 'none';
            });
        });
    }

    // Show UI
    document.querySelectorAll('.voice-ui').forEach(el => el.style.display = '');

    // Mouseover read-aloud for buttons and links (accessibility)
    this._initMouseoverRead();

    // Auto-restart if continuous mode was active before page navigation
    var self = this;
    if (this._continuousMode && sessionStorage.getItem('ahg_voice_active')) {
      setTimeout(function () {
        self._isAutoRestart = true;
        self.startListening();
      }, 800);
    }

    // Accessibility: announce page context after a short delay
    setTimeout(function () { self._announcePageContext(); }, 1500);
  }

  /**
   * Toggle listening on/off.
   */
  toggle() {
    if (this._toggleDebounce) return;
    this._toggleDebounce = true;
    var self = this;
    setTimeout(function() { self._toggleDebounce = false; }, 500);
    if (this.isListening) { this.stopListening(); } else { this.startListening(); }
  }

  /**
   * Start listening for voice commands.
   */
  startListening() {
    if (!this.isSupported || this.isListening) return;

    try {
      this.recognition.start();
      this.isListening = true;
      this._updateUI(true);
      // Only speak "Listening" on initial activation, not on auto-restarts
      if (!this._isAutoRestart) {
        this.speak('Listening');
      }
      this._isAutoRestart = false;
      // Mark session as voice-active for auto-announcements on page navigation
      try { sessionStorage.setItem('ahg_voice_active', '1'); } catch (e) { /* ignore */ }
    } catch (e) {
      // Already started or permission denied
      console.warn('Voice: could not start recognition', e);
      this._isAutoRestart = false;
    }
  }

  /**
   * Stop listening.
   */
  stopListening() {
    if (!this.isSupported || !this.isListening) return;

    // Disable continuous mode on deliberate stop
    this._continuousMode = false;
    this._isAutoRestart = false;
    try { sessionStorage.setItem('ahg_voice_continuous', '0'); } catch (e) { /* ignore */ }

    this.speak('Stopped listening');

    try {
      this.recognition.stop();
    } catch (e) {
      // Ignore
    }
    this.isListening = false;
    this._updateUI(false);
  }

  // ---------------------------------------------------------------
  //  Dictation Mode (Phase 3)
  // ---------------------------------------------------------------

  /**
   * Start dictation into a specific text field.
   */
  startDictation(field) {
    if (!this.isSupported || !field) return;

    this.dictationField = field;
    this.dictationHistory = [];
    this.dictationConfirmClear = false;
    this.mode = 'dictation';

    // Switch recognition to continuous + interim
    try { this.recognition.stop(); } catch (e) { /* ignore */ }

    const self = this;
    setTimeout(function () {
      self.recognition.continuous = true;
      self.recognition.interimResults = true;

      // Mark field active
      field.classList.add('voice-dictation-active');
      field.focus();

      // Update field mic icon if present
      var mic = field.parentElement && field.parentElement.querySelector('.voice-field-mic');
      if (mic) mic.classList.add('active');

      try {
        self.recognition.start();
        self.isListening = true;
        self._updateUI(true);
        self.showToast('Dictation started — speak into field', 'info');
      } catch (e) {
        console.warn('Voice: could not start dictation', e);
      }
    }, 200);
  }

  /**
   * Stop dictation and return to command mode.
   */
  stopDictation() {
    if (this.mode !== 'dictation') return;

    // Remove interim text
    this._clearInterim();

    // Clean up field state
    if (this.dictationField) {
      this.dictationField.classList.remove('voice-dictation-active');
      var mic = this.dictationField.parentElement &&
        this.dictationField.parentElement.querySelector('.voice-field-mic');
      if (mic) mic.classList.remove('active');
    }

    this.mode = 'command';
    this.dictationField = null;
    this.dictationHistory = [];
    this.dictationConfirmClear = false;

    // Revert recognition to one-shot
    try { this.recognition.stop(); } catch (e) { /* ignore */ }

    const self = this;
    setTimeout(function () {
      self.recognition.continuous = false;
      self.recognition.interimResults = false;
      self.isListening = false;
      self._updateUI(false);
    }, 200);

    this.showToast('Dictation stopped', 'info');
    this.speak('Dictation stopped');
  }

  /**
   * Punctuation/sub-command map for dictation mode.
   */
  static get DICTATION_SUBS() {
    return {
      'new line': '\n',
      'newline': '\n',
      'new paragraph': '\n\n',
      'period': '. ',
      'full stop': '. ',
      'comma': ', ',
      'question mark': '? ',
      'exclamation mark': '! ',
      'exclamation point': '! ',
      'colon': ': ',
      'semicolon': '; ',
      'open quote': '\u201C',
      'close quote': '\u201D',
      'open bracket': '(',
      'close bracket': ')',
      'dash': ' \u2013 ',
      'hyphen': '-'
    };
  }

  /**
   * Process a dictation transcript segment.
   */
  _processDictation(transcript, isFinal) {
    if (!this.dictationField) return;

    var text = transcript.trim();
    var lower = text.toLowerCase();

    // Handle "clear field" confirmation flow
    if (this.dictationConfirmClear) {
      this.dictationConfirmClear = false;
      if (isFinal && (lower === 'yes' || lower === 'yeah' || lower === 'yep')) {
        this.dictationField.value = '';
        this.dictationHistory = [];
        this.showToast('Field cleared', 'success');
        this.speak('Field cleared');
      } else if (isFinal) {
        this.showToast('Clear cancelled', 'info');
        this.speak('Clear cancelled');
      }
      return;
    }

    // Check for dictation sub-commands (only on final results)
    if (isFinal) {
      // Stop dictating
      if (lower === 'stop dictating' || lower === 'stop dictation') {
        this.stopDictation();
        return;
      }

      // Undo last
      if (lower === 'undo' || lower === 'undo last' || lower === 'undo that') {
        if (this.dictationHistory.length > 0) {
          var last = this.dictationHistory.pop();
          var val = this.dictationField.value;
          if (val.endsWith(last)) {
            this.dictationField.value = val.slice(0, -last.length);
          }
          this.showToast('Undone: "' + last.trim().substring(0, 30) + '"', 'info');
        } else {
          this.speak('Nothing to undo');
        }
        return;
      }

      // Clear field
      if (lower === 'clear field' || lower === 'clear the field') {
        this.dictationConfirmClear = true;
        this.speak('Are you sure? Say yes or no');
        this.showToast('Say "yes" to clear or "no" to cancel', 'warning');
        return;
      }

      // Read back
      if (lower === 'read back' || lower === 'read it back' || lower === 'read field') {
        var content = this.dictationField.value.trim();
        if (content) {
          this.speak(content);
          this.showToast('Reading back...', 'info');
        } else {
          this.speak('Field is empty');
        }
        return;
      }

      // Check for punctuation sub-commands
      var subs = AHGVoiceCommands.DICTATION_SUBS;
      if (subs[lower] !== undefined) {
        this._clearInterim();
        var punct = subs[lower];
        this._insertAtCursor(this.dictationField, punct);
        this.dictationHistory.push(punct);
        return;
      }

      // Final text — insert it
      this._clearInterim();
      // Capitalize first letter of sentence
      var insertText = this._smartCapitalize(text);
      // Add trailing space
      insertText += ' ';
      this._insertAtCursor(this.dictationField, insertText);
      this.dictationHistory.push(insertText);
    } else {
      // Interim result — show grayed preview
      this._showInterim(text);
    }
  }

  /**
   * Insert text at cursor position in a field.
   */
  _insertAtCursor(field, text) {
    var start = field.selectionStart;
    var end = field.selectionEnd;
    var val = field.value;

    if (typeof start === 'number') {
      field.value = val.substring(0, start) + text + val.substring(end);
      var newPos = start + text.length;
      field.selectionStart = newPos;
      field.selectionEnd = newPos;
    } else {
      // Fallback: append
      field.value += text;
    }

    // Trigger input event for any listeners
    field.dispatchEvent(new Event('input', { bubbles: true }));
  }

  /**
   * Capitalize first letter if preceding context suggests start of sentence.
   */
  _smartCapitalize(text) {
    if (!text) return text;
    if (!this.dictationField) return text;

    var val = this.dictationField.value;
    // Capitalize at start of field or after sentence-ending punctuation
    if (!val || /[.!?]\s*$/.test(val) || /\n\s*$/.test(val)) {
      return text.charAt(0).toUpperCase() + text.slice(1);
    }
    return text;
  }

  /**
   * Show interim (not-yet-final) text as a grayed span after the field.
   */
  _showInterim(text) {
    if (!this.dictationField) return;

    var container = this.dictationField.parentElement;
    if (!container) return;

    var span = container.querySelector('.voice-interim-text');
    if (!span) {
      span = document.createElement('span');
      span.className = 'voice-interim-text';
      container.appendChild(span);
    }
    span.textContent = text;
  }

  /**
   * Remove interim text display.
   */
  _clearInterim() {
    if (!this.dictationField || !this.dictationField.parentElement) return;
    var span = this.dictationField.parentElement.querySelector('.voice-interim-text');
    if (span) span.remove();
  }

  /**
   * Inject small mic icons into text inputs and textareas on edit pages.
   */
  _injectFieldMics() {
    // Only inject on edit pages
    var form = document.querySelector('form#editForm, form.form-edit');
    if (!form) return;

    var self = this;
    var fields = form.querySelectorAll('input[type="text"], textarea');

    fields.forEach(function (field) {
      // Skip hidden/readonly fields
      if (field.type === 'hidden' || field.readOnly || field.disabled) return;
      // Skip fields that are too small (like date pickers)
      if (field.offsetWidth < 100) return;

      // Ensure parent has relative positioning
      var parent = field.parentElement;
      if (!parent) return;
      var pos = window.getComputedStyle(parent).position;
      if (pos === 'static') {
        parent.style.position = 'relative';
      }

      // Create mic icon
      var mic = document.createElement('button');
      mic.type = 'button';
      mic.className = 'voice-field-mic';
      mic.setAttribute('aria-label', 'Dictate into this field');
      mic.title = 'Dictate';
      mic.innerHTML = '<i class="bi bi-mic"></i>';
      mic.tabIndex = -1;

      // Position differently for textarea vs input
      if (field.tagName === 'TEXTAREA') {
        mic.classList.add('voice-field-mic-textarea');
      }

      // Click handler
      mic.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (self.mode === 'dictation' && self.dictationField === field) {
          self.stopDictation();
        } else {
          if (self.mode === 'dictation') {
            self.stopDictation();
          }
          self.startDictation(field);
        }
      });

      parent.appendChild(mic);
    });
  }

  /**
   * Process a recognized command transcript.
   */
  processCommand(transcript, confidence) {
    const text = transcript.toLowerCase().trim();

    console.log('[Voice] Heard: "' + text + '" (confidence: ' + (confidence * 100).toFixed(1) + '%)');

    if (confidence < this.confidenceThreshold) {
      this.showToast('Low confidence (' + (confidence * 100).toFixed(0) + '%): "' + transcript + '"', 'warning');
      return;
    }

    // Handle "yes" / "yeah" / "yep" to confirm a "Did you mean?" suggestion
    if (this._suggestedCommand && (text === 'yes' || text === 'yeah' || text === 'yep' || text === 'correct' || text === 'that one')) {
      var cmd = this._suggestedCommand;
      var sugText = this._suggestedText || '';
      this._suggestedCommand = null;
      this._suggestedText = null;

      this.showToast(cmd.description, 'success');
      if (cmd.feedback) { this.speak(cmd.feedback); }

      try {
        if (cmd.feedback && cmd.mode === 'nav') {
          var self = this;
          setTimeout(function () { try { cmd.action(sugText); } catch (e) { console.error('Voice command error:', e); } }, 600);
        } else {
          cmd.action(sugText);
        }
      } catch (e) {
        console.error('Voice command error:', e);
      }
      return;
    }

    // Clear any previous suggestion if user says something other than "yes"
    if (this._suggestedCommand && text !== 'no') {
      this._suggestedCommand = null;
      this._suggestedText = null;
    }

    // Try matching against registered commands
    if (typeof AHGVoiceRegistry === 'undefined') {
      this.showToast('Voice registry not loaded', 'danger');
      return;
    }

    const commands = AHGVoiceRegistry.getCommands();
    let matched = false;

    for (const cmd of commands) {
      if (this._matchCommand(text, cmd)) {
        matched = true;

        // Context check — block if command not available on this page
        if (typeof cmd.contextCheck === 'function' && !cmd.contextCheck()) {
          this.showToast(cmd.description + ' — not available here', 'warning');
          this.speak('That command is not available on this page');
          break;
        }

        this.showToast(cmd.description, 'success');

        // Speak audible feedback if defined (talk back to the user)
        if (cmd.feedback) {
          this.speak(cmd.feedback);
        }

        try {
          // For navigation commands, delay action slightly so speech starts first
          if (cmd.feedback && cmd.mode === 'nav') {
            var self = this;
            var cmdRef = cmd;
            setTimeout(function () {
              try { cmdRef.action(text); } catch (e) { console.error('Voice command error:', e); }
            }, 600);
          } else {
            cmd.action(text);
          }
        } catch (e) {
          console.error('Voice command error:', e);
          this.showToast('Command failed', 'danger');
        }
        break;
      }
    }

    if (!matched) {
      console.log('[Voice] No match for: "' + text + '" — tried ' + commands.length + ' commands');
      // Suggest closest match
      var result = this._findClosestCommand(text, commands);
      if (result) {
        console.log('[Voice] Closest match: "' + result.pattern + '"');
        this._suggestedCommand = result.command;
        this._suggestedText = result.pattern;
        this.showToast('Did you mean "' + result.pattern + '"? Say "yes" to confirm.', 'warning');
        this.speak('Did you mean, ' + result.pattern + '? Say yes to confirm.');
      } else {
        this._suggestedCommand = null;
        this._suggestedText = null;
        this.showToast('Not recognized: "' + transcript + '"', 'warning');
        this.speak('Sorry, I did not understand that. Say help for available commands.');
      }
    }
  }

  /**
   * Speak text aloud via SpeechSynthesis.
   */
  speak(text) {
    if (!this.synthesis) return;

    // Cancel any current speech
    this.synthesis.cancel();

    // Pause recognition while speaking to prevent self-hearing
    var wasListening = this.isListening;
    if (wasListening && this.recognition) {
      this._isSpeaking = true;
      try { this.recognition.stop(); } catch (e) { /* ignore */ }
    }

    var self = this;
    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = this.speechRate;
    utterance.pitch = 1.0;
    utterance.volume = 1.0;
    utterance.lang = this.language;

    // Prefer a natural voice if available
    const voices = this.synthesis.getVoices();
    const preferred = voices.find(v => v.lang.startsWith('en') && v.localService);
    if (preferred) {
      utterance.voice = preferred;
    }

    // Resume recognition after speech ends
    utterance.onend = function () {
      self._isSpeaking = false;
      if (wasListening && self.isListening !== false) {
        setTimeout(function () {
          if (!self._isSpeaking) {
            try { self.recognition.start(); } catch (e) { /* ignore */ }
          }
        }, 300);
      }
    };
    utterance.onerror = function () {
      self._isSpeaking = false;
      if (wasListening && self.isListening !== false) {
        setTimeout(function () {
          if (!self._isSpeaking) {
            try { self.recognition.start(); } catch (e) { /* ignore */ }
          }
        }, 300);
      }
    };

    this.synthesis.speak(utterance);
  }

  /**
   * Show a toast notification.
   */
  showToast(message, type) {
    if (!this.toastContainer) return;

    const colorMap = {
      success: 'bg-success text-white',
      warning: 'bg-warning text-dark',
      danger: 'bg-danger text-white',
      info: 'bg-info text-white'
    };

    const toast = document.createElement('div');
    toast.className = 'toast show voice-toast ' + (colorMap[type] || 'bg-secondary text-white');
    toast.setAttribute('role', 'alert');
    toast.innerHTML = '<div class="toast-body d-flex align-items-center">' +
      '<i class="bi bi-mic-fill me-2"></i>' +
      '<span>' + this._escHtml(message) + '</span>' +
      '</div>';

    this.toastContainer.appendChild(toast);

    // Auto-dismiss after 2s
    setTimeout(() => {
      toast.classList.add('voice-toast-exit');
      setTimeout(() => toast.remove(), 300);
    }, 2000);
  }

  // ---------------------------------------------------------------
  //  Metadata Reading (Phase 4)
  // ---------------------------------------------------------------

  /**
   * Read image/digital object metadata from the current page.
   */
  readImageMetadata() {
    // Read ALL populated fields on the page, grouped by section.
    // Supports both ISAD layout (.field h3 + div) and CCO/Gallery layout (.row .col-md-3 + .col-md-9).
    if (!this.synthesis) return;

    // Cancel any previous speech
    this.synthesis.cancel();

    var utterances = [];
    var self = this;
    var fieldCount = 0;

    // Try TTS content area first (wraps the main metadata), then fall back to #content or #wrapper
    var contentArea = document.querySelector('#tts-content-area, #content, #wrapper');
    if (!contentArea) {
      this.speak('No metadata found on this page');
      return;
    }

    // Find all sections (card headers for CCO/Gallery, atom-section-header for ISAD)
    var sections = contentArea.querySelectorAll('section, .card.mb-4');

    if (sections.length > 0) {
      // Read section by section
      sections.forEach(function (section) {
        // Get section header
        var header = section.querySelector('.card-header h5, .atom-section-header, h2');
        var sectionName = header ? header.textContent.trim() : '';
        var sectionFields = [];

        // Pattern 1: CCO/Gallery — .row.mb-3 > .col-md-3 (label) + .col-md-9 (value)
        section.querySelectorAll('.row.mb-3').forEach(function (row) {
          var label = row.querySelector('.col-md-3');
          var value = row.querySelector('.col-md-9');
          if (label && value) {
            var valText = value.textContent.trim();
            if (valText) {
              sectionFields.push(label.textContent.trim() + ': ' + valText);
            }
          }
        });

        // Pattern 2: ISAD — .field .col-3 (label h3) + .col-9 (value)
        section.querySelectorAll('.field.row').forEach(function (row) {
          var label = row.querySelector('h3, .col-3');
          var value = row.querySelector('.col-9');
          if (label && value) {
            var valText = value.textContent.trim();
            if (valText) {
              sectionFields.push(label.textContent.trim() + ': ' + valText);
            }
          }
        });

        // Pattern 3: Simple .field with nested label + value
        section.querySelectorAll('.field:not(.row)').forEach(function (field) {
          var label = field.querySelector('.field-label, dt, strong');
          var value = field.querySelector('.field-value, dd, span:not(.field-label)');
          if (label && value) {
            var valText = value.textContent.trim();
            if (valText) {
              sectionFields.push(label.textContent.trim() + ': ' + valText);
            }
          }
        });

        if (sectionFields.length > 0) {
          if (sectionName) {
            utterances.push(sectionName + '.');
          }
          sectionFields.forEach(function (f) {
            utterances.push(f);
            fieldCount++;
          });
          utterances.push(''); // pause between sections
        }
      });
    }

    // If no sections found, try flat field extraction
    if (fieldCount === 0) {
      // Try list-group items (Quick Info sidebar)
      contentArea.querySelectorAll('.list-group-item').forEach(function (li) {
        var text = li.textContent.trim();
        if (text && !li.querySelector('a')) { // Skip link items
          utterances.push(text);
          fieldCount++;
        }
      });
    }

    if (fieldCount === 0) {
      this.speak('No populated fields found on this page');
      this.showToast('No metadata to read', 'warning');
      return;
    }

    this.showToast('Reading ' + fieldCount + ' fields. Say "stop" to stop.', 'info');

    // Queue each utterance separately so cancel() stops mid-read
    utterances.forEach(function (text) {
      if (!text) return; // skip empty pause markers
      var u = new SpeechSynthesisUtterance(text);
      u.rate = self.speechRate;
      u.pitch = 1.0;
      u.volume = 1.0;
      u.lang = self.language;
      var voices = self.synthesis.getVoices();
      var preferred = voices.find(function (v) { return v.lang.startsWith('en') && v.localService; });
      if (preferred) u.voice = preferred;
      self.synthesis.speak(u);
    });
  }

  /**
   * Read just the title of the current record aloud.
   */
  readTitle() {
    var title = this._getPageTitle();
    if (title) {
      this.speak(title);
      this.showToast('Title: ' + title, 'info');
    } else {
      this.speak('No title found');
      this.showToast('No title found', 'warning');
    }
  }

  /**
   * Read the description/scope-and-content of the current record.
   */
  readDescription() {
    var desc = this._getPageDescription();
    if (desc) {
      this.speak(desc);
      this.showToast('Reading description...', 'info');
    } else {
      this.speak('No description found on this page');
      this.showToast('No description found', 'warning');
    }
  }

  /**
   * Read the content of a text/plain digital object aloud.
   * Finds text in the text viewer (<pre id="text-content-{ID}">) or
   * the archive-content viewer (<pre id="archive-content-{ID}">).
   */
  readTextFile() {
    var textEl = document.querySelector('[id^="text-content-"], [id^="archive-content-"]');
    if (!textEl) {
      this.speak('No text file found on this page');
      this.showToast('No text file on this page', 'warning');
      return;
    }

    var text = (textEl.textContent || '').trim();

    // Check if content is still loading
    if (!text || /loading|spinner/i.test(text)) {
      this.speak('Text file is still loading. Please try again in a moment.');
      this.showToast('Text still loading...', 'info');
      return;
    }

    if (text.length > 10000) {
      this.speak('This is a long file, ' + Math.round(text.length / 1000) + ' thousand characters. Reading now.');
    }

    this.speak(text);
    this.showToast('Reading text file (' + text.length + ' characters)', 'info');
  }

  /**
   * Stop any current speech synthesis.
   */
  stopSpeaking() {
    if (this.synthesis) {
      this.synthesis.cancel();
      this._isSpeaking = false;
      this.showToast('Speech stopped', 'info');
    }
  }

  // ---------------------------------------------------------------
  //  Accessibility — Screen Reader Mode
  // ---------------------------------------------------------------

  /**
   * Group labels used by listSections and listCommands.
   */
  static get groupLabels() {
    return {
      'nav': 'Navigation',
      'action_edit': 'Edit Actions',
      'action_view': 'View Actions',
      'action_browse': 'Browse Actions',
      'global': 'Global',
      'dictation': 'Dictation'
    };
  }

  /**
   * Read available command sections aloud and prompt user to pick one.
   */
  listSections() {
    if (!this.synthesis) return;
    if (typeof AHGVoiceRegistry === 'undefined') {
      this.speak('Voice registry not loaded');
      return;
    }

    this.synthesis.cancel();

    var grouped = AHGVoiceRegistry.getGrouped();
    var labels = AHGVoiceCommands.groupLabels;
    var self = this;

    var sectionNames = [];
    Object.keys(grouped).forEach(function (key) {
      var label = labels[key] || key;
      var count = grouped[key].length;
      sectionNames.push(label + ', ' + count + ' commands');
    });

    if (sectionNames.length === 0) {
      this.speak('No command sections found');
      return;
    }

    this.showToast('Say a section name to hear its commands', 'info');

    // Build speech: list sections then prompt
    var speech = 'There are ' + sectionNames.length + ' command sections. ';
    speech += sectionNames.join('. ') + '. ';
    speech += 'Say a section name to hear its commands. For example, say "navigation commands".';

    this._speakQueued(speech);
  }

  /**
   * Read all available commands aloud, grouped by category.
   * Each command is queued as a separate utterance so "stop" cancels mid-list.
   */
  listCommands(filterGroup) {
    if (!this.synthesis) return;
    if (typeof AHGVoiceRegistry === 'undefined') {
      this.speak('Voice registry not loaded');
      return;
    }

    // Cancel any previous reading
    this.synthesis.cancel();

    var grouped = AHGVoiceRegistry.getGrouped();
    var labels = AHGVoiceCommands.groupLabels;

    var self = this;
    var utterances = [];

    // Build list of utterances
    Object.keys(grouped).forEach(function (groupKey) {
      var label = labels[groupKey] || groupKey;

      // If filter specified, only read that group
      if (filterGroup && label.toLowerCase().indexOf(filterGroup.toLowerCase()) === -1) {
        return;
      }

      // Group header
      utterances.push(label + ' commands.');

      // Each command in the group
      grouped[groupKey].forEach(function (cmd) {
        var phrases = [];
        if (cmd.patterns) {
          cmd.patterns.forEach(function (p) {
            if (typeof p === 'string') phrases.push(p);
          });
        }
        var phraseText = phrases.length > 0 ? phrases[0] : 'pattern command';
        utterances.push('"' + phraseText + '". ' + cmd.description + '.');
      });

      // Pause between groups
      utterances.push('');
    });

    if (utterances.length === 0) {
      this.speak('No commands found for that section. Say "list sections" to hear available sections.');
      return;
    }

    // Announce start
    var totalCount = 0;
    Object.keys(grouped).forEach(function (k) {
      if (!filterGroup || (labels[k] || k).toLowerCase().indexOf(filterGroup.toLowerCase()) !== -1) {
        totalCount += grouped[k].length;
      }
    });
    this.showToast('Reading ' + totalCount + ' commands. Say "stop" to stop.', 'info');

    // Queue each utterance separately so cancel() stops mid-list
    this._speakQueuedList(utterances);
  }

  /**
   * Speak a single text as queued sentences.
   */
  _speakQueued(text) {
    var self = this;
    var sentences = text.split(/(?<=\.)\s+/).filter(function (s) { return s.trim(); });
    if (!sentences.length) return;

    // Pause recognition while speaking
    var wasListening = this.isListening;
    if (wasListening && this.recognition) {
      this._isSpeaking = true;
      try { this.recognition.stop(); } catch (e) { /* ignore */ }
    }

    sentences.forEach(function (s, i) {
      var u = new SpeechSynthesisUtterance(s);
      u.rate = self.speechRate;
      u.lang = self.language;
      var voices = self.synthesis.getVoices();
      var preferred = voices.find(function (v) { return v.lang.startsWith('en') && v.localService; });
      if (preferred) u.voice = preferred;
      // Resume recognition after last sentence
      if (i === sentences.length - 1) {
        u.onend = function () {
          self._isSpeaking = false;
          if (wasListening) {
            setTimeout(function () { if (!self._isSpeaking) { try { self.recognition.start(); } catch (e) {} } }, 300);
          }
        };
      }
      self.synthesis.speak(u);
    });
  }

  /**
   * Speak an array of text strings as queued utterances.
   */
  _speakQueuedList(utterances) {
    var self = this;
    var items = utterances.filter(function (t) { return !!t; });
    if (!items.length) return;

    // Pause recognition while speaking
    var wasListening = this.isListening;
    if (wasListening && this.recognition) {
      this._isSpeaking = true;
      try { this.recognition.stop(); } catch (e) { /* ignore */ }
    }

    items.forEach(function (text, i) {
      var u = new SpeechSynthesisUtterance(text);
      u.rate = self.speechRate;
      u.pitch = 1.0;
      u.volume = 1.0;
      u.lang = self.language;
      var voices = self.synthesis.getVoices();
      var preferred = voices.find(function (v) { return v.lang.startsWith('en') && v.localService; });
      if (preferred) u.voice = preferred;
      // Resume recognition after last item
      if (i === items.length - 1) {
        u.onend = function () {
          self._isSpeaking = false;
          if (wasListening) {
            setTimeout(function () { if (!self._isSpeaking) { try { self.recognition.start(); } catch (e) {} } }, 300);
          }
        };
      }
      self.synthesis.speak(u);
    });
  }

  /**
   * Detect the specific page type from URL for voice announcements.
   * Returns a human-readable page name.
   */
  _detectPageName() {
    var path = window.location.pathname.replace(/\/index\.php/, '');
    var search = window.location.search || '';

    // Browse pages
    if (/^\/informationobject\/browse/.test(path)) return 'Archival Descriptions';
    if (/^\/actor\/browse/.test(path)) return 'Authority Records';
    if (/^\/repository\/browse/.test(path)) return 'Archival Institutions';
    if (/^\/function\/browse/.test(path)) return 'Functions';
    if (/^\/digitalobject\/browse/.test(path)) return 'Digital Objects';
    if (/^\/term\/browse/.test(path)) {
      // Check taxonomy parameter for Subject vs Places
      var taxMatch = search.match(/taxonomy=(\d+)/);
      if (taxMatch) {
        var taxId = parseInt(taxMatch[1], 10);
        if (taxId === 35) return 'Subjects';
        if (taxId === 42) return 'Places';
        return 'Terms';
      }
      return 'Terms';
    }

    // GLAM sector browse
    if (/^\/library\/?$/.test(path)) return 'Library';
    if (/^\/museum\/?$/.test(path)) return 'Museum';
    if (/^\/gallery\/?$/.test(path)) return 'Gallery';
    if (/^\/dam\/?$/.test(path)) return 'Digital Assets';
    if (/^\/(glam|display)\/browse/.test(path)) return 'Browse';

    // Manage pages
    if (/^\/accession\/browse/.test(path)) return 'Accessions';
    if (/^\/donor\/browse/.test(path)) return 'Donors';
    if (/^\/rightsHolder\/browse/.test(path)) return 'Rights Holders';
    if (/^\/physicalobject\/browse/.test(path)) return 'Physical Storage';

    // Admin pages
    if (/^\/admin\/settings/.test(path)) return 'Settings';
    if (/^\/admin\/menus/.test(path)) return 'Menus';
    if (/^\/admin\/plugins/.test(path)) return 'Plugins';
    if (/^\/admin\/themes/.test(path)) return 'Themes';
    if (/^\/ahgSettings/.test(path)) return 'AHG Settings';
    if (/^\/admin/.test(path)) return 'Administration';

    // User pages
    if (/^\/user\/login/.test(path)) return 'Login';
    if (/^\/user\/browse/.test(path)) return 'Users';
    if (/^\/user/.test(path)) return 'User Profile';

    // Research/reading room
    if (/^\/research/.test(path)) return 'Research Reading Room';

    // Clipboard
    if (/^\/clipboard/.test(path)) return 'Clipboard';

    // Reports
    if (/^\/reports/.test(path) || /^\/ahgReports/.test(path)) return 'Reports';

    // Privacy
    if (/^\/privacy/.test(path)) return 'Privacy';

    // Spectrum
    if (/^\/spectrum/.test(path)) return 'Spectrum Procedures';

    // Heritage
    if (/^\/heritage/.test(path)) return 'Heritage Discovery';

    // Sector-specific record view (library/museum/gallery/dam with slug)
    if (/^\/library\//.test(path)) return 'Library Record';
    if (/^\/museum\//.test(path)) return 'Museum Record';
    if (/^\/gallery\//.test(path)) return 'Gallery Record';
    if (/^\/dam\//.test(path)) return 'Digital Asset Record';

    return null;
  }

  /**
   * Announce what page the user is currently on.
   */
  whereAmI() {
    var parts = [];
    var title = this._getPageTitle();
    var ctx = AHGVoiceCommands.detectContext();
    var path = window.location.pathname;
    var pageName = this._detectPageName();

    // Determine page type (view/edit checked before browse — view pages may contain browse-like elements)
    if (ctx.admin) {
      parts.push('You are on ' + (pageName || 'an admin page'));
    } else if (ctx.edit) {
      parts.push('You are editing a ' + (pageName || 'record'));
    } else if (ctx.view) {
      parts.push('You are viewing ' + (pageName || 'a record'));
    } else if (ctx.browse) {
      parts.push('You are browsing ' + (pageName || 'records'));
      // Count results
      var countText = this._getResultCount();
      if (countText) {
        parts.push(countText);
      }
    } else if (path === '/' || path === '/index.php' || path === '/index.php/') {
      parts.push('You are on the homepage');
    } else {
      parts.push('You are on ' + (pageName || document.title));
    }

    // Add title
    if (title && !ctx.admin) {
      parts.push('Title: ' + title);
    }

    // Check for digital object (broad detection including metadata-only pages)
    var hasImage = typeof AHGVoiceRegistry !== 'undefined' ? AHGVoiceRegistry._hasDigitalObject() : !!document.querySelector('img.img-fluid, .digital-object-viewer, .converted-image-viewer');
    var hasVideo = !!document.querySelector('video');
    var hasAudio = !!document.querySelector('audio');
    if (hasImage) parts.push('This record has a digital object.');
    if (hasVideo) parts.push('This record has a video.');
    if (hasAudio) parts.push('This record has an audio file.');

    // Available actions
    var actions = [];
    if (ctx.view && document.querySelector('a[href*="/edit"]')) actions.push('say "edit" to edit');
    if (ctx.edit) actions.push('say "save" to save');
    if (ctx.browse) actions.push('say "first result" to open the first item');
    if (hasImage) actions.push('say "describe image" for AI description');
    actions.push('say "help" to list all commands');

    if (actions.length) {
      parts.push('You can ' + actions.join(', or '));
    }

    var announcement = parts.join('. ') + '.';
    this.speak(announcement);
    this.showToast(announcement, 'info');
  }

  /**
   * Announce the number of results on a browse page.
   */
  howManyResults() {
    var text = this._getResultCount();
    if (text) {
      this.speak(text);
      this.showToast(text, 'info');
    } else {
      this.speak('No result count found on this page');
      this.showToast('Not on a browse page', 'warning');
    }
  }

  /**
   * Extract result count text from the page DOM.
   * Supports both base AtoM (.result-count) and AHG Display browse.
   */
  _getResultCount() {
    // Base AtoM
    var el = document.querySelector('.result-count');
    if (el) return el.textContent.trim();

    // AHG Display: "Showing N results" in h1
    var h1s = document.querySelectorAll('h1');
    for (var i = 0; i < h1s.length; i++) {
      var t = h1s[i].textContent.trim();
      if (/showing\s+\d+\s+result/i.test(t)) return t;
    }

    // AHG Display: "Results X to Y of Z"
    var smalls = document.querySelectorAll('.text-muted.small, .text-muted small');
    for (var i = 0; i < smalls.length; i++) {
      var t = smalls[i].textContent.trim();
      if (/results?\s+\d+\s+to\s+\d+/i.test(t)) return t;
    }

    return null;
  }

  /**
   * Auto-announce page context on load (for blind users).
   * Only speaks if voice was recently active (not on every cold page load).
   */
  _announcePageContext() {
    // Only auto-announce if the user has previously activated voice in this session
    if (!sessionStorage.getItem('ahg_voice_active')) return;

    var path = window.location.pathname;
    var ctx = AHGVoiceCommands.detectContext();
    var pageName = this._detectPageName();
    var announcement = '';

    var title = this._getPageTitle();

    if (path === '/' || path === '/index.php' || path === '/index.php/') {
      announcement = 'Homepage loaded. Say a command or say help for options.';
    } else if (ctx.edit) {
      announcement = (pageName || 'Edit page') + ' loaded' + (title ? ', ' + title : '') + '. Say "save" when done.';
    } else if (ctx.view) {
      announcement = (pageName || 'Record') + ' loaded' + (title ? ', ' + title : '') + '.';
    } else if (ctx.browse) {
      var count = this._getResultCount() || '';
      announcement = (pageName || 'Browse') + ' loaded.' + (count ? ' ' + count + '.' : '') + ' Say "first result" to open the first item.';
    } else if (ctx.admin) {
      announcement = (pageName || 'Admin page') + ' loaded.';
    } else if (title) {
      announcement = (pageName ? pageName + ', ' : '') + title + ' loaded.';
    } else if (pageName) {
      announcement = pageName + ' loaded.';
    }

    if (announcement) {
      this.speak(announcement);
    }
  }

  /**
   * Mouseover read-aloud for buttons, links, and interactive elements.
   * Only active when voice mode is on. Debounced to avoid rapid-fire speech.
   */
  _initMouseoverRead() {
    var self = this;
    var lastSpoken = '';
    var hoverTimer = null;

    document.addEventListener('mouseover', function (e) {
      // Check if hover read is enabled
      if (!self._hoverReadEnabled) return;
      // Only read when voice is active
      if (!sessionStorage.getItem('ahg_voice_active')) return;
      // Don't interrupt ongoing speech from commands
      if (self._isSpeaking) return;

      // Find the nearest interactive element
      var el = e.target.closest('a, button, [role="button"], input[type="submit"], input[type="button"], .btn, .nav-link, .dropdown-item, [role="tab"], [role="menuitem"]');
      if (!el) return;

      // Skip voice UI controls themselves
      if (el.closest('.voice-ui, #voice-help-modal, .voice-toast-container')) return;

      // Get readable text
      var text = '';
      // aria-label takes priority
      if (el.getAttribute('aria-label')) {
        text = el.getAttribute('aria-label');
      } else if (el.getAttribute('title')) {
        text = el.getAttribute('title');
      } else {
        // Get visible text, strip icons
        text = (el.textContent || '').replace(/[\n\r]+/g, ' ').replace(/\s+/g, ' ').trim();
      }

      // For inputs, use value
      if (!text && (el.tagName === 'INPUT')) {
        text = el.value || el.getAttribute('placeholder') || '';
      }

      // Skip empty or very short (icon-only buttons without labels)
      if (!text || text.length < 2) return;

      // Truncate very long text
      if (text.length > 80) text = text.substring(0, 80);

      // Don't repeat the same element
      if (text === lastSpoken) return;

      // Clear previous hover timer
      if (hoverTimer) clearTimeout(hoverTimer);

      // Delay before reading — user must hover intentionally (configurable in settings)
      hoverTimer = setTimeout(function () {
        if (self._isSpeaking) return;
        lastSpoken = text;

        // Use a quick, short utterance (don't go through full speak() which pauses recognition)
        if (self.synthesis) {
          self.synthesis.cancel();
          var u = new SpeechSynthesisUtterance(text);
          u.rate = Math.min(self.speechRate + 0.2, 2.0); // Slightly faster for hover reads
          u.volume = 0.8;
          u.lang = self.language;
          var voices = self.synthesis.getVoices();
          var preferred = voices.find(function (v) { return v.lang.startsWith('en') && v.localService; });
          if (preferred) u.voice = preferred;
          self.synthesis.speak(u);
        }
      }, self._hoverReadDelay);
    });

    // Clear on mouseout to cancel pending reads
    document.addEventListener('mouseout', function (e) {
      var el = e.target.closest('a, button, [role="button"], input[type="submit"], input[type="button"], .btn, .nav-link, .dropdown-item, [role="tab"], [role="menuitem"]');
      if (el && hoverTimer) {
        clearTimeout(hoverTimer);
        hoverTimer = null;
        // Cancel hover speech if still queued
        if (self.synthesis && !self._isSpeaking) {
          self.synthesis.cancel();
        }
        lastSpoken = '';
      }
    });
  }

  /**
   * Adjust speech rate.
   */
  adjustSpeechRate(delta) {
    this.speechRate = Math.max(0.5, Math.min(2.0, this.speechRate + delta));
    this.showToast('Speech rate: ' + this.speechRate.toFixed(1) + 'x', 'info');
  }

  /**
   * Gather all available metadata from the current page DOM.
   */
  _gatherPageMetadata() {
    var meta = {
      hasDigitalObject: false,
      title: null,
      description: null,
      mediaType: null,
      mimeType: null,
      fileSize: null,
      dimensions: null,
      rights: null,
      altText: null,
      digitalObjectId: null,
      informationObjectId: null
    };

    // Check for digital object viewer (broad selectors — gallery/museum/archive/library templates all differ)
    var img = document.querySelector('.digital-object-reference img, .converted-image-viewer img.img-fluid, #content img.img-fluid, #sidebar img.img-fluid, .digital-object-viewer img, #wrapper img.img-fluid');
    var video = document.querySelector('#content video, #wrapper video, .digital-object-viewer video');
    var audio = document.querySelector('#content audio, #wrapper audio, .digital-object-viewer audio');

    if (img || video || audio) {
      meta.hasDigitalObject = true;
    }

    // Also detect via IIIF viewer, OpenSeadragon, or 3D viewer containers
    if (!meta.hasDigitalObject) {
      if (document.querySelector('.iiif-viewer-container, .osd-viewer, [id^="container-iiif-viewer"], [id^="viewer-3d-"]')) {
        meta.hasDigitalObject = true;
        if (!meta.mediaType) meta.mediaType = 'image';
      }
    }

    // Digital object metadata section
    if (!meta.hasDigitalObject) {
      var doMeta = document.querySelector('.digitalObjectMetadata, #digitalObjectMetadata, .digital-object-metadata');
      if (doMeta) {
        meta.hasDigitalObject = true;
      }
    }
    // Check for field labels that indicate a digital object is present
    if (!meta.hasDigitalObject) {
      var allFields = document.querySelectorAll('#content .field, #content tr, #content dt, #content h3, #content .row, #wrapper .field, #wrapper tr');
      for (var fi = 0; fi < allFields.length; fi++) {
        var ft = allFields[fi].textContent || '';
        if (/master\s*file|media\s*type|mime[\s-]*type|original\s*file/i.test(ft)) {
          meta.hasDigitalObject = true;
          break;
        }
      }
    }

    // Alt text from image
    if (img) {
      meta.altText = img.getAttribute('alt') || null;
      meta.mediaType = 'image';
    }
    if (video) { meta.mediaType = 'video'; }
    if (audio) { meta.mediaType = 'audio'; }

    // Try to get digital object ID from DOM
    var doIdEl = document.querySelector('[id^="convert-img-"], [id^="archive-content-"], [id^="text-content-"], [id^="viewer-3d-"]');
    if (doIdEl) {
      var idMatch = doIdEl.id.match(/(\d+)$/);
      if (idMatch) meta.digitalObjectId = parseInt(idMatch[1], 10);
    }

    // Try data-do-id attribute (ahgIiifPlugin media player)
    if (!meta.digitalObjectId) {
      var doIdAttr = document.querySelector('[data-do-id]');
      if (doIdAttr) meta.digitalObjectId = parseInt(doIdAttr.getAttribute('data-do-id'), 10);
    }

    // IIIF viewer container IDs contain the INFORMATION OBJECT ID (not digital object ID).
    // Do NOT store as digitalObjectId — describeImage() handles this in Strategy 4b.
    if (!meta.digitalObjectId) {
      var iiifContainer = document.querySelector('[id^="container-iiif-viewer-"]');
      if (iiifContainer) {
        var iiifMatch = iiifContainer.id.match(/container-iiif-viewer-(\d+)/);
        if (iiifMatch) meta.informationObjectId = parseInt(iiifMatch[1], 10);
      }
    }

    // Card header may show media type label
    var cardHeader = document.querySelector('.converted-image-viewer .card-header span, .card-header span');
    if (cardHeader) {
      var label = cardHeader.textContent.trim();
      if (label && label !== '') meta.mediaType = label.toLowerCase();
    }

    // Title
    meta.title = this._getPageTitle();

    // Description
    meta.description = this._getPageDescription();

    // File size — look in metadata fields
    var fields = document.querySelectorAll('#content .field, .row.mb-3, .digitalObjectMetadata tr, .field-group');
    fields.forEach(function (field) {
      var text = field.textContent || '';
      if (/file\s*size/i.test(text)) {
        var val = text.replace(/.*file\s*size[:\s]*/i, '').trim();
        if (val) meta.fileSize = val.split('\n')[0].trim();
      }
      if (/dimensions/i.test(text)) {
        var val = text.replace(/.*dimensions[:\s]*/i, '').trim();
        if (val) meta.dimensions = val.split('\n')[0].trim();
      }
      if (/mime\s*type/i.test(text)) {
        var val = text.replace(/.*mime\s*type[:\s]*/i, '').trim();
        if (val) meta.mimeType = val.split('\n')[0].trim();
      }
    });

    // Image dimensions from natural size
    if (img && img.naturalWidth) {
      meta.dimensions = meta.dimensions || (img.naturalWidth + ' by ' + img.naturalHeight + ' pixels');
    }

    // Rights
    var rightsEl = document.querySelector('.rights-area, [class*="rights"], .embargo-notice, #rights-collapse');
    if (rightsEl) {
      meta.rights = rightsEl.textContent.trim().substring(0, 200);
    }

    return meta;
  }

  /**
   * Get the page title from DOM.
   */
  _getPageTitle() {
    // Standard h1 title (base AtoM, Display browse, library/museum/gallery/dam)
    var titleEl = document.querySelector('h1.mb-0, .multiline-header h1, #content > h1, #main-column > h1, [role="main"] > h1, h1[property="dc:title"]');
    if (titleEl) return titleEl.textContent.trim();

    // CCO/Gallery: Title field in row layout (.col-md-3 "Title" + .col-md-9 value)
    var rows = document.querySelectorAll('#tts-content-area .row.mb-3, #wrapper .row.mb-3');
    for (var i = 0; i < rows.length; i++) {
      var label = rows[i].querySelector('.col-md-3');
      if (label && /^title$/i.test(label.textContent.trim())) {
        var val = rows[i].querySelector('.col-md-9');
        if (val) { var t = val.textContent.trim(); if (t) return t; }
      }
    }

    // ISAD: Title field in .field.row layout (h3 "Title" + .col-9 value)
    var fields = document.querySelectorAll('#tts-content-area .field.row, #content .field.row');
    for (var i = 0; i < fields.length; i++) {
      var label = fields[i].querySelector('h3, .col-3');
      if (label && /^title$/i.test(label.textContent.trim())) {
        var val = fields[i].querySelector('.col-9');
        if (val) { var t = val.textContent.trim(); if (t) return t; }
      }
    }

    // Fallback to document title (strip site name suffix)
    var docTitle = document.title.replace(/\s*[-|].*$/, '').trim();
    return docTitle || null;
  }

  /**
   * Get the page description from DOM.
   */
  _getPageDescription() {
    // Try scope and content first
    var desc = document.querySelector('.scope-and-content, div[class*="scope"], #description-collapse .field-value');
    if (desc) {
      var text = desc.textContent.trim();
      if (text) return text;
    }
    // Try general description
    desc = document.querySelector('.description, #content .field .description, div.description');
    if (desc) {
      var text = desc.textContent.trim();
      if (text) return text;
    }
    return null;
  }

  // ---------------------------------------------------------------
  //  AI Image Description (Phase 5)
  // ---------------------------------------------------------------

  /**
   * AI-powered image description via server-side Ollama/Cloud LLM.
   */
  describeImage() {
    var meta = this._gatherPageMetadata();
    console.log('[Voice] describeImage: hasDigitalObject=' + meta.hasDigitalObject + ', digitalObjectId=' + meta.digitalObjectId + ', informationObjectId=' + meta.informationObjectId);

    if (!meta.hasDigitalObject) {
      this.speak('No digital object found on this page');
      this.showToast('No digital object on this page', 'warning');
      return;
    }

    // Try multiple strategies to find digital object ID
    var doId = meta.digitalObjectId;
    console.log('[Voice] Strategy 0 (metadata): doId=' + doId + ', infoObjId=' + meta.informationObjectId);

    // Strategy 1: data-do-id attribute (ahgIiifPlugin media player)
    if (!doId) {
      var doIdEl = document.querySelector('[data-do-id]');
      if (doIdEl) doId = parseInt(doIdEl.getAttribute('data-do-id'), 10);
      console.log('[Voice] Strategy 1 (data-do-id): doId=' + doId);
    }

    // Strategy 2: media player container ID (media-player-{ID})
    if (!doId) {
      var playerEl = document.querySelector('.ahg-media-player[id^="media-player-"]');
      if (playerEl) {
        var pidMatch = playerEl.id.match(/(\d+)$/);
        if (pidMatch) doId = parseInt(pidMatch[1], 10);
      }
      console.log('[Voice] Strategy 2 (media-player): doId=' + doId);
    }

    // Strategy 3: links to digitalobject or download links
    if (!doId) {
      var doLink = document.querySelector('a[href*="digitalobject/"], a[download]');
      if (doLink) {
        // Match: digitalobject/{ID}, digitalobject/edit/id/{ID}, /uploads/...{ID}
        var hrefMatch = doLink.href.match(/digitalobject\/(\d+)|digitalobject\/edit\/id\/(\d+)|\/uploads\/.*?(\d+)/);
        if (hrefMatch) doId = parseInt(hrefMatch[1] || hrefMatch[2] || hrefMatch[3], 10);
      }
      console.log('[Voice] Strategy 3 (DO link): doId=' + doId + (doLink ? ', href=' + doLink.href : ''));
    }

    // Strategy 4: data-object-id (information object ID — server can resolve DO from this)
    var infoObjectId = meta.informationObjectId || null;
    if (!doId && !infoObjectId) {
      var ioEl = document.querySelector('[data-object-id], #tpmInformationObjectId');
      if (ioEl) {
        infoObjectId = ioEl.getAttribute('data-object-id') || ioEl.value;
      }
      console.log('[Voice] Strategy 4 (data-object-id): infoObjectId=' + infoObjectId);
    }

    // Strategy 4b: IIIF viewer container IDs contain the information object ID
    // Patterns: container-iiif-viewer-{objectId}-{hash}, osd-iiif-viewer-{objectId}-{hash}, osd-{objectId}, mirador-{objectId}-wrapper
    if (!doId && !infoObjectId) {
      var iiifEl = document.querySelector('[id^="container-iiif-viewer-"], [id^="osd-iiif-viewer-"], [id^="osd-"], [id^="mirador-"]');
      if (iiifEl) {
        var idMatch = iiifEl.id.match(/(?:container-iiif-viewer|osd-iiif-viewer|osd|mirador)-(\d+)/);
        if (idMatch) infoObjectId = parseInt(idMatch[1], 10);
        console.log('[Voice] Strategy 4b (IIIF container): id=' + iiifEl.id + ', infoObjectId=' + infoObjectId);
      } else {
        console.log('[Voice] Strategy 4b (IIIF container): no IIIF element found');
      }
    }

    // Strategy 5: extract slug from URL as final fallback — server resolves DO
    var slug = null;
    if (!doId && !infoObjectId) {
      var path = window.location.pathname.replace(/\/index\.php/, '');
      // Remove known non-slug paths
      if (path && path !== '/' && !/^\/(admin|user|search|glam|display|clipboard|donor|accession|repository|actor|taxonomy|function|research)/.test(path)) {
        slug = path.replace(/^\//, '').replace(/\?.*$/, '');
        // Strip template parameter from slug (e.g., /museum/test-opensearch?template=isad → museum/test-opensearch)
        if (slug) slug = slug.split('?')[0];
      }
      console.log('[Voice] Strategy 5 (URL slug): slug=' + slug);
    }

    console.log('[Voice] Final: doId=' + doId + ', infoObjectId=' + infoObjectId + ', slug=' + slug);

    if (!doId && !infoObjectId && !slug) {
      this.speak('Cannot identify the digital object. Try from a record view page.');
      this.showToast('Digital object ID not found', 'warning');
      return;
    }

    var self = this;
    this.speak('Analyzing image, please wait. This may take up to a minute.');
    this.showToast('AI analyzing image...', 'info');

    // Show processing state on indicator
    if (this.indicator) {
      this.indicator.classList.add('voice-indicator-processing');
    }

    // Periodic patience reminders while waiting
    var waitMessages = [
      'Still analyzing, almost there.',
      'Processing the image, please hold on.',
      'AI is working on the description.'
    ];
    var waitIdx = 0;
    var patienceTimer = setInterval(function () {
      if (waitIdx < waitMessages.length) {
        self.showToast(waitMessages[waitIdx], 'info');
        self.speak(waitMessages[waitIdx]);
        waitIdx++;
      }
    }, 15000);

    // Get CSRF token if available
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfInput = document.querySelector('input[name="csrf_token"], input[name="_csrf_token"]');
    var csrfToken = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '';

    var formData = new FormData();
    if (doId) formData.append('digital_object_id', doId);
    if (infoObjectId) formData.append('information_object_id', infoObjectId);
    if (slug) formData.append('slug', slug);
    if (csrfToken) formData.append('csrf_token', csrfToken);

    fetch('/index.php/ahgVoice/describeImage', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      clearInterval(patienceTimer);
      if (self.indicator) self.indicator.classList.remove('voice-indicator-processing');

      if (data.success) {
        var source = data.source || 'AI';
        self.speak(data.description);
        self.showToast('Description generated (' + source + ')', 'success');

        // Store for save flow
        self._pendingDescription = data.description;
        self._pendingDoId = doId;
        self._pendingInfoObjectId = data.information_object_id || null;

        // After speaking, prompt for save
        setTimeout(function () {
          self._promptSaveDescription();
        }, Math.max(2000, data.description.length * 50));
      } else {
        self.speak(data.error || 'AI description failed');
        self.showToast(data.error || 'AI description failed', 'danger');
      }
    })
    .catch(function (err) {
      clearInterval(patienceTimer);
      if (self.indicator) self.indicator.classList.remove('voice-indicator-processing');
      console.error('[Voice] AI describe error:', err);
      var errMsg = 'Failed to contact AI service';
      if (err && err.message) {
        if (err.message.indexOf('JSON') !== -1) {
          errMsg = 'Server timed out processing the image. Try again or check AI service status.';
        } else {
          errMsg = 'AI service error: ' + err.message;
        }
      }
      self.speak(errMsg);
      self.showToast(errMsg, 'danger');
    });
  }

  /**
   * AI-powered 3D object description via multi-angle Blender renders + LLM.
   * @param {boolean} force - If true, bypass cached AI description and re-describe
   */
  describeObject(force) {
    var meta = this._gatherPageMetadata();
    console.log('[Voice] describeObject: hasDigitalObject=' + meta.hasDigitalObject + ', force=' + !!force);

    // Detect 3D viewer on page
    var viewer3d = document.querySelector('[id^="viewer-3d-"], model-viewer, .three-js-viewer, .gaussian-splat-viewer');
    if (!viewer3d && !meta.hasDigitalObject) {
      this.speak('No 3D object found on this page');
      this.showToast('No 3D object on this page', 'warning');
      return;
    }

    // Reuse same ID resolution strategies as describeImage
    var doId = meta.digitalObjectId;
    if (!doId) {
      var doIdEl = document.querySelector('[data-do-id]');
      if (doIdEl) doId = parseInt(doIdEl.getAttribute('data-do-id'), 10);
    }
    if (!doId) {
      var playerEl = document.querySelector('[id^="viewer-3d-"]');
      if (playerEl) {
        var pidMatch = playerEl.id.match(/(\d+)$/);
        if (pidMatch) doId = parseInt(pidMatch[1], 10);
      }
    }

    var infoObjectId = meta.informationObjectId || null;
    if (!doId && !infoObjectId) {
      var ioEl = document.querySelector('[data-object-id], #tpmInformationObjectId');
      if (ioEl) infoObjectId = ioEl.getAttribute('data-object-id') || ioEl.value;
    }
    if (!doId && !infoObjectId) {
      var iiifEl = document.querySelector('[id^="container-iiif-viewer-"], [id^="osd-iiif-viewer-"], [id^="osd-"], [id^="mirador-"]');
      if (iiifEl) {
        var idMatch = iiifEl.id.match(/(?:container-iiif-viewer|osd-iiif-viewer|osd|mirador)-(\d+)/);
        if (idMatch) infoObjectId = parseInt(idMatch[1], 10);
      }
    }

    var slug = null;
    if (!doId && !infoObjectId) {
      var path = window.location.pathname.replace(/\/index\.php/, '');
      if (path && path !== '/' && !/^\/(admin|user|search|glam|display|clipboard|donor|accession|repository|actor|taxonomy|function|research)/.test(path)) {
        slug = path.replace(/^\//, '').replace(/\?.*$/, '').split('?')[0];
      }
    }

    if (!doId && !infoObjectId && !slug) {
      this.speak('Cannot identify the 3D object. Try from a record view page.');
      this.showToast('3D object ID not found', 'warning');
      return;
    }

    var self = this;
    this.speak(force ? 'Redescribing 3D object, this may take a moment.' : 'Checking for existing description...');
    this.showToast(force ? 'Re-generating 3D description...' : 'Checking AI description...', 'info');

    if (this.indicator) {
      this.indicator.classList.add('voice-indicator-processing');
    }

    var waitMessages = [
      'Still rendering, almost there.',
      'Processing 3D model, please hold on.',
      'AI is analyzing the 3D object.'
    ];
    var waitIdx = 0;
    var patienceTimer = setInterval(function () {
      if (waitIdx < waitMessages.length) {
        self.showToast(waitMessages[waitIdx], 'info');
        self.speak(waitMessages[waitIdx]);
        waitIdx++;
      }
    }, 20000);

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfInput = document.querySelector('input[name="csrf_token"], input[name="_csrf_token"]');
    var csrfToken = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '';

    var formData = new FormData();
    if (doId) formData.append('digital_object_id', doId);
    if (infoObjectId) formData.append('information_object_id', infoObjectId);
    if (slug) formData.append('slug', slug);
    if (force) formData.append('force', '1');
    if (csrfToken) formData.append('csrf_token', csrfToken);

    fetch('/index.php/ahgVoice/describeObject', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      clearInterval(patienceTimer);
      if (self.indicator) self.indicator.classList.remove('voice-indicator-processing');

      if (data.success) {
        // If returned from cached field, just read it — no save prompt
        if (data.from_field) {
          self.speak(data.description);
          self.showToast('Reading existing AI description from extent and medium', 'success');
          // Offer redescribe option
          setTimeout(function () {
            self.speak('This is the existing AI description. Say redescribe object to generate a new one.');
            self.showToast('Say "redescribe object" for a fresh AI description', 'info');
          }, Math.max(2000, data.description.length * 50));
          return;
        }

        var source = data.source || 'AI';
        var renderInfo = data.render_count ? ' (' + data.render_count + ' views, ' + source + ')' : '';
        self.speak(data.description);
        self.showToast('3D description generated' + renderInfo, 'success');

        // Store for save flow (reuses existing saveDescription endpoint)
        self._pendingDescription = data.description;
        self._pendingDoId = doId;
        self._pendingInfoObjectId = data.information_object_id || null;

        setTimeout(function () {
          self._promptSaveDescription();
        }, Math.max(2000, data.description.length * 50));
      } else {
        self.speak(data.error || '3D description failed');
        self.showToast(data.error || '3D description failed', 'danger');
      }
    })
    .catch(function (err) {
      clearInterval(patienceTimer);
      if (self.indicator) self.indicator.classList.remove('voice-indicator-processing');
      console.error('[Voice] 3D describe error:', err);
      var errMsg = 'Failed to contact AI service';
      if (err && err.message) {
        if (err.message.indexOf('JSON') !== -1) {
          errMsg = 'Server timed out processing 3D renders. The AI model may need more time.';
        } else {
          errMsg = 'AI service error: ' + err.message;
        }
      }
      self.speak(errMsg);
      self.showToast(errMsg, 'danger');
    });
  }

  /**
   * Force re-describe a 3D object, bypassing cached AI description.
   * Replaces extent_and_medium with fresh AI content.
   */
  redescribeObject() {
    this.describeObject(true);
  }

  /**
   * Prompt user to save the AI-generated description.
   */
  _promptSaveDescription() {
    if (!this._pendingDescription) return;

    this.speak('Would you like to save this description? Say yes to save to extent and medium if empty, or say save to description, save to alt text, save to both, or discard.');
    this.showToast('Say "yes", "save to description", "save to extent and medium", "save to alt text", "save to both", or "discard"', 'info');
    this._awaitingSaveCommand = true;
  }

  /**
   * Execute save of AI description to server.
   */
  saveDescription(target, mode) {
    if (!this._pendingDescription || !this._pendingInfoObjectId) {
      this.speak('No pending description to save');
      return;
    }

    var self = this;
    var formData = new FormData();
    formData.append('information_object_id', this._pendingInfoObjectId);
    formData.append('description', this._pendingDescription);
    formData.append('save_target', target); // 'description' | 'alt_text' | 'both'
    formData.append('save_mode', mode || 'replace');

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfInput = document.querySelector('input[name="csrf_token"], input[name="_csrf_token"]');
    var csrfToken = (csrfMeta && csrfMeta.content) || (csrfInput && csrfInput.value) || '';
    if (csrfToken) formData.append('csrf_token', csrfToken);

    fetch('/index.php/ahgVoice/saveDescription', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin'
    })
    .then(function (response) { return response.json(); })
    .then(function (data) {
      if (data.success) {
        self.speak('Description saved');
        self.showToast('Description saved to ' + target, 'success');
      } else {
        self.speak(data.error || 'Save failed');
        self.showToast(data.error || 'Save failed', 'danger');
      }
      self._pendingDescription = null;
      self._pendingInfoObjectId = null;
      self._awaitingSaveCommand = false;
    })
    .catch(function (err) {
      console.error('[Voice] Save description error:', err);
      self.speak('Failed to save description');
      self.showToast('Save error', 'danger');
    });
  }

  /**
   * Discard the pending AI description.
   */
  discardDescription() {
    this._pendingDescription = null;
    this._pendingInfoObjectId = null;
    this._awaitingSaveCommand = false;
    this.speak('Description discarded');
    this.showToast('Description discarded', 'info');
  }

  // ---------------------------------------------------------------
  //  Private
  // ---------------------------------------------------------------

  /**
   * Toggle the type-a-command input popup above the floating button.
   */
  _toggleTypeInput() {
    var existing = document.getElementById('voice-type-popup');
    if (existing) {
      existing.remove();
      return;
    }

    var popup = document.createElement('div');
    popup.id = 'voice-type-popup';
    popup.className = 'voice-type-popup';

    var input = document.createElement('input');
    input.type = 'text';
    input.className = 'voice-type-input';
    input.placeholder = 'Type a command...';
    input.autocomplete = 'off';
    input.spellcheck = false;

    var self = this;

    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && this.value.trim()) {
        var text = this.value.trim();
        popup.remove();
        self.processCommand(text, 1.0);
      }
      if (e.key === 'Escape') {
        popup.remove();
      }
    });

    // Close on outside click
    var closeHandler = function (e) {
      if (!popup.contains(e.target) && e.target !== self.floatingBtn) {
        popup.remove();
        document.removeEventListener('mousedown', closeHandler);
      }
    };
    setTimeout(function () {
      document.addEventListener('mousedown', closeHandler);
    }, 10);

    popup.appendChild(input);
    document.body.appendChild(popup);

    // Position above floating button
    if (this.floatingBtn) {
      var rect = this.floatingBtn.getBoundingClientRect();
      popup.style.position = 'fixed';
      popup.style.bottom = (window.innerHeight - rect.top + 8) + 'px';
      popup.style.right = (window.innerWidth - rect.right) + 'px';
    }

    input.focus();
  }

  /**
   * Inject a mic button into the navbar (next to search).
   * Done via JS to avoid modifying the header template.
   */
  _injectNavbarButton() {
    // Find the navbar-nav ul that contains the main menu items
    var navbarNav = document.querySelector('#top-bar .navbar-nav');
    if (!navbarNav) return;

    var li = document.createElement('li');
    li.className = 'nav-item d-none d-sm-flex align-items-center';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'voice-navbar-btn';
    btn.className = 'voice-navbar-btn';
    btn.setAttribute('aria-label', 'Voice commands');
    btn.title = 'Voice commands';
    btn.innerHTML = '<i class="bi bi-mic"></i>';

    li.appendChild(btn);

    // Insert as first child of the nav
    navbarNav.insertBefore(li, navbarNav.firstChild);

    this.navbarBtn = btn;
  }

  // ---------------------------------------------------------------
  //  Context Detection (Phase 2)
  // ---------------------------------------------------------------

  /**
   * Detect the current page context.
   * Returns an object with boolean flags for each context type.
   */
  static detectContext() {
    // Note: #counts-block is the clipboard counter in the navbar (on every page) — do NOT use for browse detection
    return {
      edit: !!document.querySelector('form#editForm, form.form-edit, body.edit form'),
      view: !!document.querySelector('.informationObject, .section, #tts-content-area, body.show'),
      browse: !!document.querySelector('.result-count, .pager, .pagination, .browse-results, .display.browse'),
      admin: /\/admin|\/ahgSettings/.test(window.location.pathname)
    };
  }

  /**
   * Briefly highlight an element to give visual feedback.
   */
  highlightElement(el) {
    if (!el) return;
    el.classList.add('voice-highlight');
    setTimeout(() => el.classList.remove('voice-highlight'), 600);
  }

  /**
   * Find and click an element, with highlight feedback.
   * Returns true if element was found and clicked.
   */
  clickElement(selector) {
    var el = document.querySelector(selector);
    if (el) {
      this.highlightElement(el);
      setTimeout(() => el.click(), 150);
      return true;
    }
    return false;
  }

  _bindRecognitionEvents() {
    this.recognition.addEventListener('result', (event) => {
      // Ignore results while system is speaking (prevents self-hearing)
      if (this._isSpeaking) return;

      const result = event.results[event.results.length - 1];
      const transcript = result[0].transcript;
      const confidence = result[0].confidence;

      if (this.mode === 'dictation') {
        // In dictation mode, handle interim + final results
        this._processDictation(transcript, result.isFinal);
      } else if (result.isFinal) {
        // In command mode, only process final results
        this.processCommand(transcript, confidence);
      }
    });

    this.recognition.addEventListener('end', () => {
      // If recognition stopped because we're speaking, don't auto-restart here
      // (the speak() onend handler will restart it)
      if (this._isSpeaking) return;

      if (this.mode === 'dictation' && this.isListening) {
        // In dictation mode, auto-restart recognition (continuous listening)
        try { this.recognition.start(); } catch (e) { /* ignore */ }
        return;
      }
      if (this._continuousMode && this.isListening) {
        // Continuous command mode — auto-restart after each command.
        // Use _isAutoRestart flag so startListening() doesn't speak "Listening" again.
        var self = this;
        this.isListening = false; // Temporarily set to false so startListening() works
        setTimeout(function () {
          self._isAutoRestart = true;
          self.startListening();
        }, 300);
        return;
      }
      this.isListening = false;
      this._updateUI(false);
    });

    this.recognition.addEventListener('error', (event) => {
      // Non-fatal errors that can occur naturally during pauses
      var nonFatal = (event.error === 'no-speech' || event.error === 'aborted');

      if (event.error === 'not-allowed') {
        this.showToast('Microphone access denied. Please enable it in browser settings.', 'danger');
        this.isListening = false;
        this._updateUI(false);
      } else if (nonFatal && (this._continuousMode || this.mode === 'dictation')) {
        // In continuous/dictation mode, non-fatal errors should NOT kill listening.
        // The 'end' event will fire next and handle auto-restart.
        console.log('[Voice] Non-fatal error in continuous mode: ' + event.error + ' — will auto-restart');
      } else if (event.error === 'no-speech') {
        this.showToast('No speech detected', 'warning');
        this.isListening = false;
        this._updateUI(false);
      } else if (event.error !== 'aborted') {
        this.showToast('Recognition error: ' + event.error, 'danger');
        this.isListening = false;
        this._updateUI(false);
      } else {
        // 'aborted' in non-continuous mode
        this.isListening = false;
        this._updateUI(false);
      }
    });
  }

  _updateUI(listening) {
    // Navbar button
    if (this.navbarBtn) {
      const icon = this.navbarBtn.querySelector('i');
      if (listening) {
        this.navbarBtn.classList.add('voice-active');
        if (icon) { icon.className = 'bi bi-mic-fill'; }
      } else {
        this.navbarBtn.classList.remove('voice-active');
        if (icon) { icon.className = 'bi bi-mic'; }
      }
    }

    // Floating button
    if (this.floatingBtn) {
      if (listening) {
        this.floatingBtn.classList.add('voice-active');
        this.floatingBtn.classList.toggle('voice-dictating', this.mode === 'dictation');
      } else {
        this.floatingBtn.classList.remove('voice-active', 'voice-dictating');
      }
    }

    // Indicator bar — blue for command mode, green for dictation
    if (this.indicator) {
      this.indicator.classList.toggle('voice-indicator-active', listening && this.mode !== 'dictation');
      this.indicator.classList.toggle('voice-indicator-dictation', listening && this.mode === 'dictation');
    }
  }

  /**
   * Match transcript against a command definition.
   */
  _matchCommand(text, cmd) {
    if (cmd.pattern instanceof RegExp) {
      return cmd.pattern.test(text);
    }
    if (typeof cmd.pattern === 'string') {
      if (text === cmd.pattern) return true;
    }
    if (Array.isArray(cmd.patterns)) {
      // Get patterns with translations merged in
      var patterns = this._getTranslatedPatterns(cmd.patterns);
      for (var i = 0; i < patterns.length; i++) {
        var p = patterns[i];
        if (p instanceof RegExp && p.test(text)) return true;
        if (typeof p === 'string' && text === p) return true;
      }
    }
    return false;
  }

  /**
   * Get command patterns merged with translations for the current language.
   * Caches per language to avoid rebuilding every match.
   */
  _getTranslatedPatterns(originalPatterns) {
    if (typeof AHGVoiceTranslations === 'undefined') return originalPatterns;
    if (!this.language || this.language === 'en-US' || this.language === 'en-GB') return originalPatterns;
    return AHGVoiceTranslations.mergePatterns(originalPatterns, this.language);
  }

  _escHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  _loadSettings() {
    var self = this;
    fetch('/index.php/ahgVoice/getSettings', { credentials: 'same-origin' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var s = data.settings;
        if (s.voice_enabled === 'false') {
            document.querySelectorAll('.voice-ui').forEach(function(el) { el.style.display = 'none'; });
            return;
        }
        if (s.voice_language) { self.language = s.voice_language; if (self.recognition) self.recognition.lang = s.voice_language; }
        if (s.voice_confidence_threshold) self.confidenceThreshold = parseFloat(s.voice_confidence_threshold);
        if (s.voice_speech_rate) self.speechRate = parseFloat(s.voice_speech_rate);
        if (s.voice_show_floating_btn === 'false' && self.floatingBtn) self.floatingBtn.style.display = 'none';
        if (s.voice_continuous_listening === 'true') self._continuousMode = true;
        if (s.voice_hover_read_enabled !== undefined) self._hoverReadEnabled = s.voice_hover_read_enabled !== 'false';
        if (s.voice_hover_read_delay) self._hoverReadDelay = parseInt(s.voice_hover_read_delay, 10) || 400;
        console.log('[Voice] Settings loaded:', s);
    })
    .catch(function(err) { console.log('[Voice] Settings not available, using defaults'); });
  }

  _findClosestCommand(text, commands) {
    var bestMatch = null;
    var bestCmd = null;
    var bestScore = 0;
    for (var i = 0; i < commands.length; i++) {
        var cmd = commands[i];
        var patterns = cmd.patterns || (cmd.pattern ? [cmd.pattern] : []);
        for (var j = 0; j < patterns.length; j++) {
            var p = patterns[j];
            if (typeof p !== 'string') continue;
            // Simple contains check
            if (p.indexOf(text) !== -1 || text.indexOf(p) !== -1) {
                var score = p.length;
                if (score > bestScore) { bestScore = score; bestMatch = p; bestCmd = cmd; }
            }
            // Check word overlap
            var pWords = p.split(' ');
            var tWords = text.split(' ');
            var overlap = 0;
            for (var k = 0; k < tWords.length; k++) {
                if (pWords.indexOf(tWords[k]) !== -1) overlap++;
            }
            if (overlap > 0 && overlap > bestScore) { bestScore = overlap; bestMatch = p; bestCmd = cmd; }
        }
    }
    if (bestMatch) {
      return { pattern: bestMatch, command: bestCmd };
    }
    return null;
  }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
  window.ahgVoice = new AHGVoiceCommands();
  window.ahgVoice.init();
});
