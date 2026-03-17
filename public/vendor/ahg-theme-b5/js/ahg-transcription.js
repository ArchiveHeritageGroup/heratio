/**
 * AHG Media Controls - Extract metadata, transcription, snippets
 * All handlers use data attributes to avoid inline onclick with JS if() statements
 */
document.addEventListener('DOMContentLoaded', function () {

    // Extract Metadata button
    document.querySelectorAll('[data-action="extract"]').forEach(function (btn) {
        btn.onclick = function () {
            var doId = btn.dataset.doId, csrf = btn.dataset.csrf;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Extracting...';
            fetch('/media/extract/' + doId, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) { location.reload(); } else {
                        alert('Error: ' + (d.error || 'Failed'));
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-magic me-1"></i>Extract Metadata';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-magic me-1"></i>Extract Metadata';
                });
        };
    });

    // Re-transcribe button
    document.querySelectorAll('[data-action="retranscribe"]').forEach(function (btn) {
        btn.onclick = function () {
            if (!confirm('Re-transcribe this media?')) return;
            var doId = btn.dataset.doId, lang = btn.dataset.lang, csrf = btn.dataset.csrf;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            fetch('/media/transcribe/' + doId + '?lang=' + lang, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.success) location.reload(); else alert('Error: ' + (d.error || 'Unknown error')); })
                .catch(function () { alert('Error'); });
        };
    });

    // Transcribe buttons (English, Afrikaans, etc.)
    document.querySelectorAll('[data-action="transcribe"]').forEach(function (btn) {
        btn.onclick = function () {
            var doId = btn.dataset.doId, lang = btn.dataset.lang, csrf = btn.dataset.csrf;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transcribing...';
            fetch('/media/transcribe/' + doId + '?lang=' + lang, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) location.reload();
                    else { alert('Error'); btn.disabled = false; }
                })
                .catch(function () { btn.disabled = false; });
        };
    });

    // Save Snippet button
    document.querySelectorAll('[data-action="save-snippet"]').forEach(function (btn) {
        btn.onclick = function () {
            var doId = btn.dataset.doId, csrf = btn.dataset.csrf;
            var data = {
                digital_object_id: parseInt(doId),
                title: document.getElementById('snippet-title').value,
                start_time: document.getElementById('snippet-start').value,
                end_time: document.getElementById('snippet-end').value,
                notes: document.getElementById('snippet-notes').value
            };
            fetch('/media/snippets', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(data)
            })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.success) location.reload(); else alert('Error: ' + (d.error || 'Unknown error')); })
                .catch(function () { alert('Error'); });
        };
    });
});

/**
 * Transcription Panel - Interactive transcript viewer
 */
function initTranscriptionPanel(doId) {
    var panel = document.getElementById('transcription-panel-' + doId);
    if (!panel) return;

    var btnText = document.getElementById('btn-text-' + doId);
    var btnSegments = document.getElementById('btn-segments-' + doId);
    var fullText = panel.querySelector('.transcript-full-text');
    var segments = panel.querySelector('.transcript-segments');

    if (btnText && btnSegments) {
        btnText.onclick = function () {
            fullText.style.display = 'block'; segments.style.display = 'none';
            btnText.classList.add('active'); btnSegments.classList.remove('active');
        };
        btnSegments.onclick = function () {
            fullText.style.display = 'none'; segments.style.display = 'block';
            btnSegments.classList.add('active'); btnText.classList.remove('active');
        };
    }

    panel.querySelectorAll('.transcript-segment').forEach(function (seg) {
        seg.onmouseover = function () { this.style.background = '#f0f0f0'; };
        seg.onmouseout = function () { this.style.background = 'transparent'; };
        seg.onclick = function () {
            var player = document.querySelector('audio, video');
            if (player) { player.currentTime = parseFloat(seg.dataset.start); player.play(); }
            panel.querySelectorAll('.transcript-segment').forEach(function (x) { x.style.background = 'transparent'; });
            seg.style.background = '#fff3cd';
        };
    });

    var searchInput = document.getElementById('transcript-search-' + doId);
    var searchBtn = document.getElementById('transcript-search-btn-' + doId);
    function doSearch() {
        var query = searchInput.value.toLowerCase().trim();
        if (!query) return;
        panel.querySelectorAll('.transcript-segment').forEach(function (s) {
            var match = s.textContent.toLowerCase().indexOf(query) >= 0;
            s.style.display = match ? 'block' : 'none';
            if (match) s.style.background = '#d4edda';
        });
        if (btnSegments) btnSegments.click();
    }
    if (searchBtn) searchBtn.onclick = doSearch;
    if (searchInput) searchInput.onkeypress = function (e) { if (e.key === 'Enter') doSearch(); };
}
