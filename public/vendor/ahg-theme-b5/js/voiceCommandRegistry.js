/**
 * AHG Voice Command Registry — Navigation + Action Commands
 *
 * Command definitions for voice-driven navigation and context-aware actions.
 * Each command has: patterns, action, mode, description, contextCheck (optional).
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
var AHGVoiceRegistry = (function () {
  'use strict';

  var commands = [
    // -- Navigation -------------------------------------------------------
    {
      patterns: ['go home', 'go to home', 'home', 'homepage'],
      action: function () { window.location.href = '/'; },
      mode: 'nav',
      description: 'Go to homepage',
      feedback: 'Going to homepage'
    },
    {
      patterns: [/^(?:go to )?browse(?: records)?$/, 'browse'],
      action: function () { window.location.href = '/index.php/glam/browse'; },
      mode: 'nav',
      description: 'Browse archival records',
      feedback: 'Opening archival records'
    },
    {
      patterns: ['go to admin', 'admin', 'admin panel'],
      action: function () { window.location.href = '/admin'; },
      mode: 'nav',
      description: 'Go to admin panel',
      feedback: 'Going to admin panel'
    },
    {
      patterns: ['go to settings', 'settings', 'ahg settings'],
      action: function () { window.location.href = '/ahgSettings'; },
      mode: 'nav',
      description: 'Go to settings',
      feedback: 'Opening settings'
    },
    {
      patterns: ['go to clipboard', 'clipboard', 'open clipboard'],
      action: function () { window.location.href = '/clipboard'; },
      mode: 'nav',
      description: 'Go to clipboard',
      feedback: 'Opening clipboard'
    },
    {
      patterns: ['go back', 'back', 'previous page'],
      action: function () { window.history.back(); },
      mode: 'nav',
      description: 'Go back',
      feedback: 'Going back'
    },
    {
      patterns: ['next page', 'go to next page'],
      action: function () {
        var link = document.querySelector('.pager .next a, .pagination .page-item:last-child a');
        if (link) { link.click(); }
        else { window.ahgVoice && window.ahgVoice.speak('No next page available'); }
      },
      mode: 'nav',
      description: 'Next page',
      feedback: 'Going to next page'
    },
    {
      patterns: ['previous page', 'go to previous page', 'prev page'],
      action: function () {
        var link = document.querySelector('.pager .previous a, .pagination .page-item:first-child a');
        if (link) { link.click(); }
        else { window.ahgVoice && window.ahgVoice.speak('No previous page available'); }
      },
      mode: 'nav',
      description: 'Previous page',
      feedback: 'Going to previous page'
    },
    {
      patterns: [/^search (?:for )?(.+)$/],
      action: function (text) {
        var match = text.match(/^search (?:for )?(.+)$/);
        if (!match) return;
        var term = match[1];
        window.ahgVoice && window.ahgVoice.speak('Searching for ' + term);
        var input = document.querySelector('#search-form-wrapper input[type="text"], #search-form-wrapper input[name="query"], input[name="query"]');
        var form = document.querySelector('#search-form-wrapper form, form[action*="search"]');
        if (input && form) {
          input.value = term;
          setTimeout(function() { form.submit(); }, 800);
        } else {
          setTimeout(function() { window.location.href = '/index.php/glam/browse?query=' + encodeURIComponent(term); }, 800);
        }
      },
      mode: 'nav',
      description: 'Search for a term',
      feedback: null // handled in action
    },
    {
      patterns: ['go to donors', 'donors', 'browse donors'],
      action: function () { window.location.href = '/donor/browse'; },
      mode: 'nav',
      description: 'Browse donors',
      feedback: 'Browsing donors'
    },
    {
      patterns: ['go to research', 'research', 'reading room'],
      action: function () { window.location.href = '/research'; },
      mode: 'nav',
      description: 'Go to research/reading room',
      feedback: 'Opening reading room'
    },
    {
      patterns: ['go to authorities', 'authorities', 'browse authorities', 'authority records'],
      action: function () { window.location.href = '/actor/browse'; },
      mode: 'nav',
      description: 'Browse authority records',
      feedback: 'Browsing authority records'
    },
    {
      patterns: ['go to places', 'places', 'browse places'],
      action: function () { window.location.href = '/taxonomy/browse?taxonomy=places'; },
      mode: 'nav',
      description: 'Browse places',
      feedback: 'Browsing places'
    },
    {
      patterns: ['go to subjects', 'subjects', 'browse subjects'],
      action: function () { window.location.href = '/taxonomy/browse?taxonomy=subjects'; },
      mode: 'nav',
      description: 'Browse subjects',
      feedback: 'Browsing subjects'
    },
    {
      patterns: ['go to digital objects', 'digital objects', 'browse digital objects'],
      action: function () { window.location.href = '/index.php/display/browse?hasDigital=1&topLevel=0&view=grid'; },
      mode: 'nav',
      description: 'Browse digital objects',
      feedback: 'Browsing digital objects'
    },

    // -- Sector-specific Browse -----------------------------------------------
    {
      patterns: ['browse archive', 'browse archives', 'go to archives'],
      action: function () { window.location.href = '/index.php/display/browse?type=archive'; },
      mode: 'nav',
      description: 'Browse archive records',
      feedback: 'Browsing archive records'
    },
    {
      patterns: ['browse library', 'go to library', 'library records'],
      action: function () { window.location.href = '/index.php/display/browse?type=library'; },
      mode: 'nav',
      description: 'Browse library records',
      feedback: 'Browsing library records'
    },
    {
      patterns: ['browse museum', 'go to museum', 'museum records'],
      action: function () { window.location.href = '/index.php/display/browse?type=museum'; },
      mode: 'nav',
      description: 'Browse museum records',
      feedback: 'Browsing museum records'
    },
    {
      patterns: ['browse gallery', 'go to gallery', 'gallery records'],
      action: function () { window.location.href = '/index.php/display/browse?type=gallery'; },
      mode: 'nav',
      description: 'Browse gallery records',
      feedback: 'Browsing gallery records'
    },
    {
      patterns: ['browse dam', 'browse photos', 'go to dam', 'photo dam'],
      action: function () { window.location.href = '/index.php/display/browse?type=dam'; },
      mode: 'nav',
      description: 'Browse DAM/photo records',
      feedback: 'Browsing photo and D A M records'
    },

    {
      patterns: ['go to accessions', 'accessions', 'browse accessions'],
      action: function () { window.location.href = '/accession/browse'; },
      mode: 'nav',
      description: 'Browse accessions',
      feedback: 'Browsing accessions'
    },
    {
      patterns: ['go to repositories', 'repositories', 'institutions', 'browse repositories'],
      action: function () { window.location.href = '/repository/browse'; },
      mode: 'nav',
      description: 'Browse repositories',
      feedback: 'Browsing repositories'
    },

    // -- Actions: Edit screens --------------------------------------------
    {
      patterns: ['save', 'save record', 'save this'],
      action: function () {
        var v = window.ahgVoice;
        var btn = document.querySelector('form#editForm .btn-success[type="submit"], form.form-edit .btn-success[type="submit"], form#editForm button[type="submit"], form.form-edit button[type="submit"]');
        if (btn && v) {
          v.highlightElement(btn);
          setTimeout(function () { btn.click(); }, 150);
        } else if (v) {
          v.speak('No save button found');
        }
      },
      mode: 'action_edit',
      description: 'Save the current record',
      feedback: 'Saving record',
      contextCheck: function () { return !!document.querySelector('form#editForm, form.form-edit'); }
    },
    {
      patterns: ['cancel', 'cancel edit'],
      action: function () {
        var v = window.ahgVoice;
        var btn = document.querySelector('form#editForm a.btn-secondary, form.form-edit a.btn-secondary, a.btn[href*="cancel"], .actions a.btn-secondary');
        if (btn && v) {
          v.highlightElement(btn);
          setTimeout(function () { btn.click(); }, 150);
        } else if (v) {
          v.speak('No cancel button found');
        }
      },
      mode: 'action_edit',
      description: 'Cancel editing',
      feedback: 'Cancelling',
      contextCheck: function () { return !!document.querySelector('form#editForm, form.form-edit'); }
    },
    {
      patterns: ['delete', 'delete record', 'delete this'],
      action: function () {
        var v = window.ahgVoice;
        var btn = document.querySelector('a.btn-danger[href*="delete"], button.btn-danger, a.btn-danger, input[value="Delete"]');
        if (btn && v) {
          v.highlightElement(btn);
          setTimeout(function () { btn.click(); }, 150);
        } else if (v) {
          v.speak('No delete button found');
        }
      },
      mode: 'action_edit',
      description: 'Delete the current record',
      feedback: 'Deleting record',
      contextCheck: function () { return !!document.querySelector('form#editForm, form.form-edit'); }
    },

    // -- Actions: View screens --------------------------------------------
    {
      patterns: ['edit', 'edit record', 'edit this'],
      action: function () {
        var v = window.ahgVoice;
        var btn = document.querySelector('a[href*="/edit"], a.btn[href*="edit"], .actions a[href*="edit"]');
        if (btn && v) {
          v.highlightElement(btn);
          setTimeout(function () { btn.click(); }, 150);
        } else if (v) {
          v.speak('No edit button found');
        }
      },
      mode: 'action_view',
      description: 'Edit the current record',
      feedback: 'Opening editor',
      contextCheck: function () { return !document.querySelector('form#editForm, form.form-edit'); }
    },
    {
      patterns: ['print', 'print page', 'print this'],
      action: function () { window.print(); },
      mode: 'action_view',
      description: 'Print the current page',
      feedback: 'Opening print dialog'
    },
    {
      patterns: ['export csv', 'export to csv', 'download csv'],
      action: function () {
        var v = window.ahgVoice;
        var link = document.querySelector('a[href*="csv"], a[href*="CSV"]');
        if (link && v) {
          v.highlightElement(link);
          setTimeout(function () { link.click(); }, 150);
        } else if (v) {
          v.speak('No CSV export link found');
        }
      },
      mode: 'action_view',
      description: 'Export as CSV',
      feedback: 'Exporting as CSV',
      contextCheck: function () { return !!document.querySelector('a[href*="csv"], a[href*="CSV"]'); }
    },
    {
      patterns: ['export ead', 'export to ead', 'download ead'],
      action: function () {
        var v = window.ahgVoice;
        var link = document.querySelector('a[href*="ead"], a[href*="EAD"]');
        if (link && v) {
          v.highlightElement(link);
          setTimeout(function () { link.click(); }, 150);
        } else if (v) {
          v.speak('No EAD export link found');
        }
      },
      mode: 'action_view',
      description: 'Export as EAD',
      feedback: 'Exporting as EAD',
      contextCheck: function () { return !!document.querySelector('a[href*="ead"], a[href*="EAD"]'); }
    },

    // -- Actions: Browse screens ------------------------------------------
    {
      patterns: ['first result', 'open first', 'click first'],
      action: function () {
        var v = window.ahgVoice;
        var link = document.querySelector('.search-results article a, .result-count ~ * a, #content .search-result a, td a[href], #content .col-md-2 a[href*="/index.php/"], #content a.text-success.text-decoration-none');
        if (link && v) {
          v.highlightElement(link);
          setTimeout(function () { link.click(); }, 150);
        } else if (v) {
          v.speak('No results found');
        }
      },
      mode: 'action_browse',
      description: 'Open the first result',
      feedback: 'Opening first result',
      contextCheck: function () { return !!document.querySelector('.result-count, .pager, .pagination, .browse-results, .card-title a.text-success'); }
    },
    {
      patterns: ['sort by title', 'sort title'],
      action: function () {
        var v = window.ahgVoice;
        var opt = document.querySelector('select[name="sort"] option[value="alphabetic"], select[name="sort"] option[value="title"]');
        if (opt) {
          opt.parentElement.value = opt.value;
          if (v) v.highlightElement(opt.parentElement);
          var evt = new Event('change', { bubbles: true });
          opt.parentElement.dispatchEvent(evt);
          var form = opt.parentElement.closest('form');
          if (form) setTimeout(function () { form.submit(); }, 200);
        } else if (v) {
          v.speak('No sort option found');
        }
      },
      mode: 'action_browse',
      description: 'Sort results by title',
      feedback: 'Sorting by title',
      contextCheck: function () { return !!document.querySelector('select[name="sort"]'); }
    },
    {
      patterns: ['sort by date', 'sort date'],
      action: function () {
        var v = window.ahgVoice;
        var opt = document.querySelector('select[name="sort"] option[value="date"], select[name="sort"] option[value="startDate"]');
        if (opt) {
          opt.parentElement.value = opt.value;
          if (v) v.highlightElement(opt.parentElement);
          var evt = new Event('change', { bubbles: true });
          opt.parentElement.dispatchEvent(evt);
          var form = opt.parentElement.closest('form');
          if (form) setTimeout(function () { form.submit(); }, 200);
        } else if (v) {
          v.speak('No date sort option found');
        }
      },
      mode: 'action_browse',
      description: 'Sort results by date',
      feedback: 'Sorting by date',
      contextCheck: function () { return !!document.querySelector('select[name="sort"]'); }
    },

    // -- Global actions ---------------------------------------------------
    {
      patterns: ['toggle advanced search', 'advanced search', 'show advanced search'],
      action: function () {
        var v = window.ahgVoice;
        var toggle = document.querySelector('#toggle-advanced-search, a[href*="advanced"], .advanced-search-toggle, button[data-bs-target*="advanced"]');
        if (toggle && v) {
          v.highlightElement(toggle);
          setTimeout(function () { toggle.click(); }, 150);
        } else if (v) {
          v.speak('No advanced search toggle found');
        }
      },
      mode: 'global',
      description: 'Toggle advanced search',
      feedback: 'Toggling advanced search'
    },
    {
      patterns: ['clear search', 'clear the search'],
      action: function () {
        var input = document.querySelector('#search-form-wrapper input[type="text"], input[name="query"]');
        var form = document.querySelector('#search-form-wrapper form, form[action*="search"]');
        if (input && form) {
          input.value = '';
          form.submit();
        }
      },
      mode: 'global',
      description: 'Clear search and reload',
      feedback: 'Clearing search'
    },
    {
      patterns: ['scroll down', 'page down'],
      action: function () { window.scrollBy({ top: 500, behavior: 'smooth' }); },
      mode: 'global',
      description: 'Scroll down',
      feedback: null // no speech for scroll — too frequent
    },
    {
      patterns: ['scroll up', 'page up'],
      action: function () { window.scrollBy({ top: -500, behavior: 'smooth' }); },
      mode: 'global',
      description: 'Scroll up',
      feedback: null
    },
    {
      patterns: ['scroll to top', 'go to top', 'top of page'],
      action: function () { window.scrollTo({ top: 0, behavior: 'smooth' }); },
      mode: 'global',
      description: 'Scroll to top',
      feedback: 'Scrolling to top'
    },
    {
      patterns: ['scroll to bottom', 'go to bottom', 'bottom of page'],
      action: function () { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); },
      mode: 'global',
      description: 'Scroll to bottom',
      feedback: 'Scrolling to bottom'
    },

    // -- Metadata Reading (Phase 4) --------------------------------------
    {
      patterns: ['read metadata', 'read all fields', 'read image info', 'what is this image', 'image details', 'read record', 'read all'],
      action: function () { var v = window.ahgVoice; if (v) v.readImageMetadata(); },
      mode: 'action_view',
      description: 'Read all populated fields aloud',
      feedback: null, // action speaks the metadata directly
      contextCheck: function () {
        return AHGVoiceRegistry._hasDigitalObject();
      }
    },
    {
      patterns: ['read title', 'what is the title'],
      action: function () { var v = window.ahgVoice; if (v) v.readTitle(); },
      mode: 'action_view',
      description: 'Read the record title aloud',
      feedback: null // action speaks the title directly
    },
    {
      patterns: ['read description', 'read scope and content', 'read the description'],
      action: function () { var v = window.ahgVoice; if (v) v.readDescription(); },
      mode: 'action_view',
      description: 'Read the description aloud',
      feedback: null // action speaks the description directly
    },
    {
      patterns: ['describe object', 'describe the object', 'what is this', 'what is this object', 'what do i see', 'what is in the image', 'what is in the picture'],
      action: function () {
        var v = window.ahgVoice;
        if (!v) return;
        // Auto-detect: if a 3D viewer is on the page, use 3D describe; otherwise image describe
        if (document.querySelector('[id^="viewer-3d-"], model-viewer, .three-js-viewer, .gaussian-splat-viewer')) {
          v.describeObject();
        } else {
          v.describeImage();
        }
      },
      mode: 'action_view',
      description: 'AI describe what is in the image or 3D object',
      feedback: null,
      contextCheck: function () {
        return AHGVoiceRegistry._hasDigitalObject();
      }
    },
    {
      patterns: ['what type of file', 'what is the file type', 'file type', 'what type of object', 'what format'],
      action: function () {
        var v = window.ahgVoice;
        if (!v) return;
        var meta = v._gatherPageMetadata();
        var mimeType = meta.mimeType || '';
        var mediaType = meta.mediaType || '';

        // Try DOM metadata fields
        if (!mimeType) {
          var mimeFields = document.querySelectorAll('.digital-object-metadata .field.row, .digital-object-metadata-body .field');
          for (var i = 0; i < mimeFields.length; i++) {
            var label = mimeFields[i].querySelector('h3, .col-3');
            var value = mimeFields[i].querySelector('.col-9, p');
            if (!label || !value) continue;
            var lt = label.textContent.trim().toLowerCase();
            var vt = value.textContent.trim();
            if (/mime[\s-]*type/i.test(lt) && !mimeType) mimeType = vt;
            if (/media\s*type/i.test(lt) && !mediaType) mediaType = vt;
          }
        }

        // Detect from elements
        if (!mimeType) {
          if (document.querySelector('[data-tts-action="read-pdf"], .pdf-viewer-container')) { mimeType = 'application/pdf'; mediaType = 'Text'; }
          else if (document.querySelector('video')) { mimeType = 'video'; mediaType = 'Video'; }
          else if (document.querySelector('audio')) { mimeType = 'audio'; mediaType = 'Audio'; }
          else if (document.querySelector('.iiif-viewer-container, [id^="container-iiif-viewer"]')) { mimeType = 'image'; mediaType = 'Image'; }
          else if (document.querySelector('#wrapper img.img-fluid, #sidebar img.img-fluid, #content img.img-fluid')) { mimeType = 'image'; mediaType = 'Image'; }
        }

        if (!mimeType && !mediaType) { v.speak('No digital object found on this page'); return; }

        var map = {
          'application/pdf': 'a PDF document', 'image/jpeg': 'a JPEG image', 'image/jpg': 'a JPEG image',
          'image/png': 'a PNG image', 'image/tiff': 'a TIFF image', 'image/gif': 'a GIF image',
          'video/mp4': 'an MP4 video', 'video/webm': 'a WebM video',
          'audio/mpeg': 'an MP3 audio file', 'audio/wav': 'a WAV audio file'
        };
        var friendly = map[mimeType.toLowerCase()] || (mediaType ? 'a ' + mediaType.toLowerCase() + ' file' : 'a ' + mimeType + ' file');
        v.speak('This is ' + friendly);
      },
      mode: 'action_view',
      description: 'Report the file type in plain English',
      feedback: null
    },
    {
      patterns: ['read text', 'read the text', 'read file', 'read the file', 'read text file', 'read it'],
      action: function () { var v = window.ahgVoice; if (v) v.readTextFile(); },
      mode: 'action_view',
      description: 'Read plain text file content aloud',
      feedback: 'Reading text file',
      contextCheck: function () {
        return !!document.querySelector('[id^="text-content-"], [id^="archive-content-"]');
      }
    },
    {
      patterns: ['read pdf', 'read the pdf', 'read document', 'read the document'],
      action: function () {
        var pdfBtn = document.querySelector('[data-tts-action="read-pdf"]');
        if (pdfBtn) {
          pdfBtn.click();
        } else {
          // Fall back to reading text file if available
          var v = window.ahgVoice;
          if (v && document.querySelector('[id^="text-content-"], [id^="archive-content-"]')) {
            v.readTextFile();
          } else if (v) {
            v.showToast('No PDF or text file found on this page', 'warning');
          }
        }
      },
      mode: 'action_view',
      description: 'Read PDF content aloud',
      feedback: 'Reading PDF',
      contextCheck: function () {
        return !!document.querySelector('[data-tts-action="read-pdf"], [id^="text-content-"], [id^="archive-content-"]');
      }
    },
    {
      patterns: ['stop reading', 'stop speaking', 'shut up', 'be quiet', 'silence'],
      action: function () {
        var v = window.ahgVoice;
        if (v) v.stopSpeaking();
        // Also stop the AhgTTS component (PDF reader, metadata reader)
        if (window.AhgTTS && window.AhgTTS.isPlaying) window.AhgTTS.stop();
      },
      mode: 'global',
      description: 'Stop speech output',
      feedback: null // can't speak while stopping speech
    },
    {
      patterns: ['slower', 'speak slower', 'slow down'],
      action: function () { var v = window.ahgVoice; if (v) v.adjustSpeechRate(-0.2); },
      mode: 'global',
      description: 'Decrease speech rate',
      feedback: 'Slowing down'
    },
    {
      patterns: ['faster', 'speak faster', 'speed up'],
      action: function () { var v = window.ahgVoice; if (v) v.adjustSpeechRate(0.2); },
      mode: 'global',
      description: 'Increase speech rate',
      feedback: 'Speeding up'
    },

    // -- AI 3D Object Description -----------------------------------------
    {
      patterns: ['describe 3d', 'describe 3d object', 'describe model', 'describe 3d model', 'what is this model'],
      action: function () { var v = window.ahgVoice; if (v) v.describeObject(); },
      mode: 'action_view',
      description: 'AI-describe a 3D object (reads cached if available)',
      feedback: null,
      contextCheck: function () {
        return !!document.querySelector('[id^="viewer-3d-"], model-viewer, .three-js-viewer, .gaussian-splat-viewer');
      }
    },
    {
      patterns: ['redescribe object', 'redescribe 3d', 'redescribe model', 'redescribe', 'describe again', 'new description'],
      action: function () { var v = window.ahgVoice; if (v) v.redescribeObject(); },
      mode: 'action_view',
      description: 'Force re-describe 3D object with fresh AI analysis',
      feedback: 'Redescribing object',
      contextCheck: function () {
        return !!document.querySelector('[id^="viewer-3d-"], model-viewer, .three-js-viewer, .gaussian-splat-viewer');
      }
    },

    // -- AI Image Description (Phase 5) ----------------------------------
    {
      patterns: ['describe image', 'ai describe', 'what do you see', 'generate description', 'generate alt text'],
      action: function () { var v = window.ahgVoice; if (v) v.describeImage(); },
      mode: 'action_view',
      description: 'AI-generate image description',
      feedback: null, // action speaks its own feedback
      contextCheck: function () {
        return AHGVoiceRegistry._hasDigitalObject();
      }
    },
    {
      patterns: ['yes', 'save', 'save it'],
      action: function () { var v = window.ahgVoice; if (v && v._awaitingSaveCommand) v.saveDescription('auto'); },
      mode: 'action_view',
      description: 'Save AI description (auto-detect: extent and medium if empty, otherwise description)',
      feedback: 'Saving description',
      contextCheck: function () { var v = window.ahgVoice; return v && v._awaitingSaveCommand; }
    },
    {
      patterns: ['save to description', 'save description'],
      action: function () { var v = window.ahgVoice; if (v) v.saveDescription('description'); },
      mode: 'action_view',
      description: 'Save AI description to record',
      feedback: 'Saving to description'
    },
    {
      patterns: ['save to extent and medium', 'save to extent and media', 'save to extent'],
      action: function () { var v = window.ahgVoice; if (v) v.saveDescription('extent_and_medium'); },
      mode: 'action_view',
      description: 'Save AI description to extent and medium field',
      feedback: 'Saving to extent and medium'
    },
    {
      patterns: ['save to alt text', 'save alt text'],
      action: function () { var v = window.ahgVoice; if (v) v.saveDescription('alt_text'); },
      mode: 'action_view',
      description: 'Save AI description as alt text',
      feedback: 'Saving to alt text'
    },
    {
      patterns: ['save to both'],
      action: function () { var v = window.ahgVoice; if (v) v.saveDescription('both'); },
      mode: 'action_view',
      description: 'Save AI description to both fields',
      feedback: 'Saving to both fields'
    },
    {
      patterns: ['discard', 'discard description', 'nevermind', 'never mind'],
      action: function () { var v = window.ahgVoice; if (v) v.discardDescription(); },
      mode: 'action_view',
      description: 'Discard the AI description',
      feedback: null // action speaks its own feedback
    },

    // -- Dictation --------------------------------------------------------
    {
      patterns: ['start dictating', 'start dictation', 'dictate'],
      action: function () {
        var v = window.ahgVoice;
        if (!v) return;
        var field = document.activeElement;
        if (field && (field.tagName === 'INPUT' && field.type === 'text' || field.tagName === 'TEXTAREA')) {
          v.startDictation(field);
        } else {
          var firstField = document.querySelector('form#editForm textarea, form.form-edit textarea, form#editForm input[type="text"], form.form-edit input[type="text"]');
          if (firstField) {
            v.startDictation(firstField);
          } else {
            v.speak('No text field found. Focus a field first.');
            v.showToast('No text field found', 'warning');
          }
        }
      },
      mode: 'dictation',
      description: 'Start dictating into focused field',
      feedback: 'Starting dictation mode',
      contextCheck: function () { return !!document.querySelector('form#editForm, form.form-edit, textarea, input[type="text"]'); }
    },
    {
      patterns: ['stop dictating', 'stop dictation'],
      action: function () {
        var v = window.ahgVoice;
        if (v && v.mode === 'dictation') { v.stopDictation(); }
        else if (v) { v.speak('Not in dictation mode'); }
      },
      mode: 'dictation',
      description: 'Stop dictating',
      feedback: null // action speaks its own feedback
    },

    // -- Listening Mode ---------------------------------------------------
    {
      patterns: ['keep listening', 'continuous listening', 'stay on', 'always listen'],
      action: function () {
        var v = window.ahgVoice;
        if (!v) return;
        v._continuousMode = true;
        try { sessionStorage.setItem('ahg_voice_continuous', '1'); } catch (e) { /* ignore */ }
        v.speak('Continuous listening enabled. I will keep listening after each command. Say stop listening to turn off.');
        v.showToast('Continuous listening ON', 'success');
      },
      mode: 'global',
      description: 'Enable continuous listening mode',
      feedback: null
    },
    {
      patterns: ['stop continuous', 'single command', 'one at a time', 'stop listening'],
      action: function () {
        var v = window.ahgVoice;
        if (!v) return;
        v._continuousMode = false;
        try { sessionStorage.setItem('ahg_voice_continuous', '0'); } catch (e) { /* ignore */ }
        v.speak('Continuous listening disabled. I will stop after each command.');
        v.showToast('Continuous listening OFF', 'info');
      },
      mode: 'global',
      description: 'Disable continuous listening mode',
      feedback: null
    },

    // -- Accessibility & Help ---------------------------------------------
    {
      patterns: ['help', 'show commands', 'voice help', 'what can you do'],
      action: function () {
        var modal = document.getElementById('voice-help-modal');
        if (modal && typeof bootstrap !== 'undefined') {
          var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
          bsModal.show();
        }
      },
      mode: 'nav',
      description: 'Show voice commands help modal',
      feedback: 'Here are the available commands'
    },
    {
      patterns: [
        'list commands', 'list command', 'list sections', 'commands',
        'what commands', 'what commands are available', 'available commands',
        'read sections', 'command sections', 'which commands'
      ],
      action: function () { var v = window.ahgVoice; if (v) v.listSections(); },
      mode: 'nav',
      description: 'Read available command sections aloud',
      feedback: null
    },
    {
      patterns: ['read all commands', 'list all commands', 'read everything', 'all commands'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands(); },
      mode: 'nav',
      description: 'Read all commands aloud',
      feedback: null
    },
    {
      patterns: ['navigation commands', 'navigation', 'list navigation', 'read navigation', 'nav commands'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('navigation'); },
      mode: 'nav',
      description: 'Read navigation commands',
      feedback: null
    },
    {
      patterns: ['edit commands', 'edit actions', 'list edit', 'read edit', 'editing commands'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('edit'); },
      mode: 'nav',
      description: 'Read edit page commands',
      feedback: null
    },
    {
      patterns: ['view commands', 'view actions', 'list view', 'read view', 'viewing commands'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('view'); },
      mode: 'nav',
      description: 'Read view page commands',
      feedback: null
    },
    {
      patterns: ['browse commands', 'browse actions', 'list browse', 'read browse', 'browsing commands'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('browse'); },
      mode: 'nav',
      description: 'Read browse page commands',
      feedback: null
    },
    {
      patterns: ['global commands', 'list global', 'read global'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('global'); },
      mode: 'nav',
      description: 'Read global commands',
      feedback: null
    },
    {
      patterns: ['dictation commands', 'list dictation', 'read dictation'],
      action: function () { var v = window.ahgVoice; if (v) v.listCommands('dictation'); },
      mode: 'nav',
      description: 'Read dictation commands',
      feedback: null
    },
    {
      patterns: [/^list (\w+) commands?$/, /^read (\w+) commands?$/, /^(\w+) commands$/],
      action: function (text) {
        var v = window.ahgVoice;
        if (!v) return;
        var match = text.match(/(?:list |read )?(\w+) commands?/);
        if (match) { v.listCommands(match[1]); }
        else { v.listSections(); }
      },
      mode: 'nav',
      description: 'Read commands for a specific section',
      feedback: null
    },
    {
      patterns: ['where am i', 'what page is this', 'what page am i on', 'current page', 'announce page'],
      action: function () { var v = window.ahgVoice; if (v) v.whereAmI(); },
      mode: 'global',
      description: 'Announce current page and available actions',
      feedback: null // action handles its own speech
    },
    {
      patterns: ['how many results', 'result count', 'count results', 'how many records'],
      action: function () { var v = window.ahgVoice; if (v) v.howManyResults(); },
      mode: 'action_browse',
      description: 'Announce the number of results',
      feedback: null, // action handles its own speech
      contextCheck: function () { return !!document.querySelector('.result-count, .pager, .pagination, .display.browse, #counts-block'); }
    }
  ];

  return {
    getCommands: function () { return commands; },

    /**
     * Check if the current page has a digital object (image, video, audio, or metadata).
     * Used by contextCheck for "describe image" and "read image info" commands.
     */
    _hasDigitalObject: function () {
      // Direct media elements (broad — gallery/museum templates don't use #content)
      if (document.querySelector('#wrapper img.img-fluid, #sidebar img.img-fluid, #content img.img-fluid, .digital-object-viewer, .converted-image-viewer, video, audio')) {
        return true;
      }
      // IIIF viewer / OpenSeadragon / 3D viewer / Model Viewer / Gaussian Splat
      if (document.querySelector('.iiif-viewer-container, .osd-viewer, [id^="container-iiif-viewer"], [id^="viewer-3d-"], model-viewer, .three-js-viewer, .gaussian-splat-viewer')) {
        return true;
      }
      // Digital object metadata section
      if (document.querySelector('.digitalObjectMetadata, #digitalObjectMetadata, .digital-object-metadata')) {
        return true;
      }
      // Check for field labels indicating digital object metadata
      var fields = document.querySelectorAll('#content .field, #content tr, #content dt, #content h3, #content .row, #wrapper .field, #wrapper tr');
      for (var i = 0; i < fields.length; i++) {
        var t = fields[i].textContent || '';
        if (/master\s*file|media\s*type|mime[\s-]*type|original\s*file/i.test(t)) {
          return true;
        }
      }
      return false;
    },

    /**
     * Get commands grouped by mode for the help modal.
     */
    getGrouped: function () {
      var groups = {};
      commands.forEach(function (cmd) {
        var g = cmd.mode || 'other';
        if (!groups[g]) groups[g] = [];
        groups[g].push(cmd);
      });
      return groups;
    }
  };
})();
