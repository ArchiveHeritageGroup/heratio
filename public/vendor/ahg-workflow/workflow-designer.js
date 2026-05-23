/**
 * heratio#143 Phase 3 — workflow designer adapter for drawflow.js
 *
 * Reads steps + existing edges from the #wf-designer-data JSON island,
 * renders the canvas, and POSTs the current graph to the save endpoint
 * when the user clicks Save.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later.
 */
(function () {
  'use strict';

  var canvas = document.getElementById('wf-designer-canvas');
  var dataIsland = document.getElementById('wf-designer-data');
  var btnSave = document.getElementById('wf-designer-save');
  var btnClear = document.getElementById('wf-designer-clear');
  var btnRelayout = document.getElementById('wf-designer-relayout');
  var flash = document.getElementById('wf-designer-flash');

  if (!canvas || !dataIsland) {
    return;
  }

  var payload;
  try {
    payload = JSON.parse(dataIsland.textContent || '{}');
  } catch (e) {
    showFlash('danger', 'Designer data could not be parsed.');
    return;
  }
  var steps = Array.isArray(payload.steps) ? payload.steps : [];
  var edges = Array.isArray(payload.edges) ? payload.edges : [];

  // Drawflow setup
  var editor = new Drawflow(canvas);
  editor.reroute = true;
  editor.start();

  // Map step_id -> drawflow node id (drawflow uses its own internal numbering)
  var nodeIdByStepId = {};
  var stepIdByNodeId = {};

  layoutAndRender();

  // -------- Toolbar wiring --------
  btnSave && btnSave.addEventListener('click', save);
  btnClear && btnClear.addEventListener('click', clearEdges);
  btnRelayout && btnRelayout.addEventListener('click', relayout);

  // -------- Functions --------

  function layoutAndRender() {
    editor.clear();
    nodeIdByStepId = {};
    stepIdByNodeId = {};

    if (steps.length === 0) {
      return;
    }

    // Simple grid layout: row = step_order, columns within a row by index.
    var rows = {};
    steps.forEach(function (s) {
      var order = parseInt(s.step_order, 10) || 1;
      (rows[order] = rows[order] || []).push(s);
    });
    var orders = Object.keys(rows).map(Number).sort(function (a, b) { return a - b; });

    var Y_GAP = 130;
    var X_GAP = 260;
    var X_PAD = 30;
    var Y_PAD = 30;

    orders.forEach(function (order, rowIdx) {
      var rowSteps = rows[order];
      rowSteps.forEach(function (step, colIdx) {
        var x = X_PAD + colIdx * X_GAP;
        var y = Y_PAD + rowIdx * Y_GAP;
        var optClass = step.is_optional ? ' optional' : '';
        var html =
          '<div class="step-name">' + escapeHtml(step.name) + '</div>' +
          '<div class="step-type">' + escapeHtml(prettyType(step.step_type)) + '</div>';
        var nodeId = editor.addNode(
          'step-' + step.id,
          1, 1,
          x, y,
          'step' + optClass,
          { stepId: step.id },
          html
        );
        nodeIdByStepId[step.id] = nodeId;
        stepIdByNodeId[nodeId] = step.id;
      });
    });

    // Restore existing edges
    edges.forEach(function (e) {
      var fromNode = nodeIdByStepId[e.from_step_id];
      var toNode = nodeIdByStepId[e.to_step_id];
      if (fromNode && toNode) {
        editor.addConnection(fromNode, toNode, 'output_1', 'input_1');
      }
    });
  }

  function relayout() {
    // Re-run the row-by-step_order layout from scratch (keeps current edges).
    var currentEdges = currentEdgesFromCanvas();
    edges = currentEdges;
    layoutAndRender();
    showFlash('info', 'Layout reset to step-order grid.');
  }

  function clearEdges() {
    if (!confirm('Remove all edges from the canvas? (Steps stay, you still have to Save to persist.)')) {
      return;
    }
    // Drawflow exposes the internal state via .drawflow.drawflow.Home.data
    var data = editor.drawflow.drawflow.Home.data;
    Object.keys(data).forEach(function (nodeId) {
      var node = data[nodeId];
      if (node && node.outputs && node.outputs.output_1) {
        node.outputs.output_1.connections = [];
      }
      if (node && node.inputs && node.inputs.input_1) {
        node.inputs.input_1.connections = [];
      }
    });
    // Force redraw
    layoutAndRender();
    edges = [];
    showFlash('info', 'All edges removed from canvas. Click Save to persist.');
  }

  function currentEdgesFromCanvas() {
    var data = editor.drawflow.drawflow.Home.data;
    var out = [];
    Object.keys(data).forEach(function (nodeId) {
      var node = data[nodeId];
      if (!node || !node.outputs || !node.outputs.output_1) {
        return;
      }
      (node.outputs.output_1.connections || []).forEach(function (c) {
        var fromStep = stepIdByNodeId[parseInt(nodeId, 10)];
        var toStep = stepIdByNodeId[parseInt(c.node, 10)];
        if (fromStep && toStep) {
          out.push({ from_step_id: fromStep, to_step_id: toStep, condition_expr: null });
        }
      });
    });
    return out;
  }

  function save() {
    var payloadEdges = currentEdgesFromCanvas();
    btnSave.disabled = true;
    btnSave.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving…';

    fetch(canvas.dataset.saveUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': canvas.dataset.csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify({ edges: payloadEdges })
    })
      .then(function (r) {
        return r.json().then(function (body) { return { status: r.status, body: body }; });
      })
      .then(function (res) {
        if (res.body && res.body.ok) {
          edges = payloadEdges;
          showFlash('success', 'Saved ' + (res.body.written || payloadEdges.length) + ' edge(s).');
        } else {
          var errs = (res.body && res.body.errors) || ['Save failed'];
          showFlash('danger', errs.join(' • '));
        }
      })
      .catch(function (err) {
        showFlash('danger', 'Network error: ' + err.message);
      })
      .finally(function () {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="fas fa-save me-1"></i>Save edges';
      });
  }

  function showFlash(kind, msg) {
    if (!flash) { return; }
    flash.className = 'alert alert-' + kind + ' wf-designer-flash';
    flash.style.display = 'block';
    flash.textContent = msg;
    if (kind === 'success' || kind === 'info') {
      setTimeout(function () { flash.style.display = 'none'; }, 4000);
    }
  }

  function prettyType(t) {
    t = String(t || 'review');
    return t.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
})();
