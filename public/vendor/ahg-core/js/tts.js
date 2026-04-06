/**
 * AHG Text-to-Speech (TTS) Component
 * Uses Web Speech API for browser-native text-to-speech
 *
 * @author AHG Framework
 * @version 1.1.0
 */
(function() {
  'use strict';

  // Check for Web Speech API support
  if (!('speechSynthesis' in window)) {
    console.warn('AHG TTS: Web Speech API not supported in this browser');
    return;
  }

  var AhgTTS = {
    // State
    isPlaying: false,
    isPaused: false,
    currentUtterance: null,
    queue: [],
    currentIndex: 0,

    // Settings (can be overridden via data attributes or global config)
    settings: {
      enabled: true,
      rate: 1.0,        // 0.1 to 10
      pitch: 1.0,       // 0 to 2
      volume: 1.0,      // 0 to 1
      lang: document.documentElement.lang || 'en',
      voiceName: '',    // Specific voice to use
      highlightText: true,
      scrollIntoView: true,
      readLabels: true,
      keyboardShortcuts: true,
      fieldsToRead: ['title', 'scopeAndContent']
    },

    /**
     * Initialize TTS component
     */
    init: function() {
      var self = this;

      console.log('AHG TTS: Initializing...');

      // Load settings from global config if available (injected by PHP)
      if (window.AHG_TTS_CONFIG) {
        Object.assign(this.settings, window.AHG_TTS_CONFIG);
        console.log('AHG TTS: Loaded global config');
      }

      // Detect sector from page and load settings
      this.detectSectorAndLoadSettings();

      // If TTS is disabled, hide all TTS buttons and exit
      if (!this.settings.enabled) {
        this.hideTTSButtons();
        console.log('AHG TTS: Disabled by admin settings');
        return;
      }

      // Load voices - this can be async in some browsers
      this.voices = speechSynthesis.getVoices();

      if (speechSynthesis.onvoiceschanged !== undefined) {
        speechSynthesis.onvoiceschanged = function() {
          self.voices = speechSynthesis.getVoices();
          console.log('AHG TTS: Voices loaded:', self.voices.length);
        };
      }

      // Chrome bug workaround: speech synthesis can get stuck
      // This keeps it "warm" by periodically checking status
      setInterval(function() {
        if (self.isPlaying && !self.isPaused && speechSynthesis.paused) {
          console.log('AHG TTS: Resuming stuck synthesis');
          speechSynthesis.resume();
        }
      }, 1000);

      // Bind event handlers
      this.bindEvents();
      console.log('AHG TTS: Event handlers bound');

      // Add keyboard shortcuts if enabled
      if (this.settings.keyboardShortcuts) {
        this.bindKeyboardShortcuts();
        console.log('AHG TTS: Keyboard shortcuts bound');
      }

      console.log('AHG TTS: Initialized successfully, voices available:', this.voices.length);
    },

    /**
     * Detect current sector from page and load settings
     */
    detectSectorAndLoadSettings: function() {
      var self = this;

      // Try to detect sector from body class or data attribute
      var sector = 'archive'; // default
      var body = document.body;

      if (body.classList.contains('dam') || body.classList.contains('sfDamPlugin')) {
        sector = 'dam';
      } else if (body.classList.contains('library') || body.classList.contains('ahgLibraryPlugin')) {
        sector = 'library';
      } else if (body.classList.contains('museum') || body.classList.contains('ahgMuseumPlugin') || body.classList.contains('cco')) {
        sector = 'museum';
      } else if (body.classList.contains('gallery') || body.classList.contains('ahgGalleryPlugin')) {
        sector = 'gallery';
      }

      // Check for data attribute override
      var ttsContent = document.querySelector('[data-tts-content]');
      if (ttsContent && ttsContent.dataset.ttsSector) {
        sector = ttsContent.dataset.ttsSector;
      }

      this.currentSector = sector;
      console.log('AHG TTS: Detected sector:', sector);

      // Load settings from server
      fetch('/index.php/tts/settings?sector=' + sector)
        .then(function(response) { return response.json(); })
        .then(function(data) {
          if (data.fieldsToRead && data.fieldsToRead.length > 0) {
            self.settings.fieldsToRead = data.fieldsToRead;
          }
          self.settings.enabled = data.enabled;
          self.settings.rate = data.rate || 1.0;
          self.settings.readLabels = data.readLabels;
          console.log('AHG TTS: Settings loaded for sector', sector, '- fields:', self.settings.fieldsToRead);

          // Hide buttons if disabled
          if (!self.settings.enabled) {
            self.hideTTSButtons();
          }
        })
        .catch(function(err) {
          console.warn('AHG TTS: Could not load settings', err);
        });
    },

    /**
     * Hide all TTS buttons when disabled
     */
    hideTTSButtons: function() {
      document.querySelectorAll('[data-tts-action]').forEach(function(btn) {
        btn.style.display = 'none';
      });
    },

    /**
     * Bind click events to TTS buttons
     */
    bindEvents: function() {
      var self = this;

      // Play/Pause buttons
      document.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-tts-action]');
        if (!btn) return;

        console.log('AHG TTS: Button clicked, action:', btn.dataset.ttsAction);
        e.preventDefault();
        var action = btn.dataset.ttsAction;
        var target = btn.dataset.ttsTarget;

        switch (action) {
          case 'play':
            self.play(target, btn);
            break;
          case 'pause':
            self.pause();
            break;
          case 'resume':
            self.resume();
            break;
          case 'stop':
            self.stop();
            break;
          case 'toggle':
            self.toggle(target, btn);
            break;
          case 'read-pdf':
            var pdfId = btn.dataset.ttsPdfId;
            if (pdfId) {
              // If this PDF button is already playing, toggle pause/resume
              if (self.isPlaying && self.activeButton === btn) {
                if (self.isPaused) {
                  self.resume();
                } else {
                  self.pause();
                }
              } else {
                // Start reading this PDF
                self.readPdf(pdfId, btn);
              }
            }
            break;
        }
      });

      // Speed control
      document.addEventListener('change', function(e) {
        if (e.target.matches('[data-tts-speed]')) {
          self.setRate(parseFloat(e.target.value));
        }
      });
    },

    /**
     * Bind keyboard shortcuts
     */
    bindKeyboardShortcuts: function() {
      var self = this;

      document.addEventListener('keydown', function(e) {
        // Only when TTS is active or focused on TTS element
        if (!self.isPlaying && !e.target.closest('[data-tts-content]')) return;

        // Alt + P: Play/Pause toggle
        if (e.altKey && e.key === 'p') {
          e.preventDefault();
          if (self.isPlaying) {
            self.isPaused ? self.resume() : self.pause();
          }
        }

        // Alt + S: Stop
        if (e.altKey && e.key === 's') {
          e.preventDefault();
          self.stop();
        }

        // Alt + Arrow Up: Increase speed
        if (e.altKey && e.key === 'ArrowUp') {
          e.preventDefault();
          self.setRate(Math.min(2, self.settings.rate + 0.1));
        }

        // Alt + Arrow Down: Decrease speed
        if (e.altKey && e.key === 'ArrowDown') {
          e.preventDefault();
          self.setRate(Math.max(0.5, self.settings.rate - 0.1));
        }
      });
    },

    /**
     * Extract full text from an element, including content hidden by jQuery Expander
     * The expander plugin creates .summary (truncated visible) and .details (full hidden) elements
     */
    extractFullText: function(element) {
      if (!element) return '';

      // Check if this element has jQuery Expander applied
      var details = element.querySelector('.details, span.details, div.details');
      var summary = element.querySelector('.summary, span.summary, div.summary');

      if (details && summary) {
        // jQuery Expander is present - get the summary text (without "read more" link)
        // and the details text (the hidden part)
        var summaryText = '';
        var detailsText = '';

        // Get summary text, excluding the "read-more" link
        summary.childNodes.forEach(function(node) {
          if (node.nodeType === Node.TEXT_NODE) {
            summaryText += node.textContent;
          } else if (node.nodeType === Node.ELEMENT_NODE && !node.classList.contains('read-more')) {
            summaryText += node.textContent;
          }
        });

        // Get details text, excluding the "read-less" link
        details.childNodes.forEach(function(node) {
          if (node.nodeType === Node.TEXT_NODE) {
            detailsText += node.textContent;
          } else if (node.nodeType === Node.ELEMENT_NODE && !node.classList.contains('read-less')) {
            detailsText += node.textContent;
          }
        });

        // Combine summary and details (summary is the beginning, details is the rest)
        var fullText = (summaryText + detailsText).replace(/\s+/g, ' ').trim();
        console.log('AHG TTS: Extracted expanded content, length:', fullText.length);
        return fullText;
      }

      // No expander - just get the text content normally
      return element.textContent.trim();
    },

    /**
     * Get text content from target element, filtered by selected fields
     */
    getTextContent: function(target) {
      var element;

      console.log('AHG TTS: getTextContent called with target type:', typeof target);

      if (typeof target === 'string') {
        element = document.querySelector(target);
        console.log('AHG TTS: querySelector result for', target, ':', element ? 'found' : 'NOT FOUND');
      } else if (target instanceof Element) {
        element = target;
        console.log('AHG TTS: Using provided Element');
      }

      if (!element) {
        console.warn('AHG TTS: Target element not found for selector:', target);
        return '';
      }

      console.log('AHG TTS: Target element tag:', element.tagName, 'id:', element.id, 'classes:', element.className);

      var text = '';
      var self = this;
      var fieldsToRead = this.settings.fieldsToRead || [];
      var hasFieldFilter = fieldsToRead.length > 0;

      console.log('AHG TTS: Fields to read:', fieldsToRead);

      // Field name mappings (label text -> field key)
      var fieldMappings = {
        // Archive/ISAD
        'reference code': 'referenceCode',
        'title': 'title',
        'scope and content': 'scopeAndContent',
        'archival history': 'archivalHistory',
        'custodial history': 'archivalHistory',
        'immediate source of acquisition': 'acquisition',
        'system of arrangement': 'arrangement',
        'conditions governing access': 'accessConditions',
        'conditions governing reproduction': 'reproductionConditions',
        'physical characteristics': 'physicalCharacteristics',
        'finding aids': 'findingAids',
        'related units of description': 'relatedUnitsOfDescription',
        'notes': 'notes',
        'general note': 'notes',
        // Library
        'identifier': 'identifier',
        'subtitle': 'subtitle',
        'authors': 'creators',
        'creators': 'creators',
        'creator': 'creator',
        'publisher': 'publisher',
        'publication date': 'publicationDate',
        'isbn': 'isbn',
        'description': 'description',
        'subjects': 'subjects',
        'language': 'language',
        'edition': 'edition',
        'physical description': 'physicalDescription',
        // Museum/Gallery
        'object number': 'objectNumber',
        'accession number': 'objectNumber',
        'work type': 'workType',
        'classification': 'classification',
        'artist': 'artist',
        'creation date': 'creationDate',
        'date created': 'dateCreated',
        'materials': 'materials',
        'medium': 'medium',
        'techniques': 'techniques',
        'dimensions': 'dimensions',
        'provenance': 'provenance',
        'condition': 'condition',
        'inscriptions': 'inscriptions',
        'exhibition history': 'exhibition',
        // DAM
        'copyright': 'copyright',
        'keywords': 'keywords',
        'location': 'location',
        'format': 'format',
      };

      // Look for .field elements (AtoM's standard structure)
      var fields = element.querySelectorAll('.field, section .card, .card-body dl');

      if (fields.length > 0 || element.querySelectorAll('dt, .row').length > 0) {
        // Try to extract structured fields

        // Method 1: Look for .field with label/value structure
        var fieldsFound = element.querySelectorAll('.field');
        console.log('AHG TTS: Method 1 - Found', fieldsFound.length, '.field elements');

        fieldsFound.forEach(function(field, idx) {
          var label = field.querySelector('h3, .field-label, label, dt');
          // Look for value in col-9 (Bootstrap), .field-value, .value, p, dd, or direct div child
          var value = field.querySelector('.col-9, .field-value, .value, dd') ||
                      field.querySelector('p') ||
                      field.querySelector('div:not([class*="col-3"]):not(.field-label)');

          if (label && value) {
            var labelText = label.textContent.trim().replace(/:$/, '').toLowerCase();
            var fieldKey = fieldMappings[labelText] || labelText.replace(/\s+/g, '');
            // Use extractFullText to get content including expanded "read more" sections
            var valueText = self.extractFullText(value);

            console.log('AHG TTS: Field', idx, '- label:', labelText, 'key:', fieldKey, 'hasValue:', !!valueText, 'shouldRead:', (!hasFieldFilter || fieldsToRead.includes(fieldKey)));

            // Check if this field should be read
            if (valueText && (!hasFieldFilter || fieldsToRead.includes(fieldKey))) {
              if (self.settings.readLabels) {
                text += label.textContent.trim().replace(/:$/, '') + ': ' + valueText + '. ';
              } else {
                text += valueText + '. ';
              }
            }
          } else {
            console.log('AHG TTS: Field', idx, '- missing label or value. label:', !!label, 'value:', !!value);
          }
        });

        // Method 2: Look for dt/dd pairs
        element.querySelectorAll('dt').forEach(function(dt) {
          var dd = dt.nextElementSibling;
          if (dd && dd.tagName === 'DD') {
            var labelText = dt.textContent.trim().replace(/:$/, '').toLowerCase();
            var fieldKey = fieldMappings[labelText] || labelText.replace(/\s+/g, '');
            // Use extractFullText to get content including expanded "read more" sections
            var valueText = self.extractFullText(dd);

            if (valueText && (!hasFieldFilter || fieldsToRead.includes(fieldKey))) {
              if (self.settings.readLabels) {
                text += dt.textContent.trim().replace(/:$/, '') + ': ' + valueText + '. ';
              } else {
                text += valueText + '. ';
              }
            }
          }
        });

        // Method 3: Look for Bootstrap row with label/value columns
        element.querySelectorAll('.row').forEach(function(row) {
          var cols = row.querySelectorAll('[class*="col-"]');
          if (cols.length >= 2) {
            var labelText = cols[0].textContent.trim().replace(/:$/, '').toLowerCase();
            var fieldKey = fieldMappings[labelText] || labelText.replace(/\s+/g, '');
            // Use extractFullText to get content including expanded "read more" sections
            var valueText = self.extractFullText(cols[1]);

            if (valueText && labelText && (!hasFieldFilter || fieldsToRead.includes(fieldKey))) {
              if (self.settings.readLabels) {
                text += cols[0].textContent.trim().replace(/:$/, '') + ': ' + valueText + '. ';
              } else {
                text += valueText + '. ';
              }
            }
          }
        });
      }

      // If no structured content found, fall back to reading all text
      if (!text.trim()) {
        console.log('AHG TTS: No structured fields found, reading all text');

        // First, handle jQuery Expander elements - collect full text from expanded sections
        var expandedElements = element.querySelectorAll('.summary');
        if (expandedElements.length > 0) {
          // Page has expander content - extract full text from each expandable section
          expandedElements.forEach(function(summary) {
            var parent = summary.parentElement;
            var fullText = self.extractFullText(parent);
            if (fullText) {
              text += fullText + ' ';
            }
          });
        }

        // If still no text (no expander elements), use tree walker
        if (!text.trim()) {
          var walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT,
            {
              acceptNode: function(node) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                  // Skip jQuery Expander helper elements
                  if (node.classList && (node.classList.contains('read-more') || node.classList.contains('read-less'))) {
                    return NodeFilter.FILTER_REJECT;
                  }
                  // Include .details content even if hidden (this is the expanded content)
                  if (node.classList && node.classList.contains('details')) {
                    return NodeFilter.FILTER_ACCEPT;
                  }
                  var style = window.getComputedStyle(node);
                  if (style.display === 'none' || style.visibility === 'hidden') {
                    return NodeFilter.FILTER_REJECT;
                  }
                  if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'BUTTON', 'INPUT', 'SELECT'].includes(node.tagName)) {
                    return NodeFilter.FILTER_REJECT;
                  }
                  return NodeFilter.FILTER_SKIP;
                }
                return NodeFilter.FILTER_ACCEPT;
              }
            }
          );

          var node;
          while (node = walker.nextNode()) {
            var nodeText = node.textContent.trim();
            if (nodeText) {
              text += nodeText + ' ';
            }
          }
        }
      }

      return text.trim();
    },

    /**
     * Replace redacted content with spoken word "redacted" for accessibility
     */
    removeRedactions: function(text) {
      if (!text) return '';

      // Replace redaction patterns with spoken word "redacted" for accessibility
      // This allows blind users to know content was redacted

      // Patterns that should be replaced with "redacted"
      var redactionPatterns = [
        /\[NAME REDACTED\]/gi,
        /\[ID REDACTED\]/gi,
        /\[PASSPORT REDACTED\]/gi,
        /\[EMAIL REDACTED\]/gi,
        /\[PHONE REDACTED\]/gi,
        /\[ACCOUNT REDACTED\]/gi,
        /\[TAX NUMBER REDACTED\]/gi,
        /\[CARD REDACTED\]/gi,
        /\[ORG REDACTED\]/gi,
        /\[LOCATION REDACTED\]/gi,
        /\[DATE REDACTED\]/gi,
        /\[ADDRESS REDACTED\]/gi,
        /\[\w+\s+REDACTED\]/gi,         // Any [WORD REDACTED] pattern
        /\[REDACTED\]/gi,
        /\[REMOVED\]/gi,
        /\[PII REMOVED\]/gi,
        /\[PII REDACTED\]/gi,
        /█+/g,                          // Block characters
        /▓+/g,                          // Block characters
        /░+/g,                          // Block characters
        /\*{3,}/g,                       // Multiple asterisks (****)
        /X{3,}/g,                        // Multiple X's (XXXX)
        /\[\.{3,}\]/g,                   // [...]
        /<redacted>.*?<\/redacted>/gi,   // XML-style redaction tags
      ];

      redactionPatterns.forEach(function(pattern) {
        text = text.replace(pattern, ' redacted ');
      });

      // Clean up multiple spaces and multiple "redacted" words in a row
      text = text.replace(/(\s*redacted\s*)+/g, ' redacted ');
      text = text.replace(/\s+/g, ' ').trim();

      return text;
    },

    /**
     * Split text into chunks for better handling
     */
    splitIntoChunks: function(text, maxLength) {
      // First remove redactions
      text = this.removeRedactions(text);

      maxLength = maxLength || 200;
      var chunks = [];
      var sentences = text.match(/[^.!?]+[.!?]+/g) || [text];

      var currentChunk = '';
      sentences.forEach(function(sentence) {
        if ((currentChunk + sentence).length > maxLength && currentChunk) {
          chunks.push(currentChunk.trim());
          currentChunk = sentence;
        } else {
          currentChunk += sentence;
        }
      });

      if (currentChunk.trim()) {
        chunks.push(currentChunk.trim());
      }

      return chunks;
    },

    /**
     * Get appropriate voice for language
     */
    getVoice: function(lang) {
      var voices = this.voices || speechSynthesis.getVoices();

      // If specific voice is set, use it
      if (this.settings.voiceName) {
        var specificVoice = voices.find(function(v) {
          return v.name === this.settings.voiceName;
        }.bind(this));
        if (specificVoice) return specificVoice;
      }

      // Find voice matching language
      var langCode = lang.split('-')[0];
      var matchingVoice = voices.find(function(v) {
        return v.lang.startsWith(langCode);
      });

      return matchingVoice || voices[0];
    },

    /**
     * Play text from target element
     */
    play: function(target, triggerBtn) {
      var self = this;

      console.log('AHG TTS: play() called with target:', target);

      // Stop any current playback
      this.stop();

      // Ensure voices are loaded
      this.voices = speechSynthesis.getVoices();
      console.log('AHG TTS: Available voices:', this.voices ? this.voices.length : 0);

      if (!this.voices || this.voices.length === 0) {
        console.warn('AHG TTS: No voices available, waiting for voices to load...');
        // Try again after a short delay
        setTimeout(function() {
          self.voices = speechSynthesis.getVoices();
          if (self.voices && self.voices.length > 0) {
            self.play(target, triggerBtn);
          } else {
            alert('Text-to-Speech is not available. Please check your browser settings or try a different browser.');
          }
        }, 100);
        return;
      }

      // Get text content
      var targetSelector = target || '[data-tts-content]';
      console.log('AHG TTS: Getting content from:', targetSelector);
      var text = this.getTextContent(targetSelector);
      console.log('AHG TTS: Extracted text length:', text ? text.length : 0);
      if (text) {
        console.log('AHG TTS: First 200 chars:', text.substring(0, 200));
      }

      if (!text) {
        console.warn('AHG TTS: No text content to read');
        alert('No text content found to read aloud.');
        return;
      }

      console.log('AHG TTS: Starting playback, text length:', text.length);

      // Split into manageable chunks
      this.queue = this.splitIntoChunks(text);
      this.currentIndex = 0;

      // Update button state
      if (triggerBtn) {
        this.activeButton = triggerBtn;
        triggerBtn.classList.add('tts-playing');
        this.updateButtonState(triggerBtn, 'playing');
      }

      // Start speaking
      this.isPlaying = true;
      this.speakNextChunk();

      // Dispatch event
      document.dispatchEvent(new CustomEvent('tts:start', {
        detail: { text: text, chunks: this.queue.length }
      }));
    },

    /**
     * Speak next chunk in queue
     */
    speakNextChunk: function() {
      var self = this;

      if (this.currentIndex >= this.queue.length) {
        this.stop();
        return;
      }

      var text = this.queue[this.currentIndex];
      console.log('AHG TTS: Speaking chunk', this.currentIndex + 1, 'of', this.queue.length, ':', text.substring(0, 50) + '...');

      var utterance = new SpeechSynthesisUtterance(text);

      // Apply settings
      utterance.rate = this.settings.rate;
      utterance.pitch = this.settings.pitch;
      utterance.volume = this.settings.volume;
      utterance.lang = this.settings.lang;

      // Set voice - use the first available voice if none specified
      var voice = this.getVoice(this.settings.lang);
      if (voice) {
        utterance.voice = voice;
        console.log('AHG TTS: Using voice:', voice.name, voice.lang);
      } else {
        console.log('AHG TTS: No specific voice found, using default');
      }

      // Event handlers
      utterance.onstart = function() {
        console.log('AHG TTS: Speech started');
      };

      utterance.onend = function() {
        console.log('AHG TTS: Chunk finished');
        self.currentIndex++;
        self.speakNextChunk();
      };

      utterance.onerror = function(e) {
        console.error('AHG TTS: Speech error', e.error, e);
        if (e.error === 'not-allowed') {
          alert('Speech synthesis was blocked. Please click the page first to enable audio.');
        }
        self.stop();
      };

      utterance.onpause = function() {
        self.isPaused = true;
      };

      utterance.onresume = function() {
        self.isPaused = false;
      };

      this.currentUtterance = utterance;

      // Cancel any pending speech and speak
      speechSynthesis.cancel();
      speechSynthesis.speak(utterance);
    },

    /**
     * Toggle play/pause
     */
    toggle: function(target, triggerBtn) {
      console.log('AHG TTS: toggle() called, isPlaying:', this.isPlaying, 'isPaused:', this.isPaused);
      if (this.isPlaying) {
        if (this.isPaused) {
          this.resume();
        } else {
          this.pause();
        }
      } else {
        this.play(target, triggerBtn);
      }
    },

    /**
     * Pause playback
     */
    pause: function() {
      if (this.isPlaying && !this.isPaused) {
        speechSynthesis.pause();
        this.isPaused = true;

        if (this.activeButton) {
          this.updateButtonState(this.activeButton, 'paused');
        }

        document.dispatchEvent(new CustomEvent('tts:pause'));
      }
    },

    /**
     * Resume playback
     */
    resume: function() {
      if (this.isPlaying && this.isPaused) {
        speechSynthesis.resume();
        this.isPaused = false;

        if (this.activeButton) {
          this.updateButtonState(this.activeButton, 'playing');
        }

        document.dispatchEvent(new CustomEvent('tts:resume'));
      }
    },

    /**
     * Stop playback
     */
    stop: function() {
      speechSynthesis.cancel();
      this.isPlaying = false;
      this.isPaused = false;
      this.queue = [];
      this.currentIndex = 0;
      this.currentUtterance = null;

      if (this.activeButton) {
        this.activeButton.classList.remove('tts-playing');
        this.updateButtonState(this.activeButton, 'stopped');
        this.activeButton = null;
      }

      document.dispatchEvent(new CustomEvent('tts:stop'));
    },

    /**
     * Set speech rate
     */
    setRate: function(rate) {
      this.settings.rate = Math.max(0.1, Math.min(10, rate));

      // Update any rate displays
      document.querySelectorAll('[data-tts-rate-display]').forEach(function(el) {
        el.textContent = this.settings.rate.toFixed(1) + 'x';
      }.bind(this));

      document.dispatchEvent(new CustomEvent('tts:ratechange', {
        detail: { rate: this.settings.rate }
      }));
    },

    /**
     * Update button state and icon
     */
    updateButtonState: function(btn, state) {
      var icon = btn.querySelector('i, .fa, .fas, .far, .bi');
      // Check if this is a PDF button - use getAttribute for reliability
      var ttsAction = btn.getAttribute('data-tts-action') || btn.dataset.ttsAction;
      var isPdfButton = ttsAction === 'read-pdf';

      console.log('AHG TTS: updateButtonState - state:', state, 'isPdfButton:', isPdfButton, 'ttsAction:', ttsAction);

      if (icon) {
        // Store original icon class if not already stored
        if (!btn.dataset.originalIcon) {
          if (icon.classList.contains('fa-file-pdf')) {
            btn.dataset.originalIcon = 'fa-file-pdf';
          } else if (icon.classList.contains('fa-volume-up')) {
            btn.dataset.originalIcon = 'fa-volume-up';
          }
        }

        // Remove existing state classes
        icon.classList.remove('fa-play', 'fa-pause', 'fa-stop', 'fa-volume-up', 'fa-file-pdf',
                              'bi-play-fill', 'bi-pause-fill', 'bi-stop-fill', 'fa-spin');

        // Add appropriate class based on state
        if (state === 'playing') {
          icon.classList.add('fa-pause');
        } else if (state === 'paused') {
          icon.classList.add('fa-play');
        } else {
          // Stopped - restore original icon
          var originalIcon = btn.dataset.originalIcon;
          if (originalIcon) {
            icon.classList.add(originalIcon);
          } else if (isPdfButton) {
            icon.classList.add('fa-file-pdf');
          } else {
            icon.classList.add('fa-volume-up');
          }
        }
      }
    },

    /**
     * Get available voices
     */
    getAvailableVoices: function() {
      return speechSynthesis.getVoices();
    },

    /**
     * Check if TTS is supported
     */
    isSupported: function() {
      return 'speechSynthesis' in window;
    },

    /**
     * Read PDF content
     * @param {number} digitalObjectId - The digital object ID
     * @param {HTMLElement} triggerBtn - The button that triggered the action
     */
    readPdf: function(digitalObjectId, triggerBtn) {
      var self = this;

      // Show loading state
      if (triggerBtn) {
        triggerBtn.disabled = true;
        var originalHtml = triggerBtn.innerHTML;
        triggerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      }

      // Fetch PDF text from server
      fetch('/index.php/tts/pdfText?id=' + digitalObjectId)
        .then(function(response) {
          return response.json();
        })
        .then(function(data) {
          if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = originalHtml;
          }

          if (data.success && data.text) {
            console.log('AHG TTS: PDF text extracted, chars:', data.chars, 'pages:', data.pages, 'redacted:', data.redacted);
            if (data.redacted) {
              console.log('AHG TTS: Using redacted PDF (sensitive content removed)');
            }
            self.playText(data.text, triggerBtn);
          } else {
            alert('Could not extract text from PDF: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(function(error) {
          console.error('AHG TTS: PDF fetch error', error);
          if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = originalHtml;
          }
          alert('Error fetching PDF text: ' + error.message);
        });
    },

    /**
     * Play arbitrary text (not from DOM element)
     */
    playText: function(text, triggerBtn) {
      var self = this;

      // Stop any current playback
      this.stop();

      // Ensure voices are loaded
      this.voices = speechSynthesis.getVoices();
      if (!this.voices || this.voices.length === 0) {
        setTimeout(function() {
          self.voices = speechSynthesis.getVoices();
          if (self.voices && self.voices.length > 0) {
            self.playText(text, triggerBtn);
          } else {
            alert('Text-to-Speech is not available.');
          }
        }, 100);
        return;
      }

      if (!text || text.trim().length === 0) {
        alert('No text content to read.');
        return;
      }

      console.log('AHG TTS: Starting playback, text length:', text.length);

      // Split into manageable chunks
      this.queue = this.splitIntoChunks(text);
      this.currentIndex = 0;

      // Update button state
      if (triggerBtn) {
        this.activeButton = triggerBtn;
        triggerBtn.classList.add('tts-playing');
        this.updateButtonState(triggerBtn, 'playing');
      }

      // Start speaking
      this.isPlaying = true;
      this.speakNextChunk();

      // Dispatch event
      document.dispatchEvent(new CustomEvent('tts:start', {
        detail: { text: text, chunks: this.queue.length, source: 'pdf' }
      }));
    }
  };

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      AhgTTS.init();
    });
  } else {
    AhgTTS.init();
  }

  // Expose globally
  window.AhgTTS = AhgTTS;

})();
