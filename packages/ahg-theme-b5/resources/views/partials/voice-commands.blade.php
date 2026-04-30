{{-- Voice Commands UI partial — included in master layout.
     Renders: listening indicator, floating mic button, toast container, and help modal.
     Skips rendering for bots/crawlers. --}}

@php
    $ua = request()->header('User-Agent', '');
    $isBot = preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit/i', $ua);
@endphp

@if(!$isBot)
<!-- Voice: Listening indicator bar -->
<div id="voice-indicator" class="voice-indicator voice-ui" style="display:none"></div>

<!-- Voice: Floating mic button (bottom-right) -->
<button id="voice-floating-btn"
  class="voice-floating-btn voice-ui"
  style="display:none"
  type="button"
  aria-label="{{ __('Toggle voice commands') }}"
  title="{{ __('Click: voice | Right-click: type command') }}">
  <i class="bi bi-mic"></i>
</button>

<!-- Voice: Toast container -->
<div id="voice-toast-container" class="voice-toast-container voice-ui" style="display:none" aria-live="polite"></div>

<!-- Voice: Help modal -->
<div class="modal fade voice-ui" id="voice-help-modal" tabindex="-1" aria-labelledby="voice-help-label" aria-hidden="true" style="display:none">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="voice-help-label"><i class="bi bi-mic me-2"></i>Voice Commands</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <input type="text" class="form-control form-control-sm" id="voice-help-search" placeholder="{{ __('Filter commands...') }}" autocomplete="off">
        </div>
        <p class="text-muted small">Click the mic button and speak a command. Commands are not case-sensitive.</p>

        <h6><i class="bi bi-signpost-2 me-1"></i>Navigation</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"go home"</span><span class="voice-cmd-desc">{{ __('Go to homepage') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse / go to browse"</span><span class="voice-cmd-desc">{{ __('Browse archival records') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to admin"</span><span class="voice-cmd-desc">{{ __('Go to admin panel') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to settings"</span><span class="voice-cmd-desc">{{ __('Go to settings') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to clipboard"</span><span class="voice-cmd-desc">{{ __('Go to clipboard') }}</span></li>
          <li><span class="voice-cmd-phrase">"go back"</span><span class="voice-cmd-desc">{{ __('Go back') }}</span></li>
          <li><span class="voice-cmd-phrase">"next page"</span><span class="voice-cmd-desc">{{ __('Next page') }}</span></li>
          <li><span class="voice-cmd-phrase">"previous page"</span><span class="voice-cmd-desc">{{ __('Previous page') }}</span></li>
          <li><span class="voice-cmd-phrase">"search for [term]"</span><span class="voice-cmd-desc">{{ __('Search for a term') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to donors"</span><span class="voice-cmd-desc">{{ __('Browse donors') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to research / reading room"</span><span class="voice-cmd-desc">{{ __('Go to research / reading room') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to authorities"</span><span class="voice-cmd-desc">{{ __('Browse authority records') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to places"</span><span class="voice-cmd-desc">{{ __('Browse places') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to subjects"</span><span class="voice-cmd-desc">{{ __('Browse subjects') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to digital objects"</span><span class="voice-cmd-desc">{{ __('Browse digital objects') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to accessions"</span><span class="voice-cmd-desc">{{ __('Browse accessions') }}</span></li>
          <li><span class="voice-cmd-phrase">"go to repositories"</span><span class="voice-cmd-desc">{{ __('Browse repositories') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse archive"</span><span class="voice-cmd-desc">{{ __('Browse archive records') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse library"</span><span class="voice-cmd-desc">{{ __('Browse library records') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse museum"</span><span class="voice-cmd-desc">{{ __('Browse museum records') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse gallery"</span><span class="voice-cmd-desc">{{ __('Browse gallery records') }}</span></li>
          <li><span class="voice-cmd-phrase">"browse dam / browse photos"</span><span class="voice-cmd-desc">{{ __('Browse DAM/photo records') }}</span></li>
        </ul>

        <h6><i class="bi bi-pencil me-1"></i>Actions (Edit)</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"save / save record"</span><span class="voice-cmd-desc">Save the current record <span class="voice-ctx-badge voice-ctx-edit">edit pages</span></span></li>
          <li><span class="voice-cmd-phrase">"cancel"</span><span class="voice-cmd-desc">Cancel editing <span class="voice-ctx-badge voice-ctx-edit">edit pages</span></span></li>
          <li><span class="voice-cmd-phrase">"delete / delete record"</span><span class="voice-cmd-desc">Delete the current record <span class="voice-ctx-badge voice-ctx-edit">edit pages</span></span></li>
        </ul>

        <h6><i class="bi bi-eye me-1"></i>Actions (View)</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"edit / edit record"</span><span class="voice-cmd-desc">Edit the current record <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"print"</span><span class="voice-cmd-desc">Print the current page <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"export csv"</span><span class="voice-cmd-desc">Export as CSV <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"export ead"</span><span class="voice-cmd-desc">Export as EAD <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
        </ul>

        <h6><i class="bi bi-list-ul me-1"></i>Actions (Browse)</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"first result / open first"</span><span class="voice-cmd-desc">Open the first result <span class="voice-ctx-badge voice-ctx-browse">browse pages</span></span></li>
          <li><span class="voice-cmd-phrase">"sort by title"</span><span class="voice-cmd-desc">Sort results by title <span class="voice-ctx-badge voice-ctx-browse">browse pages</span></span></li>
          <li><span class="voice-cmd-phrase">"sort by date"</span><span class="voice-cmd-desc">Sort results by date <span class="voice-ctx-badge voice-ctx-browse">browse pages</span></span></li>
        </ul>

        <h6><i class="bi bi-globe me-1"></i>Global</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"toggle advanced search"</span><span class="voice-cmd-desc">Toggle advanced search <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"clear search"</span><span class="voice-cmd-desc">Clear search and reload <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"scroll down"</span><span class="voice-cmd-desc">Scroll down <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"scroll up"</span><span class="voice-cmd-desc">Scroll up <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"scroll to top"</span><span class="voice-cmd-desc">Scroll to top <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"scroll to bottom"</span><span class="voice-cmd-desc">Scroll to bottom <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"keep listening / continuous listening"</span><span class="voice-cmd-desc">Stay on after each command <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
          <li><span class="voice-cmd-phrase">"stop continuous / single command"</span><span class="voice-cmd-desc">Stop after each command <span class="voice-ctx-badge voice-ctx-global">global</span></span></li>
        </ul>

        <h6><i class="bi bi-image me-1"></i>Image &amp; Reading</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"read metadata / read all fields"</span><span class="voice-cmd-desc">Read all populated fields aloud <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"read title"</span><span class="voice-cmd-desc">Read the record title <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"read description"</span><span class="voice-cmd-desc">Read the description aloud <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"describe object / what is this"</span><span class="voice-cmd-desc">AI describe what is in the image <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"what type of file / file type"</span><span class="voice-cmd-desc">Report the file type in plain English <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"read pdf / read document"</span><span class="voice-cmd-desc">Read PDF content aloud <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"stop reading / shut up"</span><span class="voice-cmd-desc">Stop all speech output <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
          <li><span class="voice-cmd-phrase">"slower / faster"</span><span class="voice-cmd-desc">Adjust speech rate <span class="voice-ctx-badge voice-ctx-view">view pages</span></span></li>
        </ul>

        <h6><i class="bi bi-robot me-1"></i>AI Image Description</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"describe image / AI describe"</span><span class="voice-cmd-desc">Generate AI description of image <span class="voice-ctx-badge voice-ctx-ai">requires AI</span></span></li>
          <li><span class="voice-cmd-phrase">"save to description"</span><span class="voice-cmd-desc">Save AI description to record <span class="voice-ctx-badge voice-ctx-ai">requires AI</span></span></li>
          <li><span class="voice-cmd-phrase">"save to alt text"</span><span class="voice-cmd-desc">Save as image alt text <span class="voice-ctx-badge voice-ctx-ai">requires AI</span></span></li>
          <li><span class="voice-cmd-phrase">"save to both"</span><span class="voice-cmd-desc">Save to description and alt text <span class="voice-ctx-badge voice-ctx-ai">requires AI</span></span></li>
          <li><span class="voice-cmd-phrase">"discard"</span><span class="voice-cmd-desc">Discard AI description <span class="voice-ctx-badge voice-ctx-ai">requires AI</span></span></li>
        </ul>

        <h6><i class="bi bi-keyboard me-1"></i>Dictation</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"start dictating"</span><span class="voice-cmd-desc">Start dictating into focused field <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"stop dictating"</span><span class="voice-cmd-desc">Stop dictation, return to command mode <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
        </ul>
        <p class="text-muted small mt-1 mb-2">While dictating, say these for punctuation:</p>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"period / full stop"</span><span class="voice-cmd-desc">Insert . <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"comma"</span><span class="voice-cmd-desc">Insert , <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"question mark"</span><span class="voice-cmd-desc">Insert ? <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"exclamation mark"</span><span class="voice-cmd-desc">Insert ! <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"colon / semicolon"</span><span class="voice-cmd-desc">Insert : or ; <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"new line"</span><span class="voice-cmd-desc">Insert line break <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"new paragraph"</span><span class="voice-cmd-desc">Insert double line break <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"open quote / close quote"</span><span class="voice-cmd-desc">Insert curly quotes <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"open bracket / close bracket"</span><span class="voice-cmd-desc">Insert ( or ) <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"dash / hyphen"</span><span class="voice-cmd-desc">Insert dash or hyphen <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"undo last"</span><span class="voice-cmd-desc">Remove last dictated segment <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"clear field"</span><span class="voice-cmd-desc">Clear the entire field (with confirmation) <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
          <li><span class="voice-cmd-phrase">"read back"</span><span class="voice-cmd-desc">Read the field content aloud <span class="voice-ctx-badge voice-ctx-dictation">dictation mode</span></span></li>
        </ul>

        <h6><i class="bi bi-universal-access me-1"></i>Accessibility</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"where am I"</span><span class="voice-cmd-desc">{{ __('Announce current page and available actions') }}</span></li>
          <li><span class="voice-cmd-phrase">"how many results"</span><span class="voice-cmd-desc">{{ __('Announce the number of results on browse pages') }}</span></li>
          <li><span class="voice-cmd-phrase">"disable voice / voice off"</span><span class="voice-cmd-desc">{{ __('Disable voice commands until re-enabled') }}</span></li>
          <li><span class="voice-cmd-phrase">"enable voice / voice on"</span><span class="voice-cmd-desc">{{ __('Re-enable voice commands') }}</span></li>
        </ul>

        <h6><i class="bi bi-question-circle me-1"></i>Help</h6>
        <ul class="voice-cmd-list">
          <li><span class="voice-cmd-phrase">"help / show commands"</span><span class="voice-cmd-desc">{{ __('Show this help modal') }}</span></li>
          <li><span class="voice-cmd-phrase">"list commands / list sections"</span><span class="voice-cmd-desc">{{ __('Read available sections aloud, then say a section name') }}</span></li>
          <li><span class="voice-cmd-phrase">"navigation / edit / view / browse / global / dictation"</span><span class="voice-cmd-desc">{{ __('Read commands for that section') }}</span></li>
          <li><span class="voice-cmd-phrase">"read all commands"</span><span class="voice-cmd-desc">{{ __('Read every command aloud (say &quot;stop&quot; to stop)') }}</span></li>
        </ul>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn atom-btn-outline-light btn-sm" data-bs-dismiss="modal">{{ __('Close') }}</button>
      </div>
    </div>
  </div>
</div>
@endif
