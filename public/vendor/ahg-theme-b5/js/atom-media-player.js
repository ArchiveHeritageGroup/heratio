/**
 * AHG Atom Media Player with Visual Snippet Markers
 * Full-featured media player with IN/OUT markers on progress bar
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 */

(function(window) {
    'use strict';

    class AtomMediaPlayer {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' 
                ? document.querySelector(container) 
                : container;
            
            if (!this.container) {
                console.error('AtomMediaPlayer: Container not found');
                return;
            }

            this.options = Object.assign({
                mediaUrl: '',
                streamUrl: '',
                mediaType: 'video',
                digitalObjectId: 0,
                mimeType: '',
                autoplay: false,
                theme: 'dark',
                skipSeconds: 10,
                allowSnippets: true,
                onReady: null,
                onSnippetSave: null,
            }, options);

            this.player = null;
            this.duration = 0;
            this.speeds = [0.5, 0.75, 1, 1.25, 1.5, 2];
            this.currentSpeedIndex = 2;
            this.snippetIn = null;
            this.snippetOut = null;
            this.markers = [];
            this.isStreaming = false;

            this.init();
        }

        init() {
            this.render();
            this.bindElements();
            this.bindEvents();
            this.loadExistingSnippets();
        }

        render() {
            const isVideo = this.options.mediaType === 'video';
            const id = this.options.digitalObjectId;
            
            this.container.innerHTML = `
                <div class="atom-player atom-player--${this.options.theme} ${isVideo ? 'atom-player--video' : 'atom-player--audio'}">
                    <div class="atom-player__media-wrapper">
                        ${isVideo 
                            ? `<video class="atom-player__video" preload="metadata"></video>`
                            : `<audio class="atom-player__audio" preload="metadata"></audio>`
                        }
                    </div>
                    
                    <div class="atom-player__progress-section">
                        <div class="atom-player__progress-bar">
                            <div class="atom-player__progress-buffered"></div>
                            <div class="atom-player__progress-played"></div>
                            <div class="atom-player__progress-handle"></div>
                            <div class="atom-player__snippet-region"></div>
                            <div class="atom-player__markers"></div>
                        </div>
                        <div class="atom-player__time">
                            <span class="atom-player__time-current">0:00</span>
                            <span class="atom-player__time-sep"> / </span>
                            <span class="atom-player__time-duration">0:00</span>
                        </div>
                    </div>
                    
                    <div class="atom-player__controls">
                        <div class="atom-player__controls-left">
                            <button class="atom-player__btn atom-player__btn-play" title="Play/Pause (Space)">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-skip-back" title="Back ${this.options.skipSeconds}s">
                                <i class="fas fa-backward"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-skip-fwd" title="Forward ${this.options.skipSeconds}s">
                                <i class="fas fa-forward"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-mute" title="Mute (M)">
                                <i class="fas fa-volume-up"></i>
                            </button>
                            <input type="range" class="atom-player__volume" min="0" max="1" step="0.1" value="1">
                            <button class="atom-player__btn atom-player__btn-speed" title="Speed"><span>1x</span></button>
                        </div>
                        
                        <div class="atom-player__controls-center">
                            ${this.options.allowSnippets ? `
                            <button class="atom-player__btn atom-player__btn-in" title="Set IN Point (I)">
                                <i class="fas fa-sign-in-alt"></i> IN
                            </button>
                            <span class="atom-player__snippet-time atom-player__snippet-in-time">--:--</span>
                            <span class="atom-player__snippet-arrow">→</span>
                            <button class="atom-player__btn atom-player__btn-out" title="Set OUT Point (O)">
                                OUT <i class="fas fa-sign-out-alt"></i>
                            </button>
                            <span class="atom-player__snippet-time atom-player__snippet-out-time">--:--</span>
                            <span class="atom-player__snippet-duration"></span>
                            ` : ''}
                        </div>
                        
                        <div class="atom-player__controls-right">
                            ${this.options.allowSnippets ? `
                            <button class="atom-player__btn atom-player__btn-preview" title="Preview Selection" disabled>
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-save" title="Save Snippet" disabled>
                                <i class="fas fa-save"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-export" title="Export Snippet" disabled>
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="atom-player__btn atom-player__btn-clear" title="Clear Selection">
                                <i class="fas fa-times"></i>
                            </button>
                            ` : ''}
                            ${isVideo ? `
                            <button class="atom-player__btn atom-player__btn-fullscreen" title="Fullscreen (F)">
                                <i class="fas fa-expand"></i>
                            </button>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="atom-player__status"></div>
                </div>
                
                <div class="atom-player__modal" id="snippet-modal-${id}" style="display:none;">
                    <div class="atom-player__modal-content">
                        <div class="atom-player__modal-header">
                            <h5><i class="fas fa-cut"></i> Save Snippet</h5>
                            <button class="atom-player__modal-close">&times;</button>
                        </div>
                        <div class="atom-player__modal-body">
                            <input type="text" class="atom-player__modal-input" placeholder="Snippet title" id="snippet-title-${id}">
                            <div class="atom-player__modal-range" id="snippet-range-${id}"></div>
                        </div>
                        <div class="atom-player__modal-footer">
                            <button class="atom-player__btn atom-player__modal-cancel">Cancel</button>
                            <button class="atom-player__btn atom-player__btn--primary atom-player__modal-save">Save</button>
                        </div>
                    </div>
                </div>
            `;
            
            this.injectStyles();
        }

        bindElements() {
            const isVideo = this.options.mediaType === 'video';
            
            this.player = this.container.querySelector(isVideo ? '.atom-player__video' : '.atom-player__audio');
            this.progressBar = this.container.querySelector('.atom-player__progress-bar');
            this.progressPlayed = this.container.querySelector('.atom-player__progress-played');
            this.progressBuffered = this.container.querySelector('.atom-player__progress-buffered');
            this.progressHandle = this.container.querySelector('.atom-player__progress-handle');
            this.snippetRegion = this.container.querySelector('.atom-player__snippet-region');
            this.markersContainer = this.container.querySelector('.atom-player__markers');
            this.timeCurrent = this.container.querySelector('.atom-player__time-current');
            this.timeDuration = this.container.querySelector('.atom-player__time-duration');
            this.volumeSlider = this.container.querySelector('.atom-player__volume');
            this.statusEl = this.container.querySelector('.atom-player__status');
            
            this.btnPlay = this.container.querySelector('.atom-player__btn-play');
            this.btnMute = this.container.querySelector('.atom-player__btn-mute');
            this.btnSpeed = this.container.querySelector('.atom-player__btn-speed');
            this.btnIn = this.container.querySelector('.atom-player__btn-in');
            this.btnOut = this.container.querySelector('.atom-player__btn-out');
            this.btnPreview = this.container.querySelector('.atom-player__btn-preview');
            this.btnSave = this.container.querySelector('.atom-player__btn-save');
            this.btnExport = this.container.querySelector('.atom-player__btn-export');
            this.btnClear = this.container.querySelector('.atom-player__btn-clear');
            this.btnFullscreen = this.container.querySelector('.atom-player__btn-fullscreen');
            
            this.snippetInTime = this.container.querySelector('.atom-player__snippet-in-time');
            this.snippetOutTime = this.container.querySelector('.atom-player__snippet-out-time');
            this.snippetDuration = this.container.querySelector('.atom-player__snippet-duration');
            
            // Set source - try streaming first for compatibility
            const source = document.createElement('source');
            source.src = this.options.streamUrl || this.options.mediaUrl;
            source.type = this.options.mediaType === 'video' ? 'video/mp4' : 'audio/mpeg';
            this.player.appendChild(source);
        }

        bindEvents() {
            this.player.addEventListener('loadedmetadata', () => this.onLoadedMetadata());
            this.player.addEventListener('timeupdate', () => this.onTimeUpdate());
            this.player.addEventListener('progress', () => this.onProgress());
            this.player.addEventListener('play', () => this.onPlay());
            this.player.addEventListener('pause', () => this.onPause());
            this.player.addEventListener('ended', () => this.onEnded());
            this.player.addEventListener('error', () => this.onError());
            this.player.addEventListener('waiting', () => this.setStatus('Buffering...', 'warning'));
            this.player.addEventListener('canplay', () => this.setStatus('Ready', 'success'));
            
            this.btnPlay.addEventListener('click', () => this.togglePlay());
            this.container.querySelector('.atom-player__btn-skip-back').addEventListener('click', () => this.skip(-this.options.skipSeconds));
            this.container.querySelector('.atom-player__btn-skip-fwd').addEventListener('click', () => this.skip(this.options.skipSeconds));
            this.btnMute.addEventListener('click', () => this.toggleMute());
            this.volumeSlider.addEventListener('input', (e) => this.setVolume(e.target.value));
            this.btnSpeed.addEventListener('click', () => this.cycleSpeed());
            
            this.progressBar.addEventListener('click', (e) => this.seek(e));
            
            if (this.options.allowSnippets) {
                this.btnIn.addEventListener('click', () => this.setInPoint());
                this.btnOut.addEventListener('click', () => this.setOutPoint());
                this.btnPreview.addEventListener('click', () => this.previewSnippet());
                this.btnSave.addEventListener('click', () => this.showSaveModal());
                this.btnExport.addEventListener('click', () => this.exportSnippet());
                this.btnClear.addEventListener('click', () => this.clearSnippet());
            }
            
            if (this.btnFullscreen) {
                this.btnFullscreen.addEventListener('click', () => this.toggleFullscreen());
            }
            
            const modal = this.container.querySelector('.atom-player__modal');
            if (modal) {
                modal.querySelector('.atom-player__modal-close').addEventListener('click', () => this.hideModal());
                modal.querySelector('.atom-player__modal-cancel').addEventListener('click', () => this.hideModal());
                modal.querySelector('.atom-player__modal-save').addEventListener('click', () => this.saveSnippet());
            }
            
            document.addEventListener('keydown', (e) => this.handleKeyboard(e));
        }
        
        togglePlay() {
            if (this.player.paused) this.player.play();
            else this.player.pause();
        }
        
        skip(seconds) {
            this.player.currentTime = Math.max(0, Math.min(this.duration, this.player.currentTime + seconds));
        }
        
        toggleMute() {
            this.player.muted = !this.player.muted;
            this.btnMute.innerHTML = this.player.muted ? '<i class="fas fa-volume-mute"></i>' : '<i class="fas fa-volume-up"></i>';
        }
        
        setVolume(value) {
            this.player.volume = value;
            this.player.muted = value == 0;
        }
        
        cycleSpeed() {
            this.currentSpeedIndex = (this.currentSpeedIndex + 1) % this.speeds.length;
            this.player.playbackRate = this.speeds[this.currentSpeedIndex];
            this.btnSpeed.querySelector('span').textContent = this.speeds[this.currentSpeedIndex] + 'x';
        }
        
        seek(e) {
            const rect = this.progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            this.player.currentTime = percent * this.duration;
        }
        
        toggleFullscreen() {
            const wrapper = this.container.querySelector('.atom-player');
            if (document.fullscreenElement) {
                document.exitFullscreen();
                this.btnFullscreen.innerHTML = '<i class="fas fa-expand"></i>';
            } else {
                wrapper.requestFullscreen();
                this.btnFullscreen.innerHTML = '<i class="fas fa-compress"></i>';
            }
        }
        
        setInPoint() {
            this.snippetIn = this.player.currentTime;
            this.snippetInTime.textContent = this.formatTime(this.snippetIn);
            this.btnIn.classList.add('atom-player__btn--active');
            this.addMarker(this.snippetIn, 'in');
            this.updateSnippetRegion();
            this.updateSnippetButtons();
        }
        
        setOutPoint() {
            this.snippetOut = this.player.currentTime;
            if (this.snippetIn !== null && this.snippetOut < this.snippetIn) {
                [this.snippetIn, this.snippetOut] = [this.snippetOut, this.snippetIn];
                this.snippetInTime.textContent = this.formatTime(this.snippetIn);
            }
            this.snippetOutTime.textContent = this.formatTime(this.snippetOut);
            this.btnOut.classList.add('atom-player__btn--active');
            this.addMarker(this.snippetOut, 'out');
            this.updateSnippetRegion();
            this.updateSnippetButtons();
        }
        
        addMarker(time, type) {
            const existing = this.markersContainer.querySelector(`.atom-player__marker--${type}`);
            if (existing) existing.remove();
            
            const percent = (time / this.duration) * 100;
            const marker = document.createElement('div');
            marker.className = `atom-player__marker atom-player__marker--${type}`;
            marker.style.left = percent + '%';
            marker.title = `${type.toUpperCase()}: ${this.formatTime(time)}`;
            this.markersContainer.appendChild(marker);
        }
        
        updateSnippetRegion() {
            if (this.snippetIn !== null && this.snippetOut !== null) {
                const startPercent = (this.snippetIn / this.duration) * 100;
                const endPercent = (this.snippetOut / this.duration) * 100;
                this.snippetRegion.style.left = startPercent + '%';
                this.snippetRegion.style.width = (endPercent - startPercent) + '%';
                this.snippetRegion.style.display = 'block';
                this.snippetDuration.textContent = `(${this.formatTime(this.snippetOut - this.snippetIn)})`;
            } else {
                this.snippetRegion.style.display = 'none';
                this.snippetDuration.textContent = '';
            }
        }
        
        updateSnippetButtons() {
            const hasSelection = this.snippetIn !== null && this.snippetOut !== null;
            if (this.btnPreview) this.btnPreview.disabled = !hasSelection;
            if (this.btnSave) this.btnSave.disabled = !hasSelection;
            if (this.btnExport) this.btnExport.disabled = !hasSelection;
        }
        
        clearSnippet() {
            this.snippetIn = null;
            this.snippetOut = null;
            if (this.snippetInTime) this.snippetInTime.textContent = '--:--';
            if (this.snippetOutTime) this.snippetOutTime.textContent = '--:--';
            if (this.snippetDuration) this.snippetDuration.textContent = '';
            if (this.btnIn) this.btnIn.classList.remove('atom-player__btn--active');
            if (this.btnOut) this.btnOut.classList.remove('atom-player__btn--active');
            if (this.snippetRegion) this.snippetRegion.style.display = 'none';
            this.markersContainer.querySelectorAll('.atom-player__marker--in, .atom-player__marker--out').forEach(m => m.remove());
            this.updateSnippetButtons();
        }
        
        previewSnippet() {
            if (this.snippetIn === null || this.snippetOut === null) return;
            this.player.currentTime = this.snippetIn;
            this.player.play();
            const checkEnd = setInterval(() => {
                if (this.player.currentTime >= this.snippetOut || this.player.paused) {
                    this.player.pause();
                    clearInterval(checkEnd);
                }
            }, 50);
        }
        
        showSaveModal() {
            const modal = this.container.querySelector('.atom-player__modal');
            const rangeEl = this.container.querySelector(`#snippet-range-${this.options.digitalObjectId}`);
            if (rangeEl) rangeEl.textContent = `${this.formatTime(this.snippetIn)} → ${this.formatTime(this.snippetOut)}`;
            modal.style.display = 'flex';
        }
        
        hideModal() {
            const modal = this.container.querySelector('.atom-player__modal');
            if (modal) modal.style.display = 'none';
        }
        
        saveSnippet() {
            const title = this.container.querySelector(`#snippet-title-${this.options.digitalObjectId}`).value.trim();
            if (!title) { alert('Please enter a title'); return; }
            
            fetch('/media/snippets', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    digital_object_id: this.options.digitalObjectId,
                    title: title,
                    start_time: this.snippetIn,
                    end_time: this.snippetOut
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.hideModal();
                    this.loadExistingSnippets();
                    this.clearSnippet();
                    if (this.options.onSnippetSave) this.options.onSnippetSave(data);
                } else {
                    alert('Error: ' + (data.error || 'Failed'));
                }
            });
        }
        
        exportSnippet() {
            if (this.snippetIn === null || this.snippetOut === null) return;
            
            this.btnExport.disabled = true;
            this.btnExport.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            fetch('/media/export-snippet', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    digital_object_id: this.options.digitalObjectId,
                    start_time: this.snippetIn,
                    end_time: this.snippetOut
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.download_url) {
                    window.location.href = data.download_url;
                } else {
                    alert('Error: ' + (data.error || 'Export failed'));
                }
                this.btnExport.disabled = false;
                this.btnExport.innerHTML = '<i class="fas fa-download"></i>';
            });
        }
        
        loadExistingSnippets() {
            fetch(`/media/snippets/${this.options.digitalObjectId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.snippets) {
                        this.markers = data.snippets;
                        this.renderSavedMarkers();
                    }
                })
                .catch(() => {});
        }
        
        renderSavedMarkers() {
            this.markersContainer.querySelectorAll('.atom-player__marker--saved').forEach(m => m.remove());
            this.markers.forEach(snippet => {
                const percent = (snippet.start_time / this.duration) * 100;
                const marker = document.createElement('div');
                marker.className = 'atom-player__marker atom-player__marker--saved';
                marker.style.left = percent + '%';
                marker.title = `${snippet.title} (${this.formatTime(snippet.start_time)} - ${this.formatTime(snippet.end_time)})`;
                marker.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.player.currentTime = snippet.start_time;
                });
                this.markersContainer.appendChild(marker);
            });
        }
        
        onLoadedMetadata() {
            this.duration = this.player.duration;
            this.timeDuration.textContent = this.formatTime(this.duration);
            this.renderSavedMarkers();
            if (this.options.onReady) this.options.onReady(this);
        }
        
        onTimeUpdate() {
            const percent = (this.player.currentTime / this.duration) * 100;
            this.progressPlayed.style.width = percent + '%';
            this.progressHandle.style.left = percent + '%';
            this.timeCurrent.textContent = this.formatTime(this.player.currentTime);
        }
        
        onProgress() {
            if (this.player.buffered.length > 0) {
                const buffered = this.player.buffered.end(this.player.buffered.length - 1);
                this.progressBuffered.style.width = (buffered / this.duration) * 100 + '%';
            }
        }
        
        onPlay() { this.btnPlay.innerHTML = '<i class="fas fa-pause"></i>'; }
        onPause() { this.btnPlay.innerHTML = '<i class="fas fa-play"></i>'; }
        onEnded() { this.btnPlay.innerHTML = '<i class="fas fa-play"></i>'; }
        
        onError() {
            if (!this.isStreaming && this.options.digitalObjectId) {
                this.isStreaming = true;
                const source = this.player.querySelector('source');
                source.src = `/media/stream/${this.options.digitalObjectId}`;
                source.type = this.options.mediaType === 'video' ? 'video/mp4' : 'audio/mpeg';
                this.player.load();
                this.setStatus('Streaming...', 'info');
            } else {
                this.setStatus('Error loading media', 'danger');
            }
        }
        
        handleKeyboard(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            switch(e.key.toLowerCase()) {
                case ' ': e.preventDefault(); this.togglePlay(); break;
                case 'arrowleft': e.preventDefault(); this.skip(-this.options.skipSeconds); break;
                case 'arrowright': e.preventDefault(); this.skip(this.options.skipSeconds); break;
                case 'm': this.toggleMute(); break;
                case 'f': if (this.btnFullscreen) this.toggleFullscreen(); break;
                case 'i': if (this.options.allowSnippets) this.setInPoint(); break;
                case 'o': if (this.options.allowSnippets) this.setOutPoint(); break;
                case 'escape': this.hideModal(); break;
            }
        }
        
        formatTime(seconds) {
            if (!seconds || isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins + ':' + (secs < 10 ? '0' : '') + secs;
        }
        
        setStatus(message, type = 'info') {
            const icons = { success: 'check', warning: 'spinner fa-spin', danger: 'exclamation-triangle', info: 'info-circle' };
            this.statusEl.innerHTML = `<span class="text-${type}"><i class="fas fa-${icons[type]}"></i> ${message}</span>`;
        }

        injectStyles() {
            if (document.getElementById('atom-player-styles')) return;
            
            const css = `
            .atom-player {
                background: #1a1a2e;
                border-radius: 8px;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: #fff;
            }
            .atom-player__media-wrapper {
                position: relative;
                background: #000;
            }
            .atom-player__video {
                width: 100%;
                max-height: 500px;
                display: block;
            }
            .atom-player__audio {
                width: 100%;
            }
            .atom-player__progress-section {
                padding: 8px 12px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .atom-player__progress-bar {
                flex: 1;
                height: 8px;
                background: #444;
                border-radius: 4px;
                position: relative;
                cursor: pointer;
            }
            .atom-player__progress-buffered {
                position: absolute;
                top: 0; left: 0;
                height: 100%;
                background: #666;
                border-radius: 4px;
            }
            .atom-player__progress-played {
                position: absolute;
                top: 0; left: 0;
                height: 100%;
                background: #3498db;
                border-radius: 4px;
                z-index: 1;
            }
            .atom-player__progress-handle {
                position: absolute;
                top: 50%;
                width: 16px; height: 16px;
                background: #fff;
                border-radius: 50%;
                transform: translate(-50%, -50%);
                box-shadow: 0 2px 6px rgba(0,0,0,0.4);
                z-index: 3;
                opacity: 0;
                transition: opacity 0.2s;
            }
            .atom-player__progress-bar:hover .atom-player__progress-handle {
                opacity: 1;
            }
            .atom-player__snippet-region {
                position: absolute;
                top: -2px;
                height: calc(100% + 4px);
                background: rgba(241, 196, 15, 0.4);
                border-left: 3px solid #f39c12;
                border-right: 3px solid #f39c12;
                z-index: 2;
                pointer-events: none;
                display: none;
            }
            .atom-player__markers {
                position: absolute;
                top: 0; left: 0; right: 0;
                height: 100%;
                pointer-events: none;
                z-index: 4;
            }
            .atom-player__marker {
                position: absolute;
                top: -6px;
                width: 4px;
                height: 20px;
                transform: translateX(-50%);
                pointer-events: auto;
                cursor: pointer;
            }
            .atom-player__marker--in {
                background: #27ae60;
                border-radius: 2px 2px 0 0;
            }
            .atom-player__marker--out {
                background: #e74c3c;
                border-radius: 0 0 2px 2px;
            }
            .atom-player__marker--saved {
                background: #9b59b6;
                width: 6px; height: 16px;
                top: -4px;
                border-radius: 3px;
            }
            .atom-player__marker--saved:hover {
                transform: translateX(-50%) scale(1.3);
            }
            .atom-player__time {
                font-size: 12px;
                font-variant-numeric: tabular-nums;
                white-space: nowrap;
            }
            .atom-player__controls {
                padding: 8px 12px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 8px;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            .atom-player__controls-left,
            .atom-player__controls-center,
            .atom-player__controls-right {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            .atom-player__btn {
                background: transparent;
                border: 1px solid rgba(255,255,255,0.3);
                color: inherit;
                padding: 6px 10px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.2s;
            }
            .atom-player__btn:hover {
                background: rgba(255,255,255,0.1);
                border-color: rgba(255,255,255,0.5);
            }
            .atom-player__btn:disabled {
                opacity: 0.4;
                cursor: not-allowed;
            }
            .atom-player__btn--active {
                background: #27ae60 !important;
                border-color: #27ae60 !important;
            }
            .atom-player__btn--primary {
                background: #3498db;
                border-color: #3498db;
            }
            .atom-player__volume {
                width: 60px;
                height: 4px;
                -webkit-appearance: none;
                background: #444;
                border-radius: 2px;
            }
            .atom-player__volume::-webkit-slider-thumb {
                -webkit-appearance: none;
                width: 12px; height: 12px;
                background: #fff;
                border-radius: 50%;
                cursor: pointer;
            }
            .atom-player__snippet-time {
                font-family: monospace;
                font-size: 13px;
                min-width: 45px;
            }
            .atom-player__snippet-arrow { color: #888; }
            .atom-player__snippet-duration { color: #f39c12; font-size: 12px; }
            .atom-player__status {
                padding: 4px 12px;
                font-size: 12px;
                min-height: 24px;
            }
            .atom-player__modal {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            }
            .atom-player__modal-content {
                background: #2a2a2a;
                border-radius: 8px;
                width: 90%;
                max-width: 400px;
            }
            .atom-player__modal-header {
                padding: 12px 16px;
                border-bottom: 1px solid #444;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .atom-player__modal-header h5 { margin: 0; font-size: 16px; }
            .atom-player__modal-close {
                background: none;
                border: none;
                color: #fff;
                font-size: 24px;
                cursor: pointer;
            }
            .atom-player__modal-body { padding: 16px; }
            .atom-player__modal-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #444;
                background: #1a1a1a;
                color: #fff;
                border-radius: 4px;
                font-size: 14px;
            }
            .atom-player__modal-range { margin-top: 8px; color: #888; font-size: 13px; }
            .atom-player__modal-footer {
                padding: 12px 16px;
                border-top: 1px solid #444;
                display: flex;
                justify-content: flex-end;
                gap: 8px;
            }
            `;
            
            const style = document.createElement('style');
            style.id = 'atom-player-styles';
            style.textContent = css;
            document.head.appendChild(style);
        }
    }

    window.AtomMediaPlayer = AtomMediaPlayer;
    
})(window);
