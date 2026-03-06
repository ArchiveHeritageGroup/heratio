/**
 * AHG Enhanced Media Player
 * Handles playback of all media formats with streaming fallback
 *
 * @author Johan Pieterse - The Archive and Heritage Group
 */

(function(window) {
    'use strict';

    /**
     * Enhanced Media Player Class
     */
    class AhgMediaPlayer {
        constructor(containerId, options = {}) {
            this.container = typeof containerId === 'string'
                ? document.querySelector(containerId)
                : containerId;

            if (!this.container) {
                console.error('AhgMediaPlayer: Container not found');
                return;
            }

            this.options = Object.assign({
                mediaUrl: '',
                streamUrl: '',
                mediaType: 'video',
                digitalObjectId: 0,
                mimeType: '',
                autoplay: false,
                controls: true,
                showMetadata: true,
                onReady: null,
                onError: null,
                onPlay: null,
                onPause: null,
            }, options);

            this.player = null;
            this.isStreaming = false;
            this.speeds = [0.5, 0.75, 1, 1.25, 1.5, 2];
            this.currentSpeedIndex = 2;

            this.init();
        }

        /**
         * Initialize the player
         */
        init() {
            // Find existing player element or create one
            this.player = this.container.querySelector('video, audio');

            if (!this.player) {
                this.createPlayer();
            }

            this.attachEvents();
            this.checkPlaybackSupport();
        }

        /**
         * Create the media player element
         */
        createPlayer() {
            const isVideo = this.options.mediaType === 'video';
            this.player = document.createElement(isVideo ? 'video' : 'audio');

            this.player.controls = this.options.controls;
            this.player.preload = 'metadata';

            if (isVideo) {
                this.player.style.cssText = 'width:100%; max-height:500px; background:#000;';
            } else {
                this.player.style.cssText = 'width:100%;';
            }

            // Add source
            const source = document.createElement('source');
            source.src = this.options.streamUrl || this.options.mediaUrl;
            source.type = this.getPlayerMimeType();
            this.player.appendChild(source);

            // Wrap in container
            const playerWrapper = document.createElement('div');
            playerWrapper.className = 'player-container';
            playerWrapper.style.cssText = 'background:#000; border-radius:8px; overflow:hidden;';
            playerWrapper.appendChild(this.player);

            this.container.insertBefore(playerWrapper, this.container.firstChild);
        }

        /**
         * Get browser-compatible MIME type
         */
        getPlayerMimeType() {
            const mime = this.options.mimeType;

            // If using streaming endpoint, use transcoded format
            if (this.options.streamUrl && this.needsTranscoding(mime)) {
                return this.options.mediaType === 'video' ? 'video/mp4' : 'audio/mpeg';
            }

            return mime;
        }

        /**
         * Check if format needs transcoding
         */
        needsTranscoding(mimeType) {
            const transcodingMimes = [
                // Video
                'video/x-ms-asf', 'video/x-msvideo', 'video/quicktime',
                'video/x-ms-wmv', 'video/x-flv', 'video/x-matroska',
                'video/mp2t', 'video/x-ms-wtv', 'video/hevc',
                'application/mxf', 'video/3gpp',
                // Audio
                'audio/aiff', 'audio/x-aiff', 'audio/basic', 'audio/x-au',
                'audio/ac3', 'audio/8svx', 'audio/AMB', 'audio/x-ms-wma',
                'audio/x-pn-realaudio', 'audio/flac', 'audio/x-flac'
            ];

            return transcodingMimes.includes(mimeType);
        }

        /**
         * Check if browser can play the format directly
         */
        checkPlaybackSupport() {
            const source = this.player.querySelector('source');
            if (!source) return;

            const canPlay = this.player.canPlayType(source.type);

            if (!canPlay || canPlay === '') {
                // Try streaming endpoint
                if (this.options.digitalObjectId && !this.isStreaming) {
                    this.switchToStreaming();
                }
            }
        }

        /**
         * Switch to streaming endpoint for transcoding
         */
        switchToStreaming() {
            if (this.isStreaming) return;

            const baseUrl = window.location.origin;
            const streamUrl = `${baseUrl}/media/stream/${this.options.digitalObjectId}`;
            const newMime = this.options.mediaType === 'video' ? 'video/mp4' : 'audio/mpeg';

            const source = this.player.querySelector('source');
            if (source) {
                source.src = streamUrl;
                source.type = newMime;
            }

            this.player.load();
            this.isStreaming = true;

            this.showNotice('Streaming transcoded content for browser compatibility');
        }

        /**
         * Attach event listeners
         */
        attachEvents() {
            if (!this.player) return;

            this.player.addEventListener('error', (e) => this.handleError(e));
            this.player.addEventListener('loadstart', () => this.handleLoadStart());
            this.player.addEventListener('canplay', () => this.handleCanPlay());
            this.player.addEventListener('play', () => this.handlePlay());
            this.player.addEventListener('pause', () => this.handlePause());
            this.player.addEventListener('ended', () => this.handleEnded());
            this.player.addEventListener('waiting', () => this.handleBuffering(true));
            this.player.addEventListener('playing', () => this.handleBuffering(false));
        }

        /**
         * Handle media error
         */
        handleError(e) {
            const error = this.player.error;
            let message = 'Error loading media';

            if (error) {
                switch (error.code) {
                    case MediaError.MEDIA_ERR_ABORTED:
                        message = 'Playback aborted';
                        break;
                    case MediaError.MEDIA_ERR_NETWORK:
                        message = 'Network error';
                        break;
                    case MediaError.MEDIA_ERR_DECODE:
                        message = 'Decoding error';
                        // Try streaming as fallback
                        if (!this.isStreaming && this.options.digitalObjectId) {
                            message = 'Attempting streaming playback...';
                            this.switchToStreaming();
                        }
                        break;
                    case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED:
                        message = 'Format not supported';
                        // Try streaming as fallback
                        if (!this.isStreaming && this.options.digitalObjectId) {
                            message = 'Switching to streaming playback...';
                            this.switchToStreaming();
                        }
                        break;
                }
            }

            this.updateStatus(`<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${message}</span>`);

            if (this.options.onError) {
                this.options.onError(message, error);
            }
        }

        /**
         * Handle load start
         */
        handleLoadStart() {
            this.updateStatus('<span class="text-info"><i class="fas fa-spinner fa-spin"></i> Loading...</span>');
        }

        /**
         * Handle can play
         */
        handleCanPlay() {
            this.updateStatus('<span class="text-success"><i class="fas fa-check"></i> Ready</span>');

            if (this.options.onReady) {
                this.options.onReady(this);
            }

            if (this.options.autoplay) {
                this.play();
            }
        }

        /**
         * Handle play
         */
        handlePlay() {
            if (this.options.onPlay) {
                this.options.onPlay(this);
            }
        }

        /**
         * Handle pause
         */
        handlePause() {
            if (this.options.onPause) {
                this.options.onPause(this);
            }
        }

        /**
         * Handle ended
         */
        handleEnded() {
            this.updateStatus('<span class="text-muted"><i class="fas fa-flag-checkered"></i> Ended</span>');
        }

        /**
         * Handle buffering state
         */
        handleBuffering(isBuffering) {
            if (isBuffering) {
                this.updateStatus('<span class="text-warning"><i class="fas fa-spinner fa-spin"></i> Buffering...</span>');
            } else {
                this.updateStatus('');
            }
        }

        /**
         * Update status display
         */
        updateStatus(html) {
            const statusEl = this.container.querySelector('[id^="player-status-"]');
            if (statusEl) {
                statusEl.innerHTML = html;
            }
        }

        /**
         * Show notice message
         */
        showNotice(message) {
            let notice = this.container.querySelector('.player-notice');

            if (!notice) {
                notice = document.createElement('div');
                notice.className = 'player-notice alert alert-info py-1 mb-2 small';
                const playerContainer = this.container.querySelector('.player-container');
                if (playerContainer) {
                    playerContainer.parentNode.insertBefore(notice, playerContainer);
                }
            }

            notice.innerHTML = `<i class="fas fa-info-circle me-1"></i> ${message}`;
        }

        /**
         * Play media
         */
        play() {
            if (this.player) {
                this.player.play().catch(e => {
                    console.warn('Autoplay prevented:', e);
                });
            }
        }

        /**
         * Pause media
         */
        pause() {
            if (this.player) {
                this.player.pause();
            }
        }

        /**
         * Toggle play/pause
         */
        toggle() {
            if (this.player) {
                if (this.player.paused) {
                    this.play();
                } else {
                    this.pause();
                }
            }
        }

        /**
         * Cycle playback speed
         */
        cycleSpeed() {
            this.currentSpeedIndex = (this.currentSpeedIndex + 1) % this.speeds.length;
            const speed = this.speeds[this.currentSpeedIndex];

            if (this.player) {
                this.player.playbackRate = speed;
            }

            // Update UI
            const speedDisplay = this.container.querySelector('[id^="speed-display-"]');
            if (speedDisplay) {
                speedDisplay.textContent = speed + 'x';
            }

            return speed;
        }

        /**
         * Set playback speed
         */
        setSpeed(speed) {
            if (this.player) {
                this.player.playbackRate = speed;
            }
        }

        /**
         * Seek to position (seconds)
         */
        seek(seconds) {
            if (this.player) {
                this.player.currentTime = seconds;
            }
        }

        /**
         * Get current time
         */
        getCurrentTime() {
            return this.player ? this.player.currentTime : 0;
        }

        /**
         * Get duration
         */
        getDuration() {
            return this.player ? this.player.duration : 0;
        }

        /**
         * Set volume (0-1)
         */
        setVolume(volume) {
            if (this.player) {
                this.player.volume = Math.max(0, Math.min(1, volume));
            }
        }

        /**
         * Get volume
         */
        getVolume() {
            return this.player ? this.player.volume : 1;
        }

        /**
         * Toggle mute
         */
        toggleMute() {
            if (this.player) {
                this.player.muted = !this.player.muted;
            }
            return this.player ? this.player.muted : false;
        }

        /**
         * Enter fullscreen (video only)
         */
        fullscreen() {
            if (this.player && this.player.requestFullscreen) {
                this.player.requestFullscreen();
            } else if (this.player && this.player.webkitRequestFullscreen) {
                this.player.webkitRequestFullscreen();
            }
        }

        /**
         * Destroy player
         */
        destroy() {
            if (this.player) {
                this.player.pause();
                this.player.src = '';
                this.player.load();
            }
        }
    }

    // Global helper functions for inline event handlers
    window.togglePlaybackSpeed = function(id) {
        const container = document.getElementById('media-player-' + id);
        if (container && container._ahgPlayer) {
            container._ahgPlayer.cycleSpeed();
        } else {
            // Fallback for non-class players
            const player = document.getElementById('player-' + id);
            if (player) {
                const speeds = [0.5, 0.75, 1, 1.25, 1.5, 2];
                let currentSpeed = player.playbackRate;
                let idx = speeds.indexOf(currentSpeed);
                idx = (idx + 1) % speeds.length;
                player.playbackRate = speeds[idx];

                const display = document.getElementById('speed-display-' + id);
                if (display) {
                    display.textContent = speeds[idx] + 'x';
                }
            }
        }
    };

    window.downloadOriginal = function(id) {
        window.location.href = '/media/download/' + id;
    };

    // Auto-initialize players on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Find all media player containers
        const containers = document.querySelectorAll('.ahg-media-player');

        containers.forEach(function(container) {
            const doId = container.dataset.doId;
            if (!doId) return;

            const player = container.querySelector('video, audio');
            if (!player) return;

            const isVideo = player.tagName.toLowerCase() === 'video';
            const source = player.querySelector('source');

            // Check if player had an error and needs fallback
            player.addEventListener('error', function() {
                // Only try streaming once
                if (source && !source.dataset.triedStreaming) {
                    source.dataset.triedStreaming = 'true';
                    source.src = '/media/stream/' + doId;
                    source.type = isVideo ? 'video/mp4' : 'audio/mpeg';
                    player.load();
                }
            });
        });
    });

    // Export to global
    window.AhgMediaPlayer = AhgMediaPlayer;

})(window);