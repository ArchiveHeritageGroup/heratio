/**
 * Provenance Timeline Visualization.
 *
 * D3.js-based timeline visualization for museum object provenance chains.
 * Displays ownership history with transfer events and date ranges.
 * Uses even horizontal spacing (sequence-based) so nodes never overlap,
 * regardless of the actual date ranges involved.
 *
 * Usage:
 *   var timeline = new ProvenanceTimeline('#timeline-container', {
 *     data: timelineData,
 *     width: 800,
 *     height: 300
 *   });
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
(function(global) {
  'use strict';

  var ProvenanceTimeline = function(selector, options) {
    this.container = d3.select(selector);
    this.options = Object.assign({}, ProvenanceTimeline.defaults, options);
    this.data = options.data || { nodes: [], links: [], events: [] };

    this.init();
  };

  ProvenanceTimeline.defaults = {
    width: 900,
    height: 340,
    margin: { top: 60, right: 50, bottom: 50, left: 50 },
    nodeRadius: 22,
    colors: {
      person: '#2196f3',
      family: '#9c27b0',
      dealer: '#ff9800',
      auction_house: '#f44336',
      museum: '#4caf50',
      corporate: '#607d8b',
      government: '#795548',
      religious: '#673ab7',
      artist: '#e91e63',
      unknown: '#9e9e9e',
      gap: '#ffeb3b'
    },
    transferIcons: {
      sale: '\u{1F4B0}',
      auction: '\u{1F528}',
      gift: '\u{1F381}',
      bequest: '\u{1F4DC}',
      inheritance: '\u{1F46A}',
      commission: '\u{270F}\u{FE0F}',
      exchange: '\u{1F504}',
      seizure: '\u{26A0}\u{FE0F}',
      restitution: '\u{21A9}\u{FE0F}',
      transfer: '\u{27A1}\u{FE0F}',
      loan: '\u{23F1}\u{FE0F}',
      found: '\u{1F50D}',
      created: '\u{1F3A8}',
      unknown: '\u{2753}'
    },
    ownerIcons: {
      person: '\u{1F464}',
      family: '\u{1F46A}',
      dealer: '\u{1F3EA}',
      auction_house: '\u{1F528}',
      museum: '\u{1F3DB}\u{FE0F}',
      corporate: '\u{1F3E2}',
      government: '\u{1F3DB}\u{FE0F}',
      religious: '\u{26EA}',
      artist: '\u{1F3A8}',
      unknown: '\u{2753}'
    },
    animationDuration: 500
  };

  ProvenanceTimeline.prototype = {
    init: function() {
      this.svg = null;
      this.tooltip = null;

      this.setupSvg();
      this.setupTooltip();
      this.render();
    },

    setupSvg: function() {
      var opts = this.options;
      var nodes = this.data.nodes || [];
      var nodeCount = nodes.length;
      // Use container width, only go wider if too many nodes to fit at 120px each
      var containerWidth = opts.width || 900;
      var neededWidth = nodeCount * 120;
      var svgWidth = neededWidth > containerWidth ? neededWidth : containerWidth;

      this.container.selectAll('*').remove();

      // Wrap in a scrollable div if content wider than container
      this.scrollWrapper = this.container
        .append('div')
        .style('overflow-x', svgWidth > containerWidth ? 'auto' : 'hidden')
        .style('overflow-y', 'hidden');

      this.svg = this.scrollWrapper
        .append('svg')
        .attr('width', svgWidth)
        .attr('height', opts.height)
        .attr('class', 'provenance-timeline');

      // Defs for arrow marker
      var defs = this.svg.append('defs');

      defs.append('marker')
        .attr('id', 'prov-arrow')
        .attr('viewBox', '0 -5 10 10')
        .attr('refX', 8)
        .attr('refY', 0)
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .attr('orient', 'auto')
        .append('path')
        .attr('d', 'M0,-5L10,0L0,5')
        .attr('fill', '#999');

      // Gradient for timeline bar
      var grad = defs.append('linearGradient')
        .attr('id', 'timeline-gradient')
        .attr('x1', '0%').attr('y1', '0%')
        .attr('x2', '100%').attr('y2', '0%');
      grad.append('stop').attr('offset', '0%').attr('stop-color', '#e3f2fd');
      grad.append('stop').attr('offset', '100%').attr('stop-color', '#bbdefb');

      this.mainGroup = this.svg.append('g')
        .attr('transform', 'translate(' + opts.margin.left + ',' + opts.margin.top + ')');

      // Layers
      this.bgLayer = this.mainGroup.append('g').attr('class', 'bg-layer');
      this.linksLayer = this.mainGroup.append('g').attr('class', 'links-layer');
      this.nodesLayer = this.mainGroup.append('g').attr('class', 'nodes-layer');
      this.labelsLayer = this.mainGroup.append('g').attr('class', 'labels-layer');

      this.innerWidth = svgWidth - opts.margin.left - opts.margin.right;
      this.innerHeight = opts.height - opts.margin.top - opts.margin.bottom;
    },

    setupTooltip: function() {
      // Remove any existing tooltip
      d3.selectAll('.provenance-tooltip').remove();

      this.tooltip = d3.select('body')
        .append('div')
        .attr('class', 'provenance-tooltip')
        .style('opacity', 0)
        .style('position', 'absolute')
        .style('background', 'white')
        .style('border', '1px solid #ccc')
        .style('border-radius', '6px')
        .style('padding', '12px')
        .style('pointer-events', 'none')
        .style('max-width', '320px')
        .style('box-shadow', '0 4px 12px rgba(0,0,0,0.15)')
        .style('font-size', '13px')
        .style('line-height', '1.5')
        .style('z-index', 10000);
    },

    /**
     * Compute evenly-spaced x positions for nodes based on index.
     * Returns array of x positions (one per node).
     */
    getNodePositions: function() {
      var nodes = this.data.nodes || [];
      var n = nodes.length;
      if (n === 0) return [];
      if (n === 1) return [this.innerWidth / 2];

      var positions = [];
      var spacing = this.innerWidth / (n - 1);

      for (var i = 0; i < n; i++) {
        positions.push(i * spacing);
      }
      return positions;
    },

    render: function() {
      var nodes = this.data.nodes || [];
      if (nodes.length === 0) return;

      this.nodeXPositions = this.getNodePositions();
      this.yCenter = this.innerHeight / 2;

      this.renderTimelineBar();
      this.renderLinks();
      this.renderNodes();
      this.renderLabels();
      this.renderDates();
      this.renderTransferEvents();
    },

    /** Subtle horizontal bar behind the chain */
    renderTimelineBar: function() {
      var positions = this.nodeXPositions;
      if (positions.length < 2) return;

      this.bgLayer.append('line')
        .attr('x1', positions[0])
        .attr('y1', this.yCenter)
        .attr('x2', positions[positions.length - 1])
        .attr('y2', this.yCenter)
        .attr('stroke', '#e0e0e0')
        .attr('stroke-width', 3)
        .attr('stroke-dasharray', '6,4');
    },

    renderLinks: function() {
      var self = this;
      var opts = this.options;
      var nodes = this.data.nodes;
      var positions = this.nodeXPositions;

      // Draw lines between consecutive nodes
      for (var i = 0; i < nodes.length - 1; i++) {
        var x1 = positions[i] + opts.nodeRadius + 2;
        var x2 = positions[i + 1] - opts.nodeRadius - 2;

        if (x2 > x1) {
          var isGapLink = nodes[i].isGap || nodes[i + 1].isGap;

          this.linksLayer.append('line')
            .attr('x1', x1)
            .attr('y1', self.yCenter)
            .attr('x2', x2)
            .attr('y2', self.yCenter)
            .attr('stroke', isGapLink ? '#ffca28' : '#999')
            .attr('stroke-width', 2)
            .attr('stroke-dasharray', isGapLink ? '6,3' : 'none')
            .attr('marker-end', 'url(#prov-arrow)');
        }
      }
    },

    renderNodes: function() {
      var self = this;
      var opts = this.options;
      var nodes = this.data.nodes;
      var positions = this.nodeXPositions;

      var nodeGroups = this.nodesLayer.selectAll('.provenance-node')
        .data(nodes)
        .enter()
        .append('g')
        .attr('class', 'provenance-node')
        .attr('transform', function(d, i) {
          return 'translate(' + positions[i] + ',' + self.yCenter + ')';
        })
        .style('cursor', 'pointer')
        .on('mouseover', function(event, d) { self.showTooltip(event, d); })
        .on('mouseout', function() { self.hideTooltip(); })
        .on('click', function(event, d) { self.onNodeClick(d); });

      // Node circles
      nodeGroups.append('circle')
        .attr('r', opts.nodeRadius)
        .attr('fill', function(d) {
          if (d.isGap) return opts.colors.gap;
          return opts.colors[d.ownerType] || opts.colors.unknown;
        })
        .attr('stroke', function(d) {
          if (d.isGap) return '#f9a825';
          return d.certaintyValue < 50 ? '#bbb' : '#fff';
        })
        .attr('stroke-width', function(d) {
          return d.isGap ? 2 : 2;
        })
        .attr('stroke-dasharray', function(d) {
          return (d.certaintyValue < 50) ? '4,2' : 'none';
        })
        .attr('opacity', function(d) {
          return 0.6 + (d.certaintyValue / 300);
        });

      // Owner icon inside circle
      nodeGroups.append('text')
        .attr('text-anchor', 'middle')
        .attr('dy', '0.35em')
        .attr('font-size', '16px')
        .text(function(d) {
          return self.options.ownerIcons[d.ownerType] || self.options.ownerIcons.unknown;
        });

      // Certainty indicator (small badge)
      nodeGroups.each(function(d) {
        if (d.certainty && d.certainty !== 'certain') {
          var certaintyColors = {
            probable: '#8bc34a', possible: '#ffb74d',
            uncertain: '#ef5350', unknown: '#bdbdbd'
          };
          d3.select(this).append('circle')
            .attr('cx', opts.nodeRadius - 4)
            .attr('cy', -opts.nodeRadius + 4)
            .attr('r', 6)
            .attr('fill', certaintyColors[d.certainty] || '#bdbdbd')
            .attr('stroke', '#fff')
            .attr('stroke-width', 1.5);
        }
      });
    },

    renderLabels: function() {
      var self = this;
      var opts = this.options;
      var nodes = this.data.nodes;
      var positions = this.nodeXPositions;

      // Owner name below node
      this.labelsLayer.selectAll('.provenance-label')
        .data(nodes)
        .enter()
        .append('text')
        .attr('class', 'provenance-label')
        .attr('x', function(d, i) { return positions[i]; })
        .attr('y', self.yCenter + opts.nodeRadius + 18)
        .attr('text-anchor', 'middle')
        .attr('font-size', '11px')
        .attr('font-weight', '500')
        .attr('fill', '#333')
        .text(function(d) {
          var label = d.label || 'Unknown';
          return label.length > 18 ? label.substr(0, 16) + '\u2026' : label;
        });

      // Location below name (smaller)
      this.labelsLayer.selectAll('.provenance-location')
        .data(nodes)
        .enter()
        .append('text')
        .attr('class', 'provenance-location')
        .attr('x', function(d, i) { return positions[i]; })
        .attr('y', self.yCenter + opts.nodeRadius + 32)
        .attr('text-anchor', 'middle')
        .attr('font-size', '9px')
        .attr('fill', '#888')
        .text(function(d) {
          if (!d.location) return '';
          return d.location.length > 22 ? d.location.substr(0, 20) + '\u2026' : d.location;
        });
    },

    renderDates: function() {
      var self = this;
      var opts = this.options;
      var nodes = this.data.nodes;
      var positions = this.nodeXPositions;

      // Date above node
      this.labelsLayer.selectAll('.provenance-date')
        .data(nodes)
        .enter()
        .append('text')
        .attr('class', 'provenance-date')
        .attr('x', function(d, i) { return positions[i]; })
        .attr('y', self.yCenter - opts.nodeRadius - 10)
        .attr('text-anchor', 'middle')
        .attr('font-size', '10px')
        .attr('font-weight', '600')
        .attr('fill', '#555')
        .text(function(d) {
          return self.formatDateRange(d.startYear, d.endYear);
        });
    },

    renderTransferEvents: function() {
      var self = this;
      var opts = this.options;
      var nodes = this.data.nodes;
      var positions = this.nodeXPositions;
      var events = this.data.events || [];

      // Place transfer icons on the link midpoint between consecutive nodes
      // Match events to links by index (event[i] corresponds to transfer from node[i] to node[i+1])
      for (var i = 0; i < nodes.length - 1 && i < events.length; i++) {
        var event = events[i];
        if (!event || !event.transferType || event.transferType === 'unknown') continue;

        var midX = (positions[i] + positions[i + 1]) / 2;
        var icon = opts.transferIcons[event.transferType] || opts.transferIcons.unknown;

        var g = this.linksLayer.append('g')
          .attr('class', 'transfer-event')
          .attr('transform', 'translate(' + midX + ',' + (self.yCenter - 22) + ')')
          .style('cursor', 'pointer')
          .on('mouseover', (function(ev) {
            return function(mouseEvent) { self.showTransferTooltip(mouseEvent, ev); };
          })(event))
          .on('mouseout', function() { self.hideTooltip(); });

        g.append('text')
          .attr('text-anchor', 'middle')
          .attr('font-size', '14px')
          .text(icon);

        g.append('text')
          .attr('text-anchor', 'middle')
          .attr('y', -12)
          .attr('font-size', '8px')
          .attr('fill', '#999')
          .text(event.label || '');
      }
    },

    formatDateRange: function(startYear, endYear) {
      if (!startYear && startYear !== 0) return '';

      var startStr = this.formatYear(startYear);
      if (!endYear || endYear === startYear) return startStr;

      var endStr = this.formatYear(endYear);
      return startStr + ' \u2013 ' + endStr;
    },

    formatYear: function(year) {
      if (year === null || year === undefined) return '';
      if (year < 0) return Math.abs(year) + ' BC';
      if (year === 0) return '1 BC';
      return String(year);
    },

    showTooltip: function(event, d) {
      var html = '<div style="border-bottom:2px solid ' +
        (this.options.colors[d.ownerType] || '#999') + ';padding-bottom:6px;margin-bottom:6px">' +
        '<strong style="font-size:14px">' + (d.label || 'Unknown') + '</strong>';

      if (d.ownerType && d.ownerType !== 'unknown') {
        html += ' <span style="color:#888;font-size:12px">(' +
          d.ownerType.replace(/_/g, ' ') + ')</span>';
      }
      html += '</div>';

      if (d.location) html += '<div>\u{1F4CD} ' + d.location + '</div>';

      if (d.startYear || d.startYear === 0) {
        html += '<div>\u{1F4C5} ' + this.formatDateRange(d.startYear, d.endYear) + '</div>';
      }

      if (d.certainty && d.certainty !== 'certain') {
        var certaintyColors = {
          probable: '#8bc34a', possible: '#ffb74d',
          uncertain: '#ef5350', unknown: '#bdbdbd'
        };
        html += '<div style="margin-top:4px">' +
          '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' +
          (certaintyColors[d.certainty] || '#bdbdbd') + ';margin-right:4px"></span>' +
          'Certainty: ' + d.certainty + '</div>';
      }

      if (d.isGap) {
        html += '<div style="color:#e65100;margin-top:4px">\u{26A0}\u{FE0F} Gap in provenance chain</div>';
      }

      this.tooltip
        .html(html)
        .style('left', (event.pageX + 12) + 'px')
        .style('top', (event.pageY - 12) + 'px')
        .transition().duration(200)
        .style('opacity', 1);
    },

    showTransferTooltip: function(event, d) {
      var html = '<strong>' + (d.label || 'Transfer') + '</strong>';
      if (d.year) html += '<br>Year: ' + this.formatYear(d.year);
      if (d.details) html += '<br>' + d.details;
      if (d.salePrice) {
        html += '<br>Price: ' + (d.saleCurrency || '') + ' ' +
          d.salePrice.toLocaleString();
      }
      if (d.auctionHouse) {
        html += '<br>Auction: ' + d.auctionHouse;
        if (d.auctionLot) html += ' (Lot ' + d.auctionLot + ')';
      }

      this.tooltip
        .html(html)
        .style('left', (event.pageX + 12) + 'px')
        .style('top', (event.pageY - 12) + 'px')
        .transition().duration(200)
        .style('opacity', 1);
    },

    hideTooltip: function() {
      this.tooltip
        .transition().duration(200)
        .style('opacity', 0);
    },

    onNodeClick: function(d) {
      if (this.options.onNodeClick) {
        this.options.onNodeClick(d);
      }
    },

    update: function(newData) {
      this.data = newData;
      this.init();
    },

    resize: function(width, height) {
      this.options.width = width;
      if (height) this.options.height = height;
      this.init();
    },

    destroy: function() {
      this.container.selectAll('*').remove();
      if (this.tooltip) {
        this.tooltip.remove();
      }
    }
  };

  // Export
  global.ProvenanceTimeline = ProvenanceTimeline;

})(typeof window !== 'undefined' ? window : this);
