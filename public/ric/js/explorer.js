/**
 * RiC Explorer - Comprehensive Standalone Application
 * 2D/3D Graph, SPARQL, Semantic Search, Provenance, Statistics
 */
(function () {
    'use strict';

    // ========== CONFIG ==========
    var SPARQL_ENDPOINT = '/sparql/';
    var DATA_ENDPOINT = '/admin/ric/data';
    var AUTOCOMPLETE_ENDPOINT = '/admin/ric/autocomplete';
    var SEMANTIC_API = '/api/ric/search';
    var ADMIN_STATS = '/admin/ric/ajax-dashboard';

    var COLORS = {
        RecordSet: '#17a2b8', Record: '#45b7d1', RecordPart: '#96ceb4',
        RecordResource: '#0dcaf0',
        Person: '#dc3545', Family: '#dc3545',
        CorporateBody: '#ffc107',
        Production: '#6f42c1', Accumulation: '#6f42c1', Activity: '#6f42c1', Function: '#6f42c1',
        Place: '#fd7e14', Thing: '#20c997', Concept: '#20c997',
        Instantiation: '#6c757d',
        Mandate: '#e83e8c', Rule: '#e83e8c', Mechanism: '#ab47bc',
        Date: '#795548',
        AuthorityRecord: '#00bcd4', FindingAid: '#009688',
        default: '#6c757d'
    };

    function getColor(type) { return COLORS[type] || COLORS.default; }
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    // ========== STATE ==========
    var graphData = null;
    var cy2d = null;
    var graph3d = null;
    var fsGraph = null;
    var currentView = '2d';
    var showEdgeLabels = true;
    var autoRotate3d = false;
    var autoRotateInterval = null;
    var sparqlResultData = null;

    // ========== INIT ==========
    function init() {
        var params = new URLSearchParams(window.location.search);
        var focusId = params.get('focus') || params.get('id');

        if (focusId) {
            loadEntityGraph(focusId);
        } else {
            loadOverviewGraph();
        }

        // Enter key for search inputs
        ['graphSearch', 'globalSearch', 'entityIdInput', 'fsSearch', 'semanticQuery', 'provenanceId'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); el.nextElementSibling.click(); } });
        });

        // Load stats when tab shown
        document.querySelectorAll('#mainTabs .nav-link').forEach(function(tab) {
            tab.addEventListener('shown.bs.tab', function(e) {
                if (e.target.getAttribute('href') === '#tab-stats') loadStatistics();
                if (e.target.getAttribute('href') === '#tab-categories') loadCategoryCounts();
            });
        });

        // Setup semantic search suggestions
        setupSemanticSuggestions();

        // Setup autocomplete on entity and provenance inputs
        setupAutocomplete('entityIdInput', 'entityAutocomplete', function(item) {
            document.getElementById('entityIdInput').value = item.id;
            loadEntityById(String(item.id));
        });
        setupAutocomplete('provenanceId', 'provenanceAutocomplete', function(item) {
            document.getElementById('provenanceId').value = item.id;
            window.loadProvenance();
        });
    }

    // ========== GRAPH DATA LOADING ==========
    function loadEntityGraph(id) {
        showLoading(true);
        fetch(DATA_ENDPOINT + '?id=' + encodeURIComponent(id) + '&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.graphData && data.graphData.nodes && data.graphData.nodes.length > 0) {
                    graphData = data.graphData;
                    renderCurrentView();
                    updateStats();
                    buildTypeFilters();
                } else {
                    showEmptyState('No RiC data found for this entity.');
                }
            })
            .catch(function(err) { showEmptyState('Error loading data: ' + err.message); });
    }

    function loadOverviewGraph() {
        showLoading(true);
        fetch(DATA_ENDPOINT + '?id=overview&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.graphData && data.graphData.nodes && data.graphData.nodes.length > 0) {
                    graphData = data.graphData;
                    renderCurrentView();
                    updateStats();
                    buildTypeFilters();
                } else {
                    showEmptyState('No RiC data available. Use the <a href="/admin/ric" class="text-info">Admin Dashboard</a> to sync records.');
                }
            })
            .catch(function() { showEmptyState('Could not connect to data endpoint.'); });
    }

    // ========== 2D GRAPH (Cytoscape) ==========
    function init2DGraph(container, data) {
        if (!data || !data.nodes) return null;
        container.innerHTML = '';

        var elements = [];
        var nodeIndex = {};
        data.nodes.forEach(function(node) {
            if (nodeIndex[node.id]) return;
            nodeIndex[node.id] = true;
            elements.push({
                data: {
                    id: node.id,
                    label: (node.label || 'Unknown').substring(0, 35),
                    fullLabel: node.label || 'Unknown',
                    type: node.type || 'Unknown',
                    color: getColor(node.type),
                    atomId: node.atomId || extractAtomId(node.id),
                    atomUrl: node.atomUrl || null
                }
            });
        });
        (data.edges || []).forEach(function(edge, idx) {
            if (nodeIndex[edge.source] && nodeIndex[edge.target]) {
                elements.push({
                    data: { id: 'e' + idx, source: edge.source, target: edge.target, label: edge.label || '' }
                });
            }
        });

        try {
            var cy = cytoscape({
                container: container,
                elements: elements,
                style: [
                    { selector: 'node', style: {
                        'background-color': 'data(color)', 'label': 'data(label)',
                        'font-size': '9px', 'color': '#e0e0e0', 'text-valign': 'bottom', 'text-margin-y': '4px',
                        'width': '20px', 'height': '20px', 'text-wrap': 'ellipsis', 'text-max-width': '100px',
                        'text-outline-color': '#1a1a2e', 'text-outline-width': '2px',
                        'border-width': '2px', 'border-color': 'data(color)'
                    }},
                    { selector: 'node:selected', style: { 'border-width': '4px', 'border-color': '#fff', 'width': '30px', 'height': '30px' }},
                    { selector: 'edge', style: {
                        'width': 1.5, 'line-color': '#555', 'target-arrow-color': '#777',
                        'target-arrow-shape': 'triangle', 'curve-style': 'bezier',
                        'label': showEdgeLabels ? 'data(label)' : '', 'font-size': '7px', 'color': '#888',
                        'text-rotation': 'autorotate', 'text-outline-color': '#1a1a2e', 'text-outline-width': '1px'
                    }},
                    { selector: 'edge:selected', style: { 'width': 3, 'line-color': '#aaa' }},
                    { selector: '.highlighted', style: { 'border-width': '3px', 'border-color': '#fff', 'opacity': 1 }},
                    { selector: '.dimmed', style: { 'opacity': 0.15 }}
                ],
                layout: { name: 'cose', animate: false, padding: 30, nodeRepulsion: function() { return 8000; }, idealEdgeLength: function() { return 100; } },
                userZoomingEnabled: true, userPanningEnabled: true, minZoom: 0.1, maxZoom: 5
            });

            cy.on('tap', 'node', function(evt) { showNodeInfo(evt.target.data()); });
            cy.on('tap', function(evt) { if (evt.target === cy) { closeNodePanel(); hideContextMenu(); } });
            cy.on('cxttap', 'node', function(evt) { showContextMenu(evt, evt.target.data()); });
            cy.on('cxttap', function(evt) { if (evt.target === cy) hideContextMenu(); });
            container.addEventListener('contextmenu', function(e) { e.preventDefault(); });
            return cy;
        } catch(e) { console.error('2D graph error:', e); return null; }
    }

    // ========== 3D GRAPH (3d-force-graph) ==========
    function init3DGraph(container, data) {
        if (!data || !data.nodes || typeof ForceGraph3D === 'undefined') return null;
        container.innerHTML = '';

        var nodes = data.nodes.map(function(n) {
            return { id: n.id, name: n.label || 'Unknown', color: getColor(n.type), type: n.type, val: 1, atomId: n.atomId || null, atomUrl: n.atomUrl || null };
        });
        var nodeIds = {};
        nodes.forEach(function(n) { nodeIds[n.id] = true; });
        var links = (data.edges || []).filter(function(e) {
            return nodeIds[e.source] && nodeIds[e.target];
        }).map(function(e) {
            return { source: e.source, target: e.target, label: e.label || '' };
        });

        try {
            var w = container.clientWidth || 800;
            var h = container.clientHeight || 500;

            var g = ForceGraph3D()(container)
                .graphData({ nodes: nodes, links: links })
                .nodeColor('color')
                .nodeVal('val')
                .nodeLabel('name')
                .nodeThreeObject(function(node) {
                    var sprite = new SpriteText(node.name.length > 20 ? node.name.substring(0, 20) + '...' : node.name);
                    sprite.color = '#ffffff';
                    sprite.textHeight = 3;
                    sprite.backgroundColor = node.color;
                    sprite.padding = 0.5;
                    sprite.borderRadius = 1;
                    return sprite;
                })
                .nodeThreeObjectExtend(false)
                .linkDirectionalParticles(1)
                .linkDirectionalParticleSpeed(0.005)
                .backgroundColor('#1a1a2e')
                .width(w)
                .height(h)
                .cooldownTime(3000)
                .onEngineStop(function() {
                    // Fit camera once after simulation stabilizes (skip if auto-rotating)
                    if (!autoRotate3d) g.zoomToFit(400, 50);
                })
                .onNodeClick(function(node) { showNodeInfo({ id: node.id, fullLabel: node.name, type: node.type, atomId: node.atomId || extractAtomId(node.id), atomUrl: node.atomUrl }); })
                .onNodeRightClick(function(node, event) {
                    event.preventDefault();
                    var data = { id: node.id, fullLabel: node.name, label: node.name, type: node.type, atomId: node.atomId || extractAtomId(node.id), atomUrl: node.atomUrl };
                    showContextMenu3D(event, data);
                });

            // Prevent page scroll over 3D canvas
            container.addEventListener('wheel', function(e) { e.preventDefault(); }, { passive: false });

            // Start auto-rotate if enabled
            if (autoRotate3d) startAutoRotate(g);

            return g;
        } catch(e) { console.error('3D graph error:', e); return null; }
    }

    // ========== VIEW MANAGEMENT ==========
    function renderCurrentView() {
        showLoading(false);
        if (currentView === '2d') {
            document.getElementById('graph2d').style.display = 'block';
            document.getElementById('graph3d').style.display = 'none';
            if (cy2d) { try { cy2d.destroy(); } catch(e) {} }
            cy2d = init2DGraph(document.getElementById('graph2d'), graphData);
        } else {
            document.getElementById('graph2d').style.display = 'none';
            document.getElementById('graph3d').style.display = 'block';
            stopAutoRotate();
            if (graph3d) { try { graph3d._destructor(); } catch(e) {} graph3d = null; }
            document.getElementById('graph3d').innerHTML = '';
            setTimeout(function() { graph3d = init3DGraph(document.getElementById('graph3d'), graphData); }, 100);
        }
    }

    window.switchView = function(view) {
        currentView = view;
        document.getElementById('btn2d').classList.toggle('active', view === '2d');
        document.getElementById('btn3d').classList.toggle('active', view === '3d');
        if (graphData) renderCurrentView();
    };

    window.openFullscreen = function() {
        if (!graphData) return;
        var overlay = document.getElementById('fullscreenOverlay');
        var container = document.getElementById('fullscreenGraph');
        overlay.style.display = 'block';
        container.innerHTML = '';
        document.getElementById('fs-btn2d').classList.toggle('active', currentView === '2d');
        document.getElementById('fs-btn3d').classList.toggle('active', currentView === '3d');
        setTimeout(function() {
            if (currentView === '2d') {
                fsGraph = init2DGraph(container, graphData);
            } else {
                fsGraph = init3DGraph(container, graphData);
            }
        }, 200);
    };

    window.closeFullscreen = function() {
        document.getElementById('fullscreenOverlay').style.display = 'none';
        if (fsGraph) {
            try { if (fsGraph.destroy) fsGraph.destroy(); } catch(e) {}
            try { if (fsGraph._destructor) fsGraph._destructor(); } catch(e) {}
            fsGraph = null;
        }
        document.getElementById('fullscreenGraph').innerHTML = '';
    };

    window.switchFsView = function(view) {
        currentView = view;
        document.getElementById('fs-btn2d').classList.toggle('active', view === '2d');
        document.getElementById('fs-btn3d').classList.toggle('active', view === '3d');
        if (fsGraph) { try { if (fsGraph.destroy) fsGraph.destroy(); } catch(e) {} try { if (fsGraph._destructor) fsGraph._destructor(); } catch(e) {} fsGraph = null; }
        var container = document.getElementById('fullscreenGraph');
        container.innerHTML = '';
        setTimeout(function() {
            fsGraph = (view === '2d') ? init2DGraph(container, graphData) : init3DGraph(container, graphData);
        }, 100);
    };

    window.resetGraphView = function() { if (cy2d) { cy2d.fit(); cy2d.zoom(1); } };
    window.relayoutGraph = function() { if (cy2d) cy2d.layout({ name: 'cose', animate: true, animationDuration: 500, nodeRepulsion: function() { return 8000; }, padding: 30 }).run(); };
    window.toggleEdgeLabels = function() { showEdgeLabels = document.getElementById('chkEdgeLabels').checked; if (cy2d) cy2d.edges().style('label', showEdgeLabels ? 'data(label)' : ''); };
    function startAutoRotate(g) {
        stopAutoRotate();
        var angle = 0;
        var dist = 300;
        autoRotateInterval = setInterval(function() {
            angle += Math.PI / 300;
            g.cameraPosition({ x: dist * Math.sin(angle), z: dist * Math.cos(angle) });
        }, 30);
    }

    function stopAutoRotate() {
        if (autoRotateInterval) { clearInterval(autoRotateInterval); autoRotateInterval = null; }
    }

    window.toggleAutoRotate = function() {
        autoRotate3d = document.getElementById('chkAutoRotate').checked;
        if (autoRotate3d && graph3d) {
            startAutoRotate(graph3d);
        } else {
            stopAutoRotate();
        }
    };
    window.closeNodePanel = function() { document.getElementById('nodePanel').style.display = 'none'; };

    // ========== NODE INFO ==========
    function showNodeInfo(data) {
        var panel = document.getElementById('nodePanel');
        var content = document.getElementById('nodePanelContent');
        var color = getColor(data.type);
        var html = '<span class="badge mb-2" style="background:' + color + '">' + esc(data.type || 'Unknown') + '</span>';
        html += '<h6>' + esc(data.fullLabel || data.label || 'Unknown') + '</h6>';

        if (data.atomUrl) {
            html += '<a href="' + esc(data.atomUrl) + '" target="_blank" class="btn btn-sm btn-primary w-100 mb-2"><i class="fas fa-external-link-alt me-1"></i>View in AtoM</a>';
        } else if (data.atomId) {
            html += '<a href="/index.php/' + data.atomId + '" target="_blank" class="btn btn-sm btn-primary w-100 mb-2"><i class="fas fa-external-link-alt me-1"></i>View in AtoM</a>';
        }
        html += '<button class="btn btn-sm btn-outline-info w-100 mb-2" onclick="loadEntityById(\'' + esc(data.atomId || '') + '\')"><i class="fas fa-expand me-1"></i>Expand Relations</button>';
        html += '<button class="btn btn-sm btn-outline-warning w-100 mb-2" onclick="clearHighlight()"><i class="fas fa-highlighter me-1"></i>Clear Highlight</button>';

        // Show neighbors
        if (cy2d) {
            var node = cy2d.getElementById(data.id);
            if (node.length) {
                var neighbors = node.neighborhood('node');
                if (neighbors.length > 0) {
                    html += '<hr><small class="text-muted fw-bold">Connections (' + neighbors.length + ')</small>';
                    html += '<ul class="list-unstyled small mt-1 mb-0">';
                    neighbors.slice(0, 15).forEach(function(n) {
                        html += '<li class="mb-1"><span class="ldot" style="background:' + getColor(n.data('type')) + ';width:10px;height:10px;"></span>' + esc(n.data('label')) + '</li>';
                    });
                    if (neighbors.length > 15) html += '<li class="text-muted">... and ' + (neighbors.length - 15) + ' more</li>';
                    html += '</ul>';
                }
            }
        }

        content.innerHTML = html;
        panel.style.display = 'block';
    }

    // ========== CONTEXT MENU ==========
    function showContextMenu(evt, nodeData) {
        hideContextMenu();
        var pos = evt.renderedPosition || evt.position;
        var container = evt.cy.container();
        var rect = container.getBoundingClientRect();
        var x = rect.left + pos.x;
        var y = rect.top + pos.y;

        var menu = document.createElement('div');
        menu.id = 'ricContextMenu';
        menu.style.cssText = 'position:fixed;z-index:10000;background:#2a2a3e;border:1px solid #444;border-radius:6px;padding:4px 0;min-width:200px;box-shadow:0 4px 12px rgba(0,0,0,0.4);font-size:13px;';
        menu.style.left = x + 'px';
        menu.style.top = y + 'px';

        var color = getColor(nodeData.type);
        var atomId = nodeData.atomId || extractAtomId(nodeData.id);
        var atomUrl = nodeData.atomUrl;

        // Header
        menu.innerHTML = '<div style="padding:6px 12px;border-bottom:1px solid #444;margin-bottom:2px;">'
            + '<span class="badge" style="background:' + color + ';font-size:10px;">' + esc(nodeData.type || 'Unknown') + '</span> '
            + '<strong style="color:#e0e0e0;font-size:12px;">' + esc((nodeData.fullLabel || nodeData.label || 'Unknown').substring(0, 40)) + '</strong></div>';

        var items = [];

        // View in AtoM
        if (atomUrl || atomId) {
            var viewUrl = atomUrl || ('/index.php/' + atomId);
            items.push({ icon: 'fa-external-link-alt', label: 'View in AtoM', color: '#17a2b8',
                action: function() { window.open(viewUrl, '_blank'); } });
        }

        // Expand relations
        items.push({ icon: 'fa-expand-arrows-alt', label: 'Expand Relations', color: '#28a745',
            action: function() { loadEntityById(atomId || ''); } });

        // Highlight connections
        items.push({ icon: 'fa-highlighter', label: 'Highlight Connections', color: '#ffc107',
            action: function() { highlightConnections(nodeData.id); } });

        // Center on node
        items.push({ icon: 'fa-crosshairs', label: 'Center on Node', color: '#6c757d',
            action: function() { if (cy2d) { var n = cy2d.getElementById(nodeData.id); if (n.length) { cy2d.center(n); cy2d.zoom(2); n.select(); } } } });

        // Separator + secondary actions
        items.push({ separator: true });

        // Copy URI
        items.push({ icon: 'fa-link', label: 'Copy RiC URI', color: '#6c757d',
            action: function() { copyToClipboard(nodeData.id); } });

        // Copy label
        items.push({ icon: 'fa-copy', label: 'Copy Label', color: '#6c757d',
            action: function() { copyToClipboard(nodeData.fullLabel || nodeData.label || ''); } });

        // View provenance
        if (nodeData.type === 'RecordSet' || nodeData.type === 'Record') {
            items.push({ icon: 'fa-history', label: 'View Provenance', color: '#fd7e14',
                action: function() {
                    document.getElementById('provenanceId').value = atomId || '';
                    var tab = document.querySelector('#mainTabs a[href="#tab-provenance"]');
                    if (tab) new bootstrap.Tab(tab).show();
                    window.loadProvenance();
                } });
        }

        // Query in SPARQL
        items.push({ icon: 'fa-code', label: 'Query in SPARQL', color: '#6f42c1',
            action: function() {
                var q = 'SELECT ?predicate ?object WHERE {\n  <' + nodeData.id + '> ?predicate ?object .\n} LIMIT 50';
                document.getElementById('sparqlEditor').value = q;
                var tab = document.querySelector('#mainTabs a[href="#tab-sparql"]');
                if (tab) new bootstrap.Tab(tab).show();
            } });

        // Show details panel
        items.push({ icon: 'fa-info-circle', label: 'Show Details', color: '#17a2b8',
            action: function() { showNodeInfo(nodeData); } });

        // Build menu items
        items.forEach(function(item) {
            if (item.separator) {
                var sep = document.createElement('div');
                sep.style.cssText = 'border-top:1px solid #444;margin:2px 0;';
                menu.appendChild(sep);
                return;
            }
            var el = document.createElement('div');
            el.style.cssText = 'padding:6px 12px;cursor:pointer;color:#e0e0e0;display:flex;align-items:center;gap:8px;';
            el.innerHTML = '<i class="fas ' + item.icon + '" style="width:16px;text-align:center;color:' + item.color + ';"></i><span>' + item.label + '</span>';
            el.addEventListener('mouseenter', function() { this.style.background = '#3a3a5e'; });
            el.addEventListener('mouseleave', function() { this.style.background = 'none'; });
            el.addEventListener('click', function() { hideContextMenu(); item.action(); });
            menu.appendChild(el);
        });

        document.body.appendChild(menu);

        // Adjust if off-screen
        var menuRect = menu.getBoundingClientRect();
        if (menuRect.right > window.innerWidth) menu.style.left = (window.innerWidth - menuRect.width - 5) + 'px';
        if (menuRect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - menuRect.height - 5) + 'px';

        // Close on click outside
        setTimeout(function() {
            document.addEventListener('click', hideContextMenuHandler);
            document.addEventListener('contextmenu', hideContextMenuHandler);
        }, 50);
    }

    function showContextMenu3D(mouseEvent, nodeData) {
        hideContextMenu();
        // Reuse the same menu builder but with mouse coordinates
        var fakeEvt = { renderedPosition: { x: 0, y: 0 }, cy: { container: function() { return document.createElement('div'); } } };
        // Build menu directly at mouse position
        var menu = document.createElement('div');
        menu.id = 'ricContextMenu';
        menu.style.cssText = 'position:fixed;z-index:10000;background:#2a2a3e;border:1px solid #444;border-radius:6px;padding:4px 0;min-width:200px;box-shadow:0 4px 12px rgba(0,0,0,0.4);font-size:13px;';
        menu.style.left = mouseEvent.clientX + 'px';
        menu.style.top = mouseEvent.clientY + 'px';

        var color = getColor(nodeData.type);
        var atomId = nodeData.atomId;
        var atomUrl = nodeData.atomUrl;

        menu.innerHTML = '<div style="padding:6px 12px;border-bottom:1px solid #444;margin-bottom:2px;">'
            + '<span class="badge" style="background:' + color + ';font-size:10px;">' + esc(nodeData.type || 'Unknown') + '</span> '
            + '<strong style="color:#e0e0e0;font-size:12px;">' + esc((nodeData.fullLabel || 'Unknown').substring(0, 40)) + '</strong></div>';

        var items = [];
        if (atomUrl || atomId) {
            var viewUrl = atomUrl || ('/index.php/' + atomId);
            items.push({ icon: 'fa-external-link-alt', label: 'View in AtoM', color: '#17a2b8',
                action: function() { window.open(viewUrl, '_blank'); } });
        }
        items.push({ icon: 'fa-expand-arrows-alt', label: 'Expand Relations', color: '#28a745',
            action: function() { loadEntityById(atomId || ''); } });
        items.push({ separator: true });
        items.push({ icon: 'fa-link', label: 'Copy RiC URI', color: '#6c757d',
            action: function() { copyToClipboard(nodeData.id); } });
        items.push({ icon: 'fa-copy', label: 'Copy Label', color: '#6c757d',
            action: function() { copyToClipboard(nodeData.fullLabel || ''); } });
        items.push({ icon: 'fa-code', label: 'Query in SPARQL', color: '#6f42c1',
            action: function() {
                document.getElementById('sparqlEditor').value = 'SELECT ?predicate ?object WHERE {\n  <' + nodeData.id + '> ?predicate ?object .\n} LIMIT 50';
                var tab = document.querySelector('#mainTabs a[href="#tab-sparql"]');
                if (tab) new bootstrap.Tab(tab).show();
            } });
        items.push({ icon: 'fa-info-circle', label: 'Show Details', color: '#17a2b8',
            action: function() { showNodeInfo(nodeData); } });

        items.forEach(function(item) {
            if (item.separator) { var sep = document.createElement('div'); sep.style.cssText = 'border-top:1px solid #444;margin:2px 0;'; menu.appendChild(sep); return; }
            var el = document.createElement('div');
            el.style.cssText = 'padding:6px 12px;cursor:pointer;color:#e0e0e0;display:flex;align-items:center;gap:8px;';
            el.innerHTML = '<i class="fas ' + item.icon + '" style="width:16px;text-align:center;color:' + item.color + ';"></i><span>' + item.label + '</span>';
            el.addEventListener('mouseenter', function() { this.style.background = '#3a3a5e'; });
            el.addEventListener('mouseleave', function() { this.style.background = 'none'; });
            el.addEventListener('click', function() { hideContextMenu(); item.action(); });
            menu.appendChild(el);
        });

        document.body.appendChild(menu);
        var menuRect = menu.getBoundingClientRect();
        if (menuRect.right > window.innerWidth) menu.style.left = (window.innerWidth - menuRect.width - 5) + 'px';
        if (menuRect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - menuRect.height - 5) + 'px';
        setTimeout(function() { document.addEventListener('click', hideContextMenuHandler); document.addEventListener('contextmenu', hideContextMenuHandler); }, 50);
    }

    function hideContextMenuHandler(e) {
        var menu = document.getElementById('ricContextMenu');
        if (menu && !menu.contains(e.target)) hideContextMenu();
    }

    function hideContextMenu() {
        var menu = document.getElementById('ricContextMenu');
        if (menu) menu.remove();
        document.removeEventListener('click', hideContextMenuHandler);
        document.removeEventListener('contextmenu', hideContextMenuHandler);
    }

    function highlightConnections(nodeId) {
        if (!cy2d) return;
        cy2d.elements().removeClass('highlighted dimmed');
        var node = cy2d.getElementById(nodeId);
        if (!node.length) return;
        var neighborhood = node.closedNeighborhood();
        cy2d.elements().not(neighborhood).addClass('dimmed');
        neighborhood.addClass('highlighted');
    }

    window.clearHighlight = function() {
        if (cy2d) cy2d.elements().removeClass('highlighted dimmed');
    };

    function copyToClipboard(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() { showToast('Copied to clipboard'); });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('Copied to clipboard');
        }
    }

    function showToast(msg) {
        var toast = document.createElement('div');
        toast.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#28a745;color:#fff;padding:8px 16px;border-radius:6px;font-size:13px;z-index:10001;box-shadow:0 2px 8px rgba(0,0,0,0.3);';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function() { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(function() { toast.remove(); }, 300); }, 1500);
    }

    // ========== SEARCH ==========
    window.searchInGraph = function() {
        var q = document.getElementById('graphSearch').value.trim().toLowerCase();
        if (!q || !cy2d) return;
        var results = document.getElementById('graphSearchResults');
        var found = [];
        cy2d.nodes().forEach(function(n) {
            if ((n.data('fullLabel') || n.data('label') || '').toLowerCase().indexOf(q) !== -1) found.push(n);
        });
        if (found.length === 0) { results.innerHTML = '<small class="text-muted">No matches found.</small>'; return; }
        var html = '<div class="list-group list-group-flush">';
        found.slice(0, 20).forEach(function(n) {
            html += '<div class="list-group-item" onclick="focusGraphNode(\'' + n.id().replace(/'/g, "\\'") + '\')"><span class="ldot" style="background:' + getColor(n.data('type')) + ';width:10px;height:10px;"></span><small>' + esc(n.data('label')) + '</small></div>';
        });
        html += '</div>';
        results.innerHTML = html;
    };

    window.focusGraphNode = function(nodeId) {
        if (!cy2d) return;
        var node = cy2d.getElementById(nodeId);
        if (node.length) { cy2d.center(node); cy2d.zoom(2); node.select(); showNodeInfo(node.data()); }
    };

    window.searchInFsGraph = function() {
        var q = document.getElementById('fsSearch').value.trim().toLowerCase();
        if (!q || !fsGraph) return;
        if (fsGraph.nodes) { // cytoscape
            fsGraph.nodes().forEach(function(n) {
                if ((n.data('fullLabel') || '').toLowerCase().indexOf(q) !== -1) { fsGraph.center(n); fsGraph.zoom(2); n.select(); return; }
            });
        }
    };

    window.globalSearchEntities = function() {
        var q = document.getElementById('globalSearch').value.trim();
        if (q) { document.getElementById('graphSearch').value = q; window.searchInGraph(); }
    };

    window.loadEntityById = function(id) {
        var entityId = id || document.getElementById('entityIdInput').value.trim();
        if (!entityId) return;
        loadEntityGraph(entityId);
        window.history.pushState({}, '', '/ric/?id=' + entityId);
    };

    // ========== TYPE FILTERS ==========
    function buildTypeFilters() {
        if (!graphData || !graphData.nodes) return;
        var types = {};
        graphData.nodes.forEach(function(n) { var t = n.type || 'Unknown'; types[t] = (types[t] || 0) + 1; });
        var container = document.getElementById('typeFilters');
        var html = '';
        Object.keys(types).sort().forEach(function(type) {
            html += '<div class="form-check"><input class="form-check-input" type="checkbox" checked value="' + esc(type) + '" onchange="filterByType()"><label class="form-check-label small"><span class="ldot" style="background:' + getColor(type) + ';width:10px;height:10px;"></span>' + esc(type) + ' (' + types[type] + ')</label></div>';
        });
        container.innerHTML = html;
    }

    window.filterByType = function() {
        if (!cy2d) return;
        var checks = document.querySelectorAll('#typeFilters input[type=checkbox]');
        var visible = {};
        checks.forEach(function(c) { if (c.checked) visible[c.value] = true; });
        cy2d.nodes().forEach(function(n) {
            var show = visible[n.data('type')] || false;
            n.style('display', show ? 'element' : 'none');
        });
    };

    // ========== SPARQL ==========
    window.executeSparql = function() {
        var query = document.getElementById('sparqlEditor').value.trim();
        if (!query) return;
        var resultsCard = document.getElementById('sparqlResultsCard');
        var resultsDiv = document.getElementById('sparqlResults');
        resultsCard.style.display = 'block';
        resultsDiv.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Executing...</div>';

        fetch(SPARQL_ENDPOINT, {
            method: 'POST', body: query,
            headers: { 'Content-Type': 'application/sparql-query', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            sparqlResultData = data;
            if (!data.results || !data.results.bindings || data.results.bindings.length === 0) {
                resultsDiv.innerHTML = '<div class="p-3 text-muted">No results.</div>';
                document.getElementById('sparqlResultCount').textContent = '0';
                return;
            }
            var bindings = data.results.bindings;
            var vars = data.head.vars;
            document.getElementById('sparqlResultCount').textContent = bindings.length;

            var html = '<table class="table table-sm table-striped mb-0"><thead><tr>';
            vars.forEach(function(v) { html += '<th>' + esc(v) + '</th>'; });
            html += '</tr></thead><tbody>';
            bindings.slice(0, 200).forEach(function(row) {
                html += '<tr>';
                vars.forEach(function(v) {
                    var val = row[v] ? row[v].value : '';
                    if (val.startsWith('http')) val = '<a href="' + esc(val) + '" target="_blank" class="text-truncate d-inline-block" style="max-width:200px;">' + esc(val.split('/').pop() || val) + '</a>';
                    else val = esc(val);
                    html += '<td>' + val + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            resultsDiv.innerHTML = html;
        })
        .catch(function(err) {
            resultsDiv.innerHTML = '<div class="p-3 text-danger"><i class="fas fa-exclamation-triangle me-1"></i>' + esc(err.message) + '</div>';
        });
    };

    window.loadSparqlPreset = function() {
        var preset = document.getElementById('sparqlPresets').value;
        var editor = document.getElementById('sparqlEditor');
        var presets = {
            all_types: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?type (COUNT(?entity) AS ?count) WHERE {\n  ?entity a ?type .\n  FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#"))\n}\nGROUP BY ?type\nORDER BY DESC(?count)',
            creators: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?creator ?creatorLabel ?record ?recordLabel WHERE {\n  ?record rico:hasCreator ?creator .\n  OPTIONAL { ?creator rico:title ?creatorLabel }\n  OPTIONAL { ?record rico:title ?recordLabel }\n}\nLIMIT 50',
            places: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?place ?placeLabel ?record ?recordLabel WHERE {\n  ?record rico:hasOrHadPlaceRelation ?pr .\n  ?pr rico:hasOrHadPlace ?place .\n  OPTIONAL { ?place rico:title ?placeLabel }\n  OPTIONAL { ?record rico:title ?recordLabel }\n}\nLIMIT 50',
            recent: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\nPREFIX xsd: <http://www.w3.org/2001/XMLSchema#>\n\nSELECT ?entity ?type ?label WHERE {\n  ?entity a ?type .\n  OPTIONAL { ?entity rico:title ?label }\n  FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#"))\n}\nORDER BY DESC(?entity)\nLIMIT 25',
            mandates_rules: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?entity ?type ?label WHERE {\n  { ?entity a rico:Mandate } UNION { ?entity a rico:Rule } UNION { ?entity a rico:Mechanism }\n  ?entity a ?type .\n  OPTIONAL { ?entity rico:title ?label }\n  FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#"))\n}\nLIMIT 50',
            creation_dates: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?record ?recordLabel ?date ?dateValue WHERE {\n  ?record rico:hasCreationDate ?date .\n  OPTIONAL { ?record rico:title ?recordLabel }\n  OPTIONAL { ?date rico:normalizedDateValue ?dateValue }\n}\nLIMIT 50',
            holders: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?record ?recordLabel ?holder ?holderLabel WHERE {\n  ?record rico:hasOrHadHolder ?holder .\n  OPTIONAL { ?record rico:title ?recordLabel }\n  OPTIONAL { ?holder rico:title ?holderLabel }\n}\nLIMIT 50',
            provenance_chains: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?record ?recordLabel ?agent ?agentLabel WHERE {\n  ?agent rico:hasProvenanceOf ?record .\n  OPTIONAL { ?record rico:title ?recordLabel }\n  OPTIONAL { ?agent rico:title ?agentLabel }\n}\nLIMIT 50',
            equivalences: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\nPREFIX owl: <http://www.w3.org/2002/07/owl#>\n\nSELECT ?entity ?label ?equivalent WHERE {\n  { ?entity rico:isEquivalentTo ?equivalent } UNION { ?entity owl:sameAs ?equivalent }\n  OPTIONAL { ?entity rico:title ?label }\n}\nLIMIT 50',
            finding_aids: 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n\nSELECT ?findingAid ?label ?described WHERE {\n  ?findingAid a rico:FindingAid .\n  OPTIONAL { ?findingAid rico:title ?label }\n  OPTIONAL { ?findingAid rico:describesOrDescribed ?described }\n}\nLIMIT 50'
        };
        if (presets[preset]) editor.value = presets[preset];
    };

    window.sparqlToGraph = function() {
        if (!sparqlResultData || !sparqlResultData.results) return;
        // Try to build graph from SPARQL results
        var nodes = {}, edges = [];
        sparqlResultData.results.bindings.forEach(function(row) {
            var vars = Object.keys(row);
            vars.forEach(function(v) {
                if (row[v] && row[v].type === 'uri') {
                    var uri = row[v].value;
                    if (!nodes[uri]) nodes[uri] = { id: uri, label: uri.split('/').pop() || uri, type: 'Unknown' };
                }
            });
            // Try to find subject-object pairs
            if (vars.length >= 2 && row[vars[0]] && row[vars[1]] && row[vars[0]].type === 'uri' && row[vars[1]].type === 'uri') {
                edges.push({ source: row[vars[0]].value, target: row[vars[1]].value });
            }
        });
        graphData = { nodes: Object.values(nodes), edges: edges };
        // Switch to explorer tab
        var tab = document.querySelector('#mainTabs a[href="#tab-explorer"]');
        if (tab) new bootstrap.Tab(tab).show();
        renderCurrentView();
        buildTypeFilters();
        updateStats();
    };

    // ========== SEMANTIC SEARCH ==========
    var semanticSparql = '';
    var suggestionTimeout = null;

    window.runSemanticSearch = function() {
        var q = document.getElementById('semanticQuery').value.trim();
        if (!q) return;
        var results = document.getElementById('semanticResults');
        var header = document.getElementById('semanticResultsHeader');
        var facetsDiv = document.getElementById('semanticFacets');
        results.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Searching...</div>';
        header.style.cssText = 'display:flex!important;';
        document.getElementById('semanticResultCount').textContent = 'Searching...';
        facetsDiv.style.display = 'none';

        fetch(SEMANTIC_API + '/search', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: q })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            // Store generated SPARQL
            if (data.sparql) {
                semanticSparql = data.sparql;
                document.getElementById('semanticSparqlDisplay').textContent = data.sparql;
                document.getElementById('semanticShowSparql').style.display = 'inline-block';
            }

            var count = data.count || (data.results ? data.results.length : 0);
            document.getElementById('semanticResultCount').textContent = count + ' result' + (count !== 1 ? 's' : '') + ' found';

            if (data.results && data.results.length > 0) {
                var html = '';
                data.results.forEach(function(r) {
                    var color = getColor(r.type || 'Unknown');
                    var url = r.atomUrl || (r.id ? '/index.php/informationobject/index/id/' + r.id : '#');
                    html += '<div class="card mb-2"><div class="card-body py-2 px-3">';
                    html += '<div class="d-flex justify-content-between align-items-start">';
                    html += '<a href="' + esc(url) + '" target="_blank" class="fw-bold text-decoration-none">' + esc(r.title || r.label || 'Untitled') + '</a>';
                    html += '<span class="badge ms-2" style="background:' + color + '">' + esc(r.type || '') + '</span>';
                    html += '</div>';
                    // Metadata row
                    var meta = [];
                    if (r.identifier) meta.push('<i class="fas fa-tag me-1"></i>' + esc(r.identifier));
                    if (r.date) meta.push('<i class="fas fa-calendar me-1"></i>' + esc(r.date));
                    if (r.creator) meta.push('<i class="fas fa-user me-1"></i>' + esc(r.creator));
                    if (r.place) meta.push('<i class="fas fa-map-marker-alt me-1"></i>' + esc(r.place));
                    if (meta.length > 0) html += '<div class="text-muted small mt-1">' + meta.join(' <span class="mx-1">|</span> ') + '</div>';
                    if (r.description) html += '<small class="text-muted d-block mt-1">' + esc(r.description.substring(0, 200)) + '</small>';
                    html += '</div></div>';
                });
                results.innerHTML = html;
            } else {
                results.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-folder-open fa-2x mb-2 d-block"></i>No results found. Try a different search term.</div>';
            }

            // Facets
            if (data.facets && Object.keys(data.facets).length > 0) {
                var fhtml = '';
                if (data.facets.type) {
                    fhtml += '<div class="mb-2"><small class="text-muted fw-bold text-uppercase">Types</small><div class="d-flex flex-wrap gap-1 mt-1">';
                    for (var t in data.facets.type) {
                        fhtml += '<span class="badge bg-light text-dark border">' + esc(t) + ' <span class="text-primary">(' + data.facets.type[t] + ')</span></span>';
                    }
                    fhtml += '</div></div>';
                }
                if (data.facets.decade) {
                    fhtml += '<div class="mb-2"><small class="text-muted fw-bold text-uppercase">Decades</small><div class="d-flex flex-wrap gap-1 mt-1">';
                    var decades = Object.keys(data.facets.decade).sort();
                    decades.forEach(function(d) {
                        fhtml += '<span class="badge bg-light text-dark border">' + esc(d) + ' (' + data.facets.decade[d] + ')</span>';
                    });
                    fhtml += '</div></div>';
                }
                facetsDiv.innerHTML = fhtml;
                facetsDiv.style.display = 'block';
            }
        })
        .catch(function() {
            document.getElementById('semanticResultCount').textContent = '';
            results.innerHTML = '<div class="alert alert-warning"><i class="fas fa-info-circle me-1"></i>Semantic search API is not available. Ensure the RiC Semantic Search service is running on port 5001.</div>';
        });
    };

    window.setSemanticQuery = function(q) {
        document.getElementById('semanticQuery').value = q;
        window.runSemanticSearch();
    };

    window.clearSemanticResults = function() {
        document.getElementById('semanticResults').innerHTML = '';
        document.getElementById('semanticResultsHeader').style.cssText = 'display:none!important;';
        document.getElementById('semanticFacets').style.display = 'none';
        document.getElementById('semanticSparqlDisplay').style.display = 'none';
        document.getElementById('semanticQuery').value = '';
        document.getElementById('semanticQuery').focus();
    };

    window.toggleSemanticSparql = function() {
        var display = document.getElementById('semanticSparqlDisplay');
        var btn = document.getElementById('semanticShowSparql');
        if (display.style.display === 'none') {
            display.style.display = 'block';
            btn.innerHTML = '<i class="fas fa-code me-1"></i>Hide SPARQL';
        } else {
            display.style.display = 'none';
            btn.innerHTML = '<i class="fas fa-code me-1"></i>Show SPARQL';
        }
    };

    // Auto-suggest as user types
    function setupSemanticSuggestions() {
        var input = document.getElementById('semanticQuery');
        var dropdown = document.getElementById('semanticSuggestions');
        if (!input || !dropdown) return;

        input.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(suggestionTimeout);
            if (q.length < 2) { dropdown.style.display = 'none'; return; }
            suggestionTimeout = setTimeout(function() {
                fetch(SEMANTIC_API + '/suggest?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.suggestions && data.suggestions.length > 0) {
                            var html = '';
                            data.suggestions.forEach(function(s) {
                                html += '<div class="px-3 py-2 border-bottom" style="cursor:pointer;" onmousedown="document.getElementById(\'semanticQuery\').value=\'' + esc(s.text).replace(/'/g, "\\'") + '\';document.getElementById(\'semanticSuggestions\').style.display=\'none\';runSemanticSearch();">';
                                html += '<span>' + esc(s.text) + '</span>';
                                if (s.type) html += ' <span class="badge bg-light text-primary small ms-1">' + esc(s.type) + '</span>';
                                html += '</div>';
                            });
                            dropdown.innerHTML = html;
                            dropdown.style.display = 'block';
                        } else { dropdown.style.display = 'none'; }
                    })
                    .catch(function() { dropdown.style.display = 'none'; });
            }, 200);
        });

        input.addEventListener('blur', function() { setTimeout(function() { dropdown.style.display = 'none'; }, 200); });
    }

    // ========== AUTOCOMPLETE ==========
    function setupAutocomplete(inputId, dropdownId, onSelect) {
        var input = document.getElementById(inputId);
        var dropdown = document.getElementById(dropdownId);
        if (!input || !dropdown) return;

        var debounceTimer = null;

        input.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(debounceTimer);

            // If user typed a pure number, don't autocomplete (they know the ID)
            if (/^\d+$/.test(q) && q.length < 3) {
                dropdown.style.display = 'none';
                return;
            }
            if (q.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            debounceTimer = setTimeout(function() {
                fetch(AUTOCOMPLETE_ENDPOINT + '?q=' + encodeURIComponent(q) + '&_=' + Date.now())
                    .then(function(r) { return r.json(); })
                    .then(function(items) {
                        if (!items || items.length === 0) {
                            dropdown.innerHTML = '<div class="ac-empty"><i class="fas fa-search me-1"></i>No records found</div>';
                            dropdown.style.display = 'block';
                            return;
                        }
                        var html = '';
                        items.forEach(function(item) {
                            html += '<div class="ac-item" data-id="' + item.id + '">';
                            html += '<div>';
                            html += '<div class="ac-title">' + esc(item.title) + '</div>';
                            var meta = [];
                            if (item.identifier) meta.push(esc(item.identifier));
                            if (item.lod) meta.push(esc(item.lod));
                            if (meta.length) html += '<div class="ac-meta">' + meta.join(' &middot; ') + '</div>';
                            html += '</div>';
                            html += '<div class="ac-id">ID: ' + item.id + '</div>';
                            html += '</div>';
                        });
                        dropdown.innerHTML = html;
                        dropdown.style.display = 'block';

                        // Attach click handlers
                        dropdown.querySelectorAll('.ac-item').forEach(function(el) {
                            el.addEventListener('mousedown', function(e) {
                                e.preventDefault();
                                var selectedId = this.getAttribute('data-id');
                                var selectedItem = items.find(function(i) { return String(i.id) === selectedId; });
                                if (selectedItem) {
                                    dropdown.style.display = 'none';
                                    onSelect(selectedItem);
                                }
                            });
                        });
                    })
                    .catch(function() {
                        dropdown.style.display = 'none';
                    });
            }, 250);
        });

        input.addEventListener('blur', function() {
            setTimeout(function() { dropdown.style.display = 'none'; }, 200);
        });

        input.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        });
    }

    // ========== PROVENANCE ==========
    window.loadProvenance = function() {
        var id = document.getElementById('provenanceId').value.trim();
        if (!id) return;
        var timeline = document.getElementById('provenanceTimeline');
        timeline.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div> Loading provenance...</div>';

        fetch(DATA_ENDPOINT + '?id=' + id + '&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.graphData || !data.graphData.nodes || data.graphData.nodes.length === 0) {
                    timeline.innerHTML = '<div class="alert alert-info">No provenance data found for this record.</div>';
                    return;
                }
                var gd = data.graphData;
                var recordNode = gd.nodes.find(function(n) { return n.type === 'RecordSet' || n.type === 'Record'; }) || gd.nodes[0];

                var html = '<div class="mb-3"><h6><i class="fas fa-file-alt me-2"></i>' + esc(recordNode.label || 'Record ' + id) + '</h6></div>';

                // Build timeline from relationships
                var events = [];
                gd.nodes.forEach(function(node) {
                    if (node.id !== recordNode.id) {
                        var edgeLabel = '';
                        gd.edges.forEach(function(e) {
                            if ((e.source === recordNode.id && e.target === node.id) || (e.target === recordNode.id && e.source === node.id)) {
                                edgeLabel = e.label || '';
                            }
                        });
                        events.push({ label: node.label || 'Unknown', type: node.type, relation: edgeLabel });
                    }
                });

                if (events.length === 0) {
                    html += '<div class="alert alert-info">No provenance chain found.</div>';
                } else {
                    events.forEach(function(evt) {
                        var evtClass = evt.relation.indexOf('create') !== -1 ? 'event-create' : (evt.relation.indexOf('transfer') !== -1 ? 'event-transfer' : '');
                        html += '<div class="timeline-item ' + evtClass + '">';
                        html += '<strong>' + esc(evt.label) + '</strong>';
                        html += '<br><span class="badge" style="background:' + getColor(evt.type) + '">' + esc(evt.type) + '</span>';
                        if (evt.relation) html += ' <small class="text-muted ms-1">' + esc(evt.relation) + '</small>';
                        html += '</div>';
                    });
                }

                html += '<button class="btn btn-sm btn-outline-primary mt-2" onclick="loadEntityById(\'' + id + '\'); var t=document.querySelector(\'#mainTabs a[href=&quot;#tab-explorer&quot;]\'); if(t) new bootstrap.Tab(t).show();"><i class="fas fa-project-diagram me-1"></i>View in Graph</button>';
                timeline.innerHTML = html;
            })
            .catch(function() { timeline.innerHTML = '<div class="alert alert-warning">Error loading provenance data.</div>'; });
    };

    // ========== STATISTICS ==========
    function loadStatistics() {
        fetch(ADMIN_STATS + '?_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.triple_count !== undefined) document.getElementById('stat-triples').textContent = Number(data.triple_count || 0).toLocaleString();
                if (data.entity_count !== undefined) document.getElementById('stat-entities').textContent = Number(data.entity_count || 0).toLocaleString();
                if (data.relation_count !== undefined) document.getElementById('stat-relations').textContent = Number(data.relation_count || 0).toLocaleString();
                if (data.synced_count !== undefined) document.getElementById('stat-synced').textContent = Number(data.synced_count || 0).toLocaleString();

                if (data.type_distribution) {
                    var html = '<table class="table table-sm mb-0">';
                    Object.entries(data.type_distribution).forEach(function(entry) {
                        html += '<tr><td><span class="ldot" style="background:' + getColor(entry[0]) + ';width:10px;height:10px;"></span>' + esc(entry[0]) + '</td><td class="text-end"><strong>' + Number(entry[1]).toLocaleString() + '</strong></td></tr>';
                    });
                    html += '</table>';
                    document.getElementById('statsDistribution').innerHTML = html;
                }
                if (data.relation_types) {
                    var html2 = '<table class="table table-sm mb-0">';
                    Object.entries(data.relation_types).forEach(function(entry) {
                        html2 += '<tr><td><code>' + esc(entry[0]) + '</code></td><td class="text-end"><strong>' + Number(entry[1]).toLocaleString() + '</strong></td></tr>';
                    });
                    html2 += '</table>';
                    document.getElementById('statsRelations').innerHTML = html2;
                }
            })
            .catch(function() {
                // Try SPARQL for basic stats
                var countQuery = 'SELECT (COUNT(*) AS ?count) WHERE { ?s ?p ?o }';
                fetch(SPARQL_ENDPOINT, { method: 'POST', body: countQuery, headers: { 'Content-Type': 'application/sparql-query', 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.results && data.results.bindings[0]) {
                            document.getElementById('stat-triples').textContent = Number(data.results.bindings[0].count.value).toLocaleString();
                        }
                    })
                    .catch(function() {
                        document.getElementById('statsDistribution').innerHTML = '<div class="text-muted">Statistics unavailable. Ensure Fuseki is running.</div>';
                        document.getElementById('statsRelations').innerHTML = '<div class="text-muted">Statistics unavailable.</div>';
                    });
            });
    }

    // ========== ENTITY CATEGORIES ==========
    function loadCategoryCounts() {
        var query = 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\nSELECT ?type (COUNT(?e) AS ?count) WHERE { ?e a ?type . FILTER(STRSTARTS(STR(?type), "https://www.ica.org/standards/RiC/ontology#")) } GROUP BY ?type';
        fetch(SPARQL_ENDPOINT, { method: 'POST', body: query, headers: { 'Content-Type': 'application/sparql-query', 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.results) return;
                var conceptCount = 0;
                data.results.bindings.forEach(function(row) {
                    var type = row.type.value.split('#').pop();
                    var count = parseInt(row.count.value, 10);
                    // Merge Thing into Concept count
                    if (type === 'Thing' || type === 'Concept') {
                        conceptCount += count;
                    }
                    var el = document.getElementById('count-' + type);
                    if (el) el.textContent = Number(count).toLocaleString();
                });
                // Update Concept badge with merged count
                if (conceptCount > 0) {
                    var el = document.getElementById('count-Concept');
                    if (el) el.textContent = Number(conceptCount).toLocaleString();
                }
            })
            .catch(function() {});
    }

    window.browseCategory = function(type) {
        // Build SPARQL with multiple label fallbacks per entity type
        var labelClause;
        if (type === 'Place') {
            // Places use rico:hasPlaceName -> bnode -> rico:textualValue
            labelClause = 'OPTIONAL { ?entity rico:hasPlaceName ?_pn . ?_pn rico:textualValue ?placeName } OPTIONAL { ?entity rico:title ?title }';
        } else if (type === 'Activity') {
            // Activities use rico:hasActivityType for label + rico:name
            labelClause = 'OPTIONAL { ?entity rico:hasActivityType ?actType } OPTIONAL { ?entity rico:name ?aName } OPTIONAL { ?entity rico:title ?title }';
        } else if (type === 'Concept') {
            // Also check rico:Thing which may hold concepts/subjects
            labelClause = 'OPTIONAL { ?entity rico:title ?title } OPTIONAL { ?entity rico:name ?cName }';
        } else {
            labelClause = 'OPTIONAL { ?entity rico:title ?title } OPTIONAL { ?entity rico:name ?rName }';
        }
        // For Concept, also search rico:Thing
        var typeFilter = type === 'Concept'
            ? '{ ?entity a rico:Concept } UNION { ?entity a rico:Thing }'
            : '?entity a rico:' + type + ' .';
        var query = 'PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\nSELECT DISTINCT ?entity ?title ?placeName ?actType ?aName ?cName ?rName WHERE { ' + typeFilter + ' ' + labelClause + ' } LIMIT 50';
        var card = document.getElementById('categoryResults');
        var body = document.getElementById('categoryBody');
        card.style.display = 'block';
        document.getElementById('categoryTitle').textContent = type + ' Entities';
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';

        fetch(SPARQL_ENDPOINT, { method: 'POST', body: query, headers: { 'Content-Type': 'application/sparql-query', 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.results || data.results.bindings.length === 0) {
                    body.innerHTML = '<div class="text-muted">No entities found.</div>';
                    return;
                }
                // Deduplicate by entity URI and pick best label
                var seen = {};
                data.results.bindings.forEach(function(row) {
                    var uri = row.entity.value;
                    if (seen[uri]) return;
                    var label = (row.title && row.title.value) ||
                                (row.placeName && row.placeName.value) ||
                                (row.actType && row.actType.value) ||
                                (row.aName && row.aName.value) ||
                                (row.cName && row.cName.value) ||
                                (row.rName && row.rName.value) ||
                                null;
                    // Fallback: resolve from URI suffix, humanize condition_100 -> "Condition Check 100"
                    if (!label) {
                        var suffix = uri.split('/').pop();
                        var m = suffix.match(/^(\w+?)_(\d+)$/);
                        label = m ? m[1].charAt(0).toUpperCase() + m[1].slice(1) + ' ' + m[2] : suffix;
                    }
                    seen[uri] = { uri: uri, label: label };
                });
                var items = Object.values(seen);
                var html = '<div class="list-group list-group-flush">';
                items.forEach(function(item) {
                    var atomId = extractAtomId(item.uri);
                    html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                    html += '<span><span class="ldot" style="background:' + getColor(type) + ';width:10px;height:10px;"></span>' + esc(item.label) + '</span>';
                    html += '<div>';
                    if (atomId) html += '<a href="/index.php/' + atomId + '" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="View in AtoM"><i class="fas fa-eye"></i></a>';
                    html += '<button class="btn btn-sm btn-outline-info" onclick="loadEntityById(\'' + (atomId || '') + '\')"><i class="fas fa-project-diagram"></i></button>';
                    html += '</div></div>';
                });
                html += '</div>';
                body.innerHTML = html;
            })
            .catch(function() { body.innerHTML = '<div class="text-muted">SPARQL endpoint not available.</div>'; });
    };

    // ========== HELPERS ==========
    function showLoading(show) {
        var overlay = document.getElementById('graphLoading');
        overlay.style.display = show ? 'flex' : 'none';
    }

    function showEmptyState(msg) {
        showLoading(false);
        document.getElementById('graphStatsLabel').textContent = '';
        var overlay = document.getElementById('graphLoading');
        overlay.style.display = 'flex';
        overlay.innerHTML = '<div class="text-center text-white"><i class="fas fa-project-diagram fa-4x mb-3 text-muted"></i><h5>RiC Explorer</h5><p class="text-white-50">' + msg + '</p></div>';
    }

    function updateStats() {
        if (!graphData) return;
        var n = (graphData.nodes || []).length;
        var e = (graphData.edges || []).length;
        document.getElementById('graphStatsLabel').textContent = n + ' entities, ' + e + ' relations';
    }

    function extractAtomId(uri) {
        var match = (uri || '').match(/\/(\d+)$/);
        return match ? match[1] : null;
    }

    // Escape key closes fullscreen
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') window.closeFullscreen(); });

    // Prevent page scroll when mouse is over graph containers (fixes zoom resetting)
    function preventPageScroll(el) {
        if (!el) return;
        el.addEventListener('wheel', function(e) {
            e.preventDefault();
            // For 2D: manually apply zoom since we're eating the event
            if (cy2d && currentView === '2d') {
                var factor = e.deltaY > 0 ? 0.92 : 1.08;
                var rect = el.getBoundingClientRect();
                cy2d.zoom({
                    level: cy2d.zoom() * factor,
                    renderedPosition: { x: e.clientX - rect.left, y: e.clientY - rect.top }
                });
            }
            // For 3D: ForceGraph3D handles its own wheel via three.js OrbitControls on the canvas
            // The event reaches the canvas first (capture), then bubbles here - we just prevent page scroll
        }, { passive: false });
    }

    // Init
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function() {
        init();
        preventPageScroll(document.getElementById('graphContainer'));
        preventPageScroll(document.getElementById('fullscreenGraph'));
    });
    else {
        init();
        preventPageScroll(document.getElementById('graphContainer'));
        preventPageScroll(document.getElementById('fullscreenGraph'));
    }
})();
