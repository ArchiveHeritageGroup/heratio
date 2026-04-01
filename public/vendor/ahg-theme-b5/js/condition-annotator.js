class ConditionAnnotator {
  constructor(containerId, options = {}) {
    this.containerId = containerId;
    this.container = document.getElementById(containerId);

    if (!this.container) throw new Error(`Container #${containerId} not found`);

    this.options = Object.assign(
      {
        photoId: null,
        imageUrl: null,
        readonly: false,
        showToolbar: true,
        showLegend: true,
        saveUrl: "/condition/annotation/save",
        getUrl: "/condition/annotation/get",
      },
      options
    );

    this.canvas = null;
    this.currentTool = "select";
    this.currentColor = "#FF0000";
    this.currentCategory = "damage";
    this.isDrawing = false;
    this.startPoint = null;
    this.tempShape = null;
    this.annotationsVisible = true;
    this.imageLoaded = false;
    this.originalImageSize = { width: 0, height: 0 };
    this.scale = 1;
    this.isDirty = false;

    this._colorClassCache = new Map();
    this._styleSheetEl = null;

    this.categories = {
      damage: { color: "#FF0000", label: "Damage" },
      crack: { color: "#FF4500", label: "Crack" },
      stain: { color: "#DAA520", label: "Stain" },
      tear: { color: "#DC143C", label: "Tear" },
      loss: { color: "#9400D3", label: "Loss/Missing" },
      mould: { color: "#8B0000", label: "Mould/Fungus" },
      pest: { color: "#006400", label: "Pest Damage" },
      water: { color: "#1E90FF", label: "Water Damage" },
      abrasion: { color: "#FF8C00", label: "Abrasion/Wear" },
      corrosion: { color: "#2F4F4F", label: "Corrosion" },
      note: { color: "#4169E1", label: "General Note" },
    };

    this.init();
  }

  init() {
    this.createUI();
    this.initCanvas();
    this.bindEvents();
    if (this.options.imageUrl) setTimeout(() => this.loadImage(this.options.imageUrl), 150);
  }

  getCspNonce() {
    var meta = document.querySelector('meta[name="csp-nonce"]');
    return meta ? meta.getAttribute("content") : "";
  }

  ensureNonceStyleSheet() {
    if (this._styleSheetEl) return this._styleSheetEl;
    var nonce = this.getCspNonce();
    var style = document.createElement("style");
    if (nonce) style.setAttribute("nonce", nonce);
    style.appendChild(document.createTextNode(""));
    document.head.appendChild(style);
    this._styleSheetEl = style;
    return style;
  }

  ensureColorClass(hex) {
    var norm = (hex || "").toUpperCase();
    if (!/^#[0-9A-F]{6}$/.test(norm)) return "ann-c-default";
    if (this._colorClassCache.has(norm)) return this._colorClassCache.get(norm);

    var safe = norm.replace("#", "").toLowerCase();
    var cls = "ann-c-" + safe;
    var styleEl = this.ensureNonceStyleSheet();
    var sheet = styleEl.sheet;

    var rule = ".condition-annotator .legend-item." + cls + " { --annColor: " + norm + "; }" +
      ".condition-annotator .legend-item." + cls + " .legend-leftbar { background: " + norm + "; }" +
      ".condition-annotator .legend-item." + cls + " .legend-badge { background: " + norm + "; }" +
      ".condition-annotator .legend-item." + cls + " .legend-title { color: " + norm + "; }";

    rule.split("}").map(function(r) { return r.trim(); }).filter(Boolean).forEach(function(r) {
      try { sheet.insertRule(r + "}", sheet.cssRules.length); } catch (e) {}
    });

    this._colorClassCache.set(norm, cls);
    return cls;
  }

  createUI() {
    this.container.innerHTML = 
      '<div class="condition-annotator">' +
        (this.options.showToolbar && !this.options.readonly ? this.createToolbar() : "") +
        '<div class="annotator-main">' +
          '<div class="annotator-canvas-container">' +
            '<canvas id="' + this.containerId + '-canvas"></canvas>' +
          '</div>' +
          (this.options.showLegend ? this.createLegend() : "") +
        '</div>' +
        '<div class="annotator-status-bar">' +
          '<span class="status-text">Ready</span>' +
          '<span class="annotation-count">0 annotations</span>' +
        '</div>' +
      '</div>';
  }

  createToolbar() {
    var self = this;
    var categoryOptions = Object.entries(this.categories).map(function(entry) {
      return '<option value="' + entry[0] + '" data-color="' + entry[1].color + '">' + entry[1].label + '</option>';
    }).join("");

    return '<div class="annotator-toolbar">' +
      '<div class="btn-group" role="group">' +
        '<button type="button" class="tool-btn btn btn-sm btn-primary active" data-tool="select" title="Select (V)"><i class="fas fa-mouse-pointer"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="rect" title="Rectangle (R)"><i class="far fa-square"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="circle" title="Circle (C)"><i class="far fa-circle"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="arrow" title="Arrow (A)"><i class="fas fa-long-arrow-alt-right"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="freehand" title="Freehand (F)"><i class="fas fa-pencil-alt"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="text" title="Text (T)"><i class="fas fa-font"></i></button>' +
        '<button type="button" class="tool-btn btn btn-sm btn-secondary" data-tool="marker" title="Marker (M)"><i class="fas fa-map-marker-alt"></i></button>' +
      '</div>' +
      '<div class="annotator-field"><label class="annotator-label">Category:</label>' +
        '<select id="' + this.containerId + '-category" class="form-select form-select-sm annotator-select">' + categoryOptions + '</select>' +
      '</div>' +
      '<div class="annotator-field"><label class="annotator-label">Color:</label>' +
        '<input type="color" id="' + this.containerId + '-color" value="' + this.currentColor + '" class="annotator-color">' +
      '</div>' +
      '<div class="btn-group ms-auto" role="group">' +
        '<button type="button" class="btn btn-sm btn-info" id="' + this.containerId + '-toggle" title="Show/Hide"><i class="fas fa-eye"></i></button>' +
        '<button type="button" class="btn btn-sm btn-danger" id="' + this.containerId + '-delete" title="Delete"><i class="fas fa-trash"></i></button>' +
        '<button type="button" class="btn btn-sm btn-warning" id="' + this.containerId + '-undo" title="Undo"><i class="fas fa-undo"></i></button>' +
      '</div>' +
      '<div class="btn-group" role="group">' +
        '<button type="button" class="btn btn-sm btn-secondary" id="' + this.containerId + '-zoom-in" title="Zoom In"><i class="fas fa-search-plus"></i></button>' +
        '<button type="button" class="btn btn-sm btn-secondary" id="' + this.containerId + '-zoom-out" title="Zoom Out"><i class="fas fa-search-minus"></i></button>' +
        '<button type="button" class="btn btn-sm btn-secondary" id="' + this.containerId + '-zoom-fit" title="Fit"><i class="fas fa-expand"></i></button>' +
      '</div>' +
    '</div>';
  }

  createLegend() {
    return '<div class="annotator-legend" id="' + this.containerId + '-legend">' +
      '<div class="legend-header"><strong><i class="fas fa-list-ul me-2"></i>Annotations</strong></div>' +
      '<div class="legend-items"><div class="legend-empty"><i class="fas fa-draw-polygon fa-2x mb-2 d-block opacity-50"></i>No annotations yet.<br><small>Use tools above to annotate.</small></div></div>' +
    '</div>';
  }

  initCanvas() {
    var canvasEl = document.getElementById(this.containerId + "-canvas");
    this.canvas = new fabric.Canvas(canvasEl, { selection: !this.options.readonly, preserveObjectStacking: true });
  }

  bindEvents() {
    var self = this;
    this.container.querySelectorAll(".tool-btn").forEach(function(btn) {
      btn.addEventListener("click", function() {
        self.setTool(btn.dataset.tool);
        self.container.querySelectorAll(".tool-btn").forEach(function(b) {
          b.classList.remove("active", "btn-primary");
          b.classList.add("btn-secondary");
        });
        btn.classList.remove("btn-secondary");
        btn.classList.add("active", "btn-primary");
      });
    });

    var categorySelect = document.getElementById(this.containerId + "-category");
    if (categorySelect) {
      categorySelect.addEventListener("change", function(e) {
        self.currentCategory = e.target.value;
        var option = e.target.options[e.target.selectedIndex];
        self.currentColor = option.dataset.color;
        var cp = document.getElementById(self.containerId + "-color");
        if (cp) cp.value = self.currentColor;
      });
    }

    var colorPicker = document.getElementById(this.containerId + "-color");
    if (colorPicker) {
      colorPicker.addEventListener("change", function(e) {
        self.currentColor = e.target.value.toUpperCase();
      });
    }

    this.bindButton("toggle", function() { self.toggleAnnotations(); });
    this.bindButton("delete", function() { self.deleteSelected(); });
    this.bindButton("undo", function() { self.undo(); });
    this.bindButton("zoom-in", function() { self.zoom(1.25); });
    this.bindButton("zoom-out", function() { self.zoom(0.8); });
    this.bindButton("zoom-fit", function() { self.zoomFit(); });

    this.canvas.on("mouse:down", function(e) { self.onMouseDown(e); });
    this.canvas.on("mouse:move", function(e) { self.onMouseMove(e); });
    this.canvas.on("mouse:up", function(e) { self.onMouseUp(e); });
    this.canvas.on("object:modified", function() { self.markDirty(); });
    this.canvas.on("selection:created", function(e) { self.highlightLegendItem(e); });
    this.canvas.on("selection:updated", function(e) { self.highlightLegendItem(e); });
    this.canvas.on("selection:cleared", function() { self.highlightLegendItem(null); });

    document.addEventListener("keydown", function(e) {
      if (["INPUT", "TEXTAREA", "SELECT"].includes(e.target.tagName)) return;
      if (e.key === "Delete" || e.key === "Backspace") self.deleteSelected();
      else if (e.ctrlKey && e.key === "z") self.undo();
      else if (e.ctrlKey && e.key === "s") { e.preventDefault(); self.save(); }
    });
  }

  bindButton(id, handler) {
    var btn = document.getElementById(this.containerId + "-" + id);
    if (btn) btn.addEventListener("click", handler);
  }

  setTool(tool) {
    this.currentTool = tool;
    var cursors = { select: "default", rect: "crosshair", circle: "crosshair", arrow: "crosshair", freehand: "crosshair", text: "text", marker: "crosshair" };
    this.canvas.defaultCursor = cursors[tool] || "default";
    this.canvas.hoverCursor = tool === "select" ? "move" : cursors[tool];
    this.canvas.isDrawingMode = tool === "freehand";
    if (tool === "freehand") {
      this.canvas.freeDrawingBrush.color = this.currentColor;
      this.canvas.freeDrawingBrush.width = 3;
    }
    this.setStatus("Tool: " + tool);
  }

  loadImage(url) {
    var self = this;
    this.setStatus("Loading image...");
    var containerEl = this.container.querySelector(".annotator-canvas-container");
    var maxWidth = containerEl.clientWidth - 40;
    var maxHeight = containerEl.clientHeight - 40;
    if (maxWidth < 400) maxWidth = 600;
    if (maxHeight < 300) maxHeight = 400;

    // Add cache-busting parameter
    var imageUrl = url + (url.includes("?") ? "&" : "?") + "_t=" + Date.now();

    // Load image directly with fabric.js (simpler, avoids canvas intermediate step)
    fabric.Image.fromURL(imageUrl, function(fabricImg) {
      if (!fabricImg) {
        console.error("Failed to load image from URL:", imageUrl);
        self.setStatus("Failed to load image");
        return;
      }

      var width = fabricImg.width;
      var height = fabricImg.height;

      if (width === 0 || height === 0) {
        self.setStatus("Invalid image dimensions");
        return;
      }

      self.originalImageSize = { width: width, height: height };
      var scaleX = maxWidth / width;
      var scaleY = maxHeight / height;
      self.scale = Math.min(scaleX, scaleY, 1);
      if (self.scale < 0.1) self.scale = 0.1;

      self.canvas.setWidth(Math.round(width * self.scale));
      self.canvas.setHeight(Math.round(height * self.scale));

      fabricImg.set({ scaleX: self.scale, scaleY: self.scale, left: 0, top: 0, originX: "left", originY: "top" });
      self.canvas.setBackgroundImage(fabricImg, function() {
        self.canvas.renderAll();
        self.imageLoaded = true;
        self.setStatus("Loaded " + width + "x" + height + " at " + Math.round(self.scale * 100) + "%");
        if (self.options.photoId) setTimeout(function() { self.loadAnnotations(); }, 100);
      });
    }, { crossOrigin: 'anonymous' });
  }

  loadImageWithOrientation(url) {
    return new Promise(function(resolve, reject) {
      var img = new Image();
      img.crossOrigin = "anonymous";
      img.onload = function() {
        var canvas = document.createElement("canvas");
        var ctx = canvas.getContext("2d");
        var width = img.naturalWidth || img.width;
        var height = img.naturalHeight || img.height;
        canvas.width = width;
        canvas.height = height;
        ctx.drawImage(img, 0, 0);
        resolve({ width: width, height: height, dataUrl: canvas.toDataURL("image/jpeg", 0.95) });
      };
      img.onerror = function() { reject(new Error("Failed to load image")); };
      img.src = url + (url.includes("?") ? "&" : "?") + "_t=" + Date.now();
    });
  }

  onMouseDown(e) {
    if (this.options.readonly || this.currentTool === "select" || this.currentTool === "freehand") return;
    var pointer = this.canvas.getPointer(e.e);
    this.isDrawing = true;
    this.startPoint = pointer;
    if (this.currentTool === "text") { this.addTextLabel(pointer); this.isDrawing = false; }
    else if (this.currentTool === "marker") { this.addMarker(pointer); this.isDrawing = false; }
  }

  onMouseMove(e) {
    if (!this.isDrawing || this.options.readonly) return;
    var pointer = this.canvas.getPointer(e.e);
    if (this.tempShape) this.canvas.remove(this.tempShape);
    this.tempShape = this.createShape(this.startPoint, pointer, true);
    if (this.tempShape) this.canvas.add(this.tempShape);
  }

  onMouseUp(e) {
    if (!this.isDrawing || this.options.readonly) return;
    var pointer = this.canvas.getPointer(e.e);
    this.isDrawing = false;
    if (this.tempShape) { this.canvas.remove(this.tempShape); this.tempShape = null; }
    var w = Math.abs(pointer.x - this.startPoint.x), h = Math.abs(pointer.y - this.startPoint.y);
    if (w < 5 && h < 5) return;
    var shape = this.createShape(this.startPoint, pointer, false);
    if (!shape) return;
    var self = this;
    shape.annotationData = {
      id: "ann_" + Date.now() + "_" + Math.random().toString(36).slice(2, 11),
      type: this.currentTool,
      category: this.currentCategory,
      label: this.categories[this.currentCategory]?.label || "Note",
      color: this.currentColor,
      created_at: new Date().toISOString()
    };
    this.canvas.add(shape);
    this.canvas.setActiveObject(shape);
    this.markDirty();
    this.updateLegend();
    setTimeout(function() {
      var notes = prompt("Add notes for this " + shape.annotationData.label + ":");
      if (notes) { shape.annotationData.notes = notes; self.updateLegend(); }
    }, 100);
  }

  createShape(start, end, isTemp) {
    var opts = {
      stroke: this.currentColor,
      strokeWidth: isTemp ? 1 : 3,
      fill: isTemp ? "rgba(255,0,0,0.1)" : "transparent",
      strokeDashArray: isTemp ? [5, 5] : null,
      selectable: !isTemp && !this.options.readonly,
      evented: !isTemp && !this.options.readonly
    };
    switch (this.currentTool) {
      case "rect":
        return new fabric.Rect({ left: Math.min(start.x, end.x), top: Math.min(start.y, end.y), width: Math.abs(end.x - start.x), height: Math.abs(end.y - start.y), ...opts });
      case "circle":
        return new fabric.Ellipse({ left: Math.min(start.x, end.x), top: Math.min(start.y, end.y), rx: Math.abs(end.x - start.x) / 2, ry: Math.abs(end.y - start.y) / 2, ...opts });
      case "arrow":
        var angle = Math.atan2(end.y - start.y, end.x - start.x);
        var line = new fabric.Line([start.x, start.y, end.x, end.y], { ...opts, fill: null });
        var head = new fabric.Triangle({ left: end.x, top: end.y, width: 15, height: 15, fill: this.currentColor, angle: (angle * 180) / Math.PI + 90, originX: "center", originY: "center", selectable: false, evented: false });
        return new fabric.Group([line, head], { selectable: !this.options.readonly, evented: !this.options.readonly });
      default: return null;
    }
  }

  addTextLabel(pointer) {
    var text = prompt("Enter annotation text:");
    if (!text) return;
    var label = new fabric.IText(text, { left: pointer.x, top: pointer.y, fontSize: 16, fill: this.currentColor, backgroundColor: "rgba(255,255,255,0.9)", padding: 5, selectable: !this.options.readonly, evented: !this.options.readonly });
    label.annotationData = { id: "ann_" + Date.now(), type: "text", category: this.currentCategory, label: text, color: this.currentColor, created_at: new Date().toISOString() };
    this.canvas.add(label);
    this.markDirty();
    this.updateLegend();
  }

  addMarker(pointer) {
    var notes = prompt("Enter marker note:");
    var idx = this.getAnnotationObjects().length + 1;
    var circle = new fabric.Circle({ radius: 14, fill: this.currentColor, originX: "center", originY: "center" });
    var number = new fabric.Text(idx.toString(), { fontSize: 14, fill: "#FFF", fontWeight: "bold", originX: "center", originY: "center" });
    var marker = new fabric.Group([circle, number], { left: pointer.x, top: pointer.y, selectable: !this.options.readonly, evented: !this.options.readonly });
    marker.annotationData = { id: "ann_" + Date.now(), type: "marker", category: this.currentCategory, label: "Marker " + idx, notes: notes || "", color: this.currentColor, created_at: new Date().toISOString() };
    this.canvas.add(marker);
    this.markDirty();
    this.updateLegend();
  }

  toggleAnnotations() {
    var self = this;
    this.annotationsVisible = !this.annotationsVisible;
    this.getAnnotationObjects().forEach(function(obj) { obj.visible = self.annotationsVisible; });
    this.canvas.renderAll();
    this.setStatus(this.annotationsVisible ? "Annotations visible" : "Annotations hidden");
  }

  deleteSelected() {
    var self = this;
    var objs = this.canvas.getActiveObjects();
    if (!objs.length) return;
    objs.forEach(function(obj) { if (obj.annotationData) self.canvas.remove(obj); });
    this.canvas.discardActiveObject();
    this.markDirty();
    this.updateLegend();
  }

  undo() {
    var objs = this.getAnnotationObjects();
    if (!objs.length) return;
    this.canvas.remove(objs[objs.length - 1]);
    this.markDirty();
    this.updateLegend();
  }

  zoom(factor) {
    var z = this.canvas.getZoom() * factor;
    if (z < 0.1 || z > 5) return;
    this.canvas.setZoom(z);
    this.canvas.setWidth(this.originalImageSize.width * this.scale * z);
    this.canvas.setHeight(this.originalImageSize.height * this.scale * z);
    this.setStatus("Zoom: " + Math.round(z * this.scale * 100) + "%");
  }

  zoomFit() {
    if (!this.imageLoaded) return;
    this.canvas.setZoom(1);
    this.canvas.setWidth(this.originalImageSize.width * this.scale);
    this.canvas.setHeight(this.originalImageSize.height * this.scale);
    this.canvas.renderAll();
    this.setStatus("Fit: " + Math.round(this.scale * 100) + "%");
  }

  getAnnotationObjects() {
    return this.canvas.getObjects().filter(function(obj) { return obj.annotationData; });
  }

  setStatus(text) {
    var el = this.container.querySelector(".status-text");
    if (el) el.textContent = text;
  }

  markDirty() { this.isDirty = true; }

  updateLegend() {
    var self = this;
    var annotations = this.getAnnotationObjects();
    var count = annotations.length;
    var countEl = this.container.querySelector(".annotation-count");
    if (countEl) countEl.textContent = count + " annotation" + (count !== 1 ? "s" : "");
    var legendItems = this.container.querySelector(".legend-items");
    if (!legendItems) return;
    if (count === 0) {
      legendItems.innerHTML = '<div class="legend-empty"><i class="fas fa-draw-polygon fa-2x mb-2 d-block opacity-50"></i>No annotations yet.<br><small>Use tools above to annotate.</small></div>';
      return;
    }
    legendItems.innerHTML = annotations.map(function(obj, idx) {
      var ann = obj.annotationData;
      var color = (ann.color || obj.stroke || "#FF0000").toUpperCase();
      var category = self.categories[ann.category] || { label: ann.category || "Note" };
      var cls = self.ensureColorClass(color);
      var icon = self.getTypeIcon(ann.type);
      return '<div class="legend-item ' + cls + '" data-id="' + ann.id + '">' +
        '<div class="legend-leftbar" aria-hidden="true"></div>' +
        '<div class="legend-body">' +
          '<div class="legend-row">' +
            '<div class="legend-title"><span class="legend-badge">' + (idx + 1) + '</span>' + category.label + '</div>' +
            '<div class="legend-icon">' + icon + '</div>' +
          '</div>' +
          (ann.notes ? '<div class="legend-notes">"' + self.escapeHtml(ann.notes) + '"</div>' : '') +
          '<div class="legend-meta"><i class="far fa-clock me-1"></i>' + (ann.created_at ? new Date(ann.created_at).toLocaleTimeString() : "Just now") + '</div>' +
        '</div>' +
      '</div>';
    }).join("");
    legendItems.querySelectorAll(".legend-item").forEach(function(item) {
      item.addEventListener("click", function() {
        var id = item.dataset.id;
        var obj = annotations.find(function(a) { return a.annotationData.id === id; });
        if (obj) { self.canvas.setActiveObject(obj); self.canvas.renderAll(); }
      });
    });
  }

  highlightLegendItem(e) {
    this.container.querySelectorAll(".legend-item").forEach(function(item) { item.classList.remove("selected"); });
    if (e && e.selected && e.selected.length > 0) {
      var obj = e.selected[0];
      if (obj.annotationData) {
        var item = this.container.querySelector('.legend-item[data-id="' + obj.annotationData.id + '"]');
        if (item) item.classList.add("selected");
      }
    }
  }

  getTypeIcon(type) {
    var icons = { rect: '<i class="far fa-square"></i>', circle: '<i class="far fa-circle"></i>', arrow: '<i class="fas fa-long-arrow-alt-right"></i>', text: '<i class="fas fa-font"></i>', marker: '<i class="fas fa-map-marker-alt"></i>', freehand: '<i class="fas fa-pencil-alt"></i>' };
    return icons[type] || '<i class="fas fa-draw-polygon"></i>';
  }

  escapeHtml(text) {
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  loadAnnotations() {
    var self = this;
    if (!this.options.photoId) return;
    var url = this.options.getUrl + "?photo_id=" + this.options.photoId;
    fetch(url).then(function(res) { return res.json(); }).then(function(data) {
      if (data.success && Array.isArray(data.annotations) && data.annotations.length > 0) {
        self.fromJSON(data.annotations);
        self.setStatus("Loaded " + data.annotations.length + " annotations");
      } else {
        self.setStatus("Ready - no saved annotations");
      }
    }).catch(function(e) {
      console.error("Load annotations failed:", e);
      self.setStatus("Ready");
    });
  }

  save() {
    var self = this;
    if (!this.options.photoId) {
      this.setStatus("Error: No photo ID");
      return Promise.reject("No photo ID");
    }
    this.setStatus("Saving...");
    var annotations = this.toJSON();
    return fetch(this.options.saveUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ photo_id: this.options.photoId, annotations: annotations })
    }).then(function(res) { return res.json(); }).then(function(data) {
      if (data.success) {
        self.isDirty = false;
        self.setStatus("Saved " + annotations.length + " annotations");
        return Promise.resolve();
      }
      throw new Error(data.error || "Save failed");
    }).catch(function(e) {
      self.setStatus("Save failed: " + e.message);
      return Promise.reject(e);
    });
  }

  toJSON() {
    var self = this;
    return this.getAnnotationObjects().map(function(obj) {
      return {
        ...obj.annotationData,
        fabricData: {
          type: obj.type,
          left: obj.left / self.scale,
          top: obj.top / self.scale,
          width: (obj.width || 0) * (obj.scaleX || 1) / self.scale,
          height: (obj.height || 0) * (obj.scaleY || 1) / self.scale,
          stroke: obj.stroke,
          strokeWidth: obj.strokeWidth,
          fill: obj.fill,
          rx: obj.rx ? obj.rx / self.scale : undefined,
          ry: obj.ry ? obj.ry / self.scale : undefined,
          text: obj.text,
          fontSize: obj.fontSize
        }
      };
    });
  }

  fromJSON(annotations) {
    var self = this;
    if (!Array.isArray(annotations)) return;
    annotations.forEach(function(ann) {
      var fd = ann.fabricData || ann;
      var obj = null;
      var opts = {
        left: (fd.left || 0) * self.scale,
        top: (fd.top || 0) * self.scale,
        stroke: fd.stroke || ann.color || "#FF0000",
        strokeWidth: fd.strokeWidth || 3,
        fill: fd.fill || "transparent",
        selectable: !self.options.readonly,
        evented: !self.options.readonly
      };
      if (fd.type === "rect") {
        obj = new fabric.Rect({ ...opts, width: (fd.width || 50) * self.scale, height: (fd.height || 50) * self.scale });
      } else if (fd.type === "ellipse" || fd.type === "circle") {
        obj = new fabric.Ellipse({ ...opts, rx: (fd.rx || 25) * self.scale, ry: (fd.ry || 25) * self.scale });
      } else if (fd.type === "i-text" || fd.type === "text") {
        obj = new fabric.IText(fd.text || ann.label || "Note", { ...opts, fontSize: fd.fontSize || 16, fill: fd.stroke || ann.color || "#FF0000", backgroundColor: "rgba(255,255,255,0.9)" });
      } else if (fd.type === "group" || fd.type === "marker") {
        var c = new fabric.Circle({ radius: 14, fill: fd.stroke || ann.color || "#FF0000", originX: "center", originY: "center" });
        var n = new fabric.Text(ann.label && ann.label.replace ? ann.label.replace("Marker ", "") : "?", { fontSize: 14, fill: "#FFF", fontWeight: "bold", originX: "center", originY: "center" });
        obj = new fabric.Group([c, n], { left: opts.left, top: opts.top, selectable: opts.selectable, evented: opts.evented });
      }
      if (obj) {
        obj.annotationData = { id: ann.id, type: ann.type || fd.type, category: ann.category || "note", label: ann.label || "Annotation", notes: ann.notes || "", color: ann.color || fd.stroke, created_at: ann.created_at };
        self.canvas.add(obj);
      }
    });
    this.canvas.renderAll();
    this.updateLegend();
  }

  destroy() {
    if (this.canvas) this.canvas.dispose();
    this.container.innerHTML = "";
  }
}

if (typeof module !== "undefined" && module.exports) module.exports = ConditionAnnotator;
