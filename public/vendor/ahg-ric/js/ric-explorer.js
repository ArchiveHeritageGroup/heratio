/**
 * RiC Explorer JavaScript
 * Graph visualization using Cytoscape.js
 * Ported from AtoM ahgRicExplorerPlugin
 */

(function() {
    'use strict';

    // Activity bucket — Production / Accumulation / Custody / Transfer / Publication /
    // Reproduction are pre-spec-v0.37 emissions. RiC-O 1.1 has no concrete subclasses;
    // v0.37+ servers emit rico:Activity + rico:hasActivityType. Both sets kept; the
    // dual-read in getEffectiveType() resolves a v0.37+ Activity node to its slug.
    var COLORS = {
        RecordSet: '#4ecdc4',
        Record: '#45b7d1',
        RecordPart: '#96ceb4',
        Person: '#dc3545',
        CorporateBody: '#ffc107',
        Family: '#e83e8c',
        Mechanism: '#e74c3c',
        Production: '#6f42c1',
        Accumulation: '#5f27cd',
        Custody: '#7d5fff',
        Transfer: '#7d5fff',
        Publication: '#7d5fff',
        Reproduction: '#7d5fff',
        Activity: '#6f42c1',
        Place: '#fd7e14',
        Thing: '#20c997',
        Concept: '#20c997',
        DocumentaryFormType: '#20c997',
        CarrierType: '#20c997',
        ContentType: '#20c997',
        RecordState: '#adb5bd',
        Language: '#0d6efd',
        Instantiation: '#17a2b8',
        Function: '#6c757d',
        default: '#6c757d'
    };

    // Strip IRI / CURIE prefixes so "rico:Person",
    // "https://www.ica.org/standards/RiC/ontology#Person" and "Person" all collapse to "Person".
    function localName(value) {
        if (!value) return '';
        return String(value).split('#').pop().split('/').pop().split(':').pop();
    }

    function capitalise(s) {
        return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
    }

    // Dual-read across the spec v0.37 boundary:
    //   pre-v0.37: node.type === "rico:Production" / "rico:Accumulation" / etc.
    //   v0.37+:   node.type === "rico:Activity" with activity-type carried in
    //             node.attributes.activityType (slug) or .hasActivityType (IRI).
    function getEffectiveType(node) {
        if (!node || !node.type) return 'default';
        var t = localName(node.type);
        if (t !== 'Activity') return t;
        var attrs = node.attributes || {};
        var candidate = attrs.activityType || attrs['rico:hasActivityType'] || attrs.hasActivityType;
        if (!candidate) return 'Activity';
        return capitalise(localName(candidate)) || 'Activity';
    }

    // Accepts either a node object (preferred — dispatches via getEffectiveType for v0.37+
    // Activity dual-read) or a bare type string (back-compat for callers with only a type).
    function getColor(typeOrNode) {
        if (typeOrNode && typeof typeOrNode === 'object' && 'type' in typeOrNode) {
            return COLORS[getEffectiveType(typeOrNode)] || COLORS.default;
        }
        if (!typeOrNode) return COLORS.default;
        return COLORS[localName(typeOrNode)] || COLORS.default;
    }

    /**
     * Initialize a 2D Cytoscape graph in the given container
     */
    function init2DGraph(container, graphData, options) {
        if (!graphData || !graphData.nodes || !window.cytoscape) return null;

        options = options || {};
        var nodeSize = options.nodeSize || 25;
        var fontSize = options.fontSize || '9px';
        var maxLabelLen = options.maxLabelLen || 25;

        var elements = [];

        graphData.nodes.forEach(function(node) {
            elements.push({
                data: {
                    id: node.id,
                    label: node.label ? node.label.substring(0, maxLabelLen) : 'Unknown',
                    type: node.type,
                    color: getColor(node),
                    central: node.central || false,
                    atomId: node.atomId || null,
                    atomUrl: node.atomUrl || null
                }
            });
        });

        (graphData.edges || []).forEach(function(edge, idx) {
            elements.push({
                data: {
                    id: 'e' + idx,
                    source: edge.source,
                    target: edge.target,
                    label: edge.label || ''
                }
            });
        });

        try {
            var cy = cytoscape({
                container: container,
                elements: elements,
                style: [
                    {
                        selector: 'node',
                        style: {
                            'background-color': 'data(color)',
                            'label': 'data(label)',
                            'color': '#fff',
                            'font-size': fontSize,
                            'text-valign': 'bottom',
                            'text-margin-y': '4px',
                            'width': nodeSize + 'px',
                            'height': nodeSize + 'px',
                            'text-wrap': 'ellipsis',
                            'text-max-width': '80px'
                        }
                    },
                    {
                        selector: 'node[?central]',
                        style: {
                            'width': (nodeSize + 10) + 'px',
                            'height': (nodeSize + 10) + 'px',
                            'border-width': '3px',
                            'border-color': '#fff',
                            'font-weight': 'bold'
                        }
                    },
                    {
                        selector: 'edge',
                        style: {
                            'width': 1,
                            'line-color': '#555',
                            'target-arrow-color': '#555',
                            'target-arrow-shape': 'triangle',
                            'curve-style': 'bezier',
                            'label': 'data(label)',
                            'font-size': '7px',
                            'color': '#888',
                            'text-rotation': 'autorotate'
                        }
                    }
                ],
                layout: {
                    name: 'cose',
                    animate: false,
                    nodeRepulsion: function(){ return options.nodeRepulsion || 5000; },
                    idealEdgeLength: options.idealEdgeLength || 80,
                    padding: options.padding || 20
                },
                userZoomingEnabled: true,
                userPanningEnabled: true,
                boxSelectionEnabled: false
            });

            // Center on the main node
            var centralNode = cy.nodes('[?central]');
            if (centralNode.length) {
                cy.center(centralNode);
            }

            // Click handler for nodes with atomUrl
            cy.on('tap', 'node', function(evt) {
                var nodeData = evt.target.data();
                if (nodeData.atomUrl) {
                    window.open(nodeData.atomUrl, '_blank');
                }
            });

            return cy;
        } catch(e) {
            console.error('2D graph init error:', e);
            return null;
        }
    }

    /**
     * Initialize a 3D Force Graph in the given container
     */
    function init3DGraph(container, graphData) {
        if (!graphData || !graphData.nodes || typeof ForceGraph3D === 'undefined') return null;

        var nodes = graphData.nodes.map(function(n) {
            return { id: n.id, name: n.label || 'Unknown', color: getColor(n), val: 1 };
        });
        var links = (graphData.edges || []).map(function(e) {
            return { source: e.source, target: e.target };
        });

        try {
            var w = container.clientWidth || 400;
            var h = container.clientHeight || 350;

            return ForceGraph3D()(container)
                .graphData({ nodes: nodes, links: links })
                .nodeColor('color')
                .nodeVal('val')
                .nodeLabel('name')
                .nodeThreeObject(function(node) {
                    var sprite = new SpriteText(node.name.length > 15 ? node.name.substring(0,15) + '...' : node.name);
                    sprite.color = '#ffffff';
                    sprite.textHeight = 3;
                    sprite.backgroundColor = node.color;
                    sprite.padding = 0.5;
                    sprite.borderRadius = 1;
                    return sprite;
                })
                .nodeThreeObjectExtend(false)
                .linkDirectionalParticles(1)
                .backgroundColor('#1a1a2e')
                .width(w)
                .height(h);
        } catch(e) {
            console.error('3D graph init error:', e);
            return null;
        }
    }

    // Export to global scope
    window.RicExplorer = {
        COLORS: COLORS,
        localName: localName,
        getEffectiveType: getEffectiveType,
        getColor: getColor,
        init2DGraph: init2DGraph,
        init3DGraph: init3DGraph
    };

})();
