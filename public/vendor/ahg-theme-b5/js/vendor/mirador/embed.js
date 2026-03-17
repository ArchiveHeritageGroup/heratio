/**
 * Mirador Embed - Simple IIIF Viewer Embedding
 * Usage: MiradorEmbed.init('#container', 'https://example.com/manifest.json');
 */
const MiradorEmbed = {
    defaultConfig: {
        window: {
            allowClose: false,
            allowMaximize: false,
            allowFullscreen: true,
            sideBarOpenByDefault: false
        },
        workspace: {
            showZoomControls: true,
            type: 'single'
        },
        workspaceControlPanel: {
            enabled: false
        }
    },

    init: function(containerId, manifestUrl, options = {}) {
        const config = {
            id: containerId.replace('#', ''),
            windows: [{
                manifestId: manifestUrl,
                canvasIndex: options.canvasIndex || 0
            }],
            ...this.defaultConfig,
            ...options
        };

        return Mirador.viewer(config);
    },

    compare: function(containerId, manifestUrls, options = {}) {
        const windows = manifestUrls.map(url => ({
            manifestId: url,
            canvasIndex: 0
        }));

        const config = {
            id: containerId.replace('#', ''),
            windows: windows,
            window: {
                allowClose: true,
                allowMaximize: true,
                allowFullscreen: true,
                sideBarOpenByDefault: false
            },
            workspace: {
                showZoomControls: true,
                type: 'mosaic',
                allowNewWindows: true
            },
            workspaceControlPanel: {
                enabled: true
            },
            ...options
        };

        return Mirador.viewer(config);
    }
};

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MiradorEmbed;
}
