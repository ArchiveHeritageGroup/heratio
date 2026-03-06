/**
 * Landing Page Builder - Drag & Drop Editor
 * 
 * Requires: Sortable.js (https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js)
 */

(function() {
    'use strict';

    const Builder = {
        container: null,
        palette: null,
        configPanel: null,
        sortable: null,
        currentBlockId: null,

        init: function() {
            this.container = document.getElementById('blocks-container');
            this.palette = document.getElementById('block-palette');
            this.configPanel = document.getElementById('config-panel');

            if (!this.container) return;

            this.initSortable();
            this.initPaletteDrag();
            this.bindEvents();
            this.updateBlockCount();
        },

        // Initialize sortable for reordering
        initSortable: function() {
            this.sortable = new Sortable(this.container, {
                animation: 150,
                handle: '.block-handle',
                ghostClass: 'block-ghost',
                chosenClass: 'block-chosen',
                dragClass: 'block-drag',
                filter: '.column-drop-zone, .nested-block',
                preventOnFilter: false,
                group: {
                    name: 'blocks',
                    pull: true,
                    put: ['blocks']
                },
                onEnd: (evt) => {
                    this.saveOrder();
                    this.hideEmptyMessage();
                }
            });
        },

        // Initialize palette drag with Sortable
        initPaletteDrag: function() {
            // Use Sortable on each category collapse for drag to column zones
            this.palette.querySelectorAll('.collapse').forEach(collapse => {
                new Sortable(collapse, {
                    group: {
                        name: 'palette',
                        pull: 'clone',
                        put: false
                    },
                    draggable: '.block-type-item',
                    sort: false,
                    animation: 150
                });
            });

            const items = this.palette.querySelectorAll('.block-type-item');
            
            items.forEach(item => {
                item.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('block-type-id', item.dataset.typeId);
                    e.dataTransfer.setData('machine-name', item.dataset.machineName);
                    item.classList.add('dragging');
                });

                item.addEventListener('dragend', (e) => {
                    item.classList.remove('dragging');
                });

                // Double-click to add
                item.addEventListener('dblclick', () => {
                    this.addBlock(item.dataset.typeId);
                });
            });

            // Drop zone handling
            this.container.addEventListener('dragover', (e) => {
                e.preventDefault();
                this.container.classList.add('drop-active');
            });

            this.container.addEventListener('dragleave', () => {
                this.container.classList.remove('drop-active');
            });

            this.container.addEventListener('drop', (e) => {
                e.preventDefault();
                this.container.classList.remove('drop-active');
                
                // Skip if dropped on a column zone (let column handler deal with it)
                if (e.target.closest('.column-drop-zone')) {
                    return;
                }
                
                const blockTypeId = e.dataTransfer.getData('block-type-id');
                if (blockTypeId) {
                    this.addBlock(blockTypeId);
                }
            });
        },

        // Bind UI events
        bindEvents: function() {
            // Block actions (delegated)
            this.container.addEventListener('click', (e) => {
                // Handle nested block buttons first
                if (e.target.closest('.btn-edit-nested')) {
                    const blockId = e.target.closest('.btn-edit-nested').dataset.blockId;
                    this.openConfig(blockId);
                    return;
                }
                if (e.target.closest('.btn-delete-nested')) {
                    const nestedBlock = e.target.closest('.nested-block');
                    const blockId = e.target.closest('.btn-delete-nested').dataset.blockId;
                    this.deleteBlock(blockId, nestedBlock);
                    return;
                }
                // Handle regular block card buttons
                const card = e.target.closest('.block-card');
                if (!card) return;
                const blockId = card.dataset.blockId;
                if (e.target.closest('.btn-edit')) {
                    this.openConfig(blockId);
                } else if (e.target.closest('.btn-delete')) {
                    this.deleteBlock(blockId, card);
                } else if (e.target.closest('.btn-duplicate')) {
                    this.duplicateBlock(blockId);
                } else if (e.target.closest('.btn-visibility')) {
                    this.toggleVisibility(blockId, card);
                }
            });

            // Close config panel
            document.getElementById('close-config')?.addEventListener('click', () => {
                this.closeConfig();
            });

            // Page settings form
            document.getElementById('page-settings-form')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.savePageSettings(new FormData(e.target));
            });

            // Slug preview
            document.querySelector('input[name="slug"]')?.addEventListener('input', (e) => {
                document.getElementById('slug-preview').textContent = e.target.value || 'slug';
            });

            // Delete page
            document.getElementById('btn-delete-page')?.addEventListener('click', () => {
                this.deletePage();
            });

            // Preview
            document.getElementById('btn-preview')?.addEventListener('click', (e) => {
                window.open(e.target.closest('button').dataset.url, '_blank');
            });

            // Save draft
            document.getElementById('btn-save-draft')?.addEventListener('click', () => {
                this.saveDraft();
            });

            // Publish
            document.getElementById('btn-publish')?.addEventListener('click', () => {
                this.publish();
            });

            // Version restore
            document.querySelectorAll('.version-restore').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.restoreVersion(link.dataset.versionId);
                });
            });

            // Collapse/expand all 
            document.getElementById('btn-collapse-all')?.addEventListener('click', () => {
                this.container.querySelectorAll('.block-preview').forEach(el => {
                    el.style.display = 'none';
                });
            });

            document.getElementById('btn-expand-all')?.addEventListener('click', () => {
                this.container.querySelectorAll('.block-preview').forEach(el => {
                    el.style.display = 'block';
                });
            });
        },

        // =================================================================
        // BLOCK OPERATIONS
        // =================================================================

        // Add new block
        addBlock: async function(blockTypeId) {
            const formData = new FormData();
            formData.append('page_id', LandingPageBuilder.pageId);
            formData.append('block_type_id', blockTypeId);

            try {
                const response = await fetch(LandingPageBuilder.urls.addBlock, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    this.appendBlockCard(result.block);
                    this.hideEmptyMessage();
                    this.updateBlockCount();
                    this.showToast('Block added', 'success');
                } else {
                    this.showToast(result.error || 'Failed to add block', 'danger');
                }
            } catch (error) {
                console.error('Add block error:', error);
                this.showToast('Failed to add block', 'danger');
            }
        },

        // Append block card to canvas
        appendBlockCard: function(block) {
            const template = document.getElementById("block-card-template");
            const clone = template.content.cloneNode(true);
            const card = clone.querySelector(".block-card");
            card.dataset.blockId = block.id;
            card.querySelector(".block-icon").classList.add(block.type_icon);
            card.querySelector(".block-label").textContent = block.type_label;
            
            // Check if column layout - add drop zones
            const columnLayouts = ["row_1_col", "row_2_col", "row_3_col"];
            if (columnLayouts.includes(block.machine_name)) {
                const numCols = block.machine_name === "row_3_col" ? 3 : (block.machine_name === "row_2_col" ? 2 : 1);
                let colHtml = "<div class=\"row g-2\">";
                for (let i = 1; i <= numCols; i++) {
                    colHtml += `<div class="col">
                        <div class="column-drop-zone border border-2 border-dashed rounded p-2 text-center"
                             data-parent-block="${block.id}"
                             data-column="col${i}"
                             style="min-height: 80px; background: #fff;">
                            <div class="empty-column text-muted py-2">
                                <small>⬇ Col ${i}</small>
                            </div>
                        </div>
                    </div>`;
                }
                colHtml += "</div>";
                card.querySelector(".block-preview").innerHTML = colHtml;
            }
            
            this.container.appendChild(clone);
            
            // Re-initialize column drop zones
            if (window.ColumnDropZones && typeof window.ColumnDropZones.init === "function") {
                setTimeout(() => window.ColumnDropZones.init(), 100);
            }
            
            // Open config immediately for new blocks
            setTimeout(() => this.openConfig(block.id), 150);
        },

        // Delete block
        deleteBlock: async function(blockId, cardElement) {
            if (!confirm('Delete this block?')) return;

            const formData = new FormData();
            formData.append('block_id', blockId);

            try {
                const response = await fetch(LandingPageBuilder.urls.deleteBlock, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    cardElement.remove();
                    this.updateBlockCount();
                    this.showEmptyMessageIfNeeded();
                    
                    if (this.currentBlockId === blockId) {
                        this.closeConfig();
                    }
                    
                    this.showToast('Block deleted', 'success');
                } else {
                    this.showToast(result.error || 'Failed to delete', 'danger');
                }
            } catch (error) {
                console.error('Delete error:', error);
                this.showToast('Failed to delete block', 'danger');
            }
        },

        // Duplicate block
        duplicateBlock: async function(blockId) {
            const formData = new FormData();
            formData.append('block_id', blockId);

            try {
                const response = await fetch(LandingPageBuilder.urls.duplicateBlock, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Reload page to show duplicated block
                    location.reload();
                } else {
                    this.showToast(result.error || 'Failed to duplicate', 'danger');
                }
            } catch (error) {
                console.error('Duplicate error:', error);
                this.showToast('Failed to duplicate block', 'danger');
            }
        },

        // Toggle visibility
        toggleVisibility: async function(blockId, cardElement) {
            const formData = new FormData();
            formData.append('block_id', blockId);

            try {
                const response = await fetch(LandingPageBuilder.urls.toggleVisibility, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    const btn = cardElement.querySelector('.btn-visibility');
                    const icon = btn.querySelector('i');
                    
                    if (result.is_visible) {
                        cardElement.classList.remove('block-hidden');
                        btn.classList.remove('text-warning');
                        btn.classList.add('text-muted');
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    } else {
                        cardElement.classList.add('block-hidden');
                        btn.classList.remove('text-muted');
                        btn.classList.add('text-warning');
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                }
            } catch (error) {
                console.error('Toggle visibility error:', error);
            }
        },

        // Save block order
        saveOrder: async function() {
            const blocks = this.container.querySelectorAll('.block-card');
            const order = Array.from(blocks).map(card => parseInt(card.dataset.blockId));

            const formData = new FormData();
            formData.append('page_id', LandingPageBuilder.pageId);
            formData.append('order', JSON.stringify(order));

            try {
                await fetch(LandingPageBuilder.urls.reorderBlocks, {
                    method: 'POST',
                    body: formData
                });
            } catch (error) {
                console.error('Reorder error:', error);
            }
        },

        // =================================================================
        // CONFIGURATION PANEL
        // =================================================================

        // Open config panel
        openConfig: async function(blockId) {
            this.currentBlockId = blockId;

            try {
                const response = await fetch(`${LandingPageBuilder.urls.getBlockConfig}?block_id=${blockId}`);
                const result = await response.json();

                if (result.success) {
                    this.renderConfigForm(result.block);
                    this.configPanel.style.display = 'block';
                    
                    // Highlight active card
                    this.container.querySelectorAll('.block-card').forEach(c => c.classList.remove('border-primary'));
                    this.container.querySelector(`[data-block-id="${blockId}"]`)?.classList.add('border-primary');
                }
            } catch (error) {
                console.error('Get config error:', error);
            }
        },

        // Render config form
        renderConfigForm: function(block) {
            const container = document.getElementById('config-form-container');
            document.getElementById('config-title').textContent = block.type_label;

            let html = `<form id="block-config-form" data-block-id="${block.id}">`;

            // Block title (optional override)
            html += `
                <div class="mb-3">
                    <label class="form-label">Block Title (optional)</label>
                    <input type="text" name="title" class="form-control form-control-sm" 
                           value="${this.escapeHtml(block.title || '')}" 
                           placeholder="Override default label">
                </div>
            `;

            // Block-specific config fields
            const schema = block.config_schema || {};
            const config = block.config || {};

            for (const [key, field] of Object.entries(schema)) {
                html += this.renderConfigField(key, field, config[key]);
            }

            // Style settings accordion
            html += `
                <div class="accordion mt-3" id="styleAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed py-2" type="button" 
                                    data-bs-toggle="collapse" data-bs-target="#styleSettings">
                                <i class="bi bi-palette me-2"></i> Style Settings
                            </button>
                        </h2>
                        <div id="styleSettings" class="accordion-collapse collapse" 
                             data-bs-parent="#styleAccordion">
                            <div class="accordion-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">Container</label>
                                        <select name="container_type" class="form-select form-select-sm">
                                            <option value="container" ${block.container_type === 'container' ? 'selected' : ''}>Container</option>
                                            <option value="container-lg" ${block.container_type === 'container-lg' ? 'selected' : ''}>Container Large</option>
                                            <option value="fluid" ${block.container_type === 'fluid' ? 'selected' : ''}>Full Width</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">CSS Classes</label>
                                        <input type="text" name="css_classes" class="form-control form-control-sm" 
                                               value="${this.escapeHtml(block.css_classes || '')}">
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label small">Background Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="color" name="background_color" class="form-control form-control-color" 
                                                   value="${block.background_color || '#ffffff'}" style="width: 40px;">
                                            <input type="text" class="form-control" 
                                                   value="${block.background_color || '#ffffff'}" 
                                                   id="bg_color_text" readonly>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Text Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="color" name="text_color" class="form-control form-control-color" 
                                                   value="${block.text_color || '#212529'}" style="width: 40px;">
                                            <input type="text" class="form-control" 
                                                   value="${block.text_color || '#212529'}" 
                                                   id="text_color_text" readonly>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label small">Padding Top</label>
                                        <select name="padding_top" class="form-select form-select-sm">
                                            ${[0,1,2,3,4,5].map(n => `<option value="${n}" ${block.padding_top == n ? 'selected' : ''}>${n}</option>`).join('')}
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small">Padding Bottom</label>
                                        <select name="padding_bottom" class="form-select form-select-sm">
                                            ${[0,1,2,3,4,5].map(n => `<option value="${n}" ${block.padding_bottom == n ? 'selected' : ''}>${n}</option>`).join('')}
                                        </select>
                                    </div>
                                
                                <div class="row g-2 mt-3">
                                    <div class="col-12">
                                        <label class="form-label small"><i class="bi bi-grid-3x3-gap me-1"></i>Column Span</label>
                                        <select name="col_span" class="form-select form-select-sm">
                                            <option value="1" ${block.col_span == 1 ? 'selected' : ''}>1 Column (default)</option>
                                            <option value="2" ${block.col_span == 2 ? 'selected' : ''}>2 Columns</option>
                                            <option value="3" ${block.col_span == 3 ? 'selected' : ''}>3 Columns</option>
                                            <option value="4" ${block.col_span == 4 ? 'selected' : ''}>4 Columns</option>
                                            <option value="6" ${block.col_span == 6 ? 'selected' : ''}>6 Columns (Half)</option>
                                            <option value="12" ${block.col_span == 12 ? 'selected' : ''}>12 Columns (Full Width)</option>
                                        </select>
                                        <small class="text-muted">Number of grid columns this block spans (Bootstrap 12-column grid)</small>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            html += `
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </form>`;

            container.innerHTML = html;

            // Bind color picker sync
            container.querySelectorAll('input[type="color"]').forEach(colorInput => {
                colorInput.addEventListener('input', (e) => {
                    const textInput = e.target.nextElementSibling;
                    if (textInput) textInput.value = e.target.value;
                });
            });

            // Bind form submit
            document.getElementById('block-config-form').addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveBlockConfig(new FormData(e.target));
            });
        },

        // Render individual config field
        renderConfigField: function(key, field, value) {
            const label = field.label || key;
            const type = field.type || 'text';
            value = value ?? '';

            let html = `<div class="mb-3">`;

            switch (type) {
                case 'text':
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="text" name="config[${key}]" class="form-control form-control-sm" 
                               value="${this.escapeHtml(value)}">
                    `;
                    break;

                case 'textarea':
                case 'richtext':
                    html += `
                        <label class="form-label small">${label}</label>
                        <textarea name="config[${key}]" class="form-control form-control-sm" 
                                  rows="4">${this.escapeHtml(value)}</textarea>
                    `;
                    break;

                case 'number':
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="number" name="config[${key}]" class="form-control form-control-sm" 
                               value="${value}" min="${field.min || ''}" max="${field.max || ''}">
                    `;
                    break;

                case 'checkbox':
                    html += `
                        <div class="form-check">
                            <input type="checkbox" name="config[${key}]" class="form-check-input" 
                                   id="config_${key}" ${value ? 'checked' : ''} value="1">
                            <label class="form-check-label small" for="config_${key}">${label}</label>
                        </div>
                    `;
                    break;

                case 'select':
                    html += `
                        <label class="form-label small">${label}</label>
                        <select name="config[${key}]" class="form-select form-select-sm">
                            ${(field.options || []).map(opt => {
                                const optVal = typeof opt === 'object' ? opt.value : opt;
                                const optLabel = typeof opt === 'object' ? opt.label : opt;
                                return `<option value="${optVal}" ${value == optVal ? 'selected' : ''}>${optLabel}</option>`;
                            }).join('')}
                        </select>
                    `;
                    break;

                case 'range':
                    html += `
                        <label class="form-label small">${label}: <span id="${key}_value">${value}</span></label>
                        <input type="range" name="config[${key}]" class="form-range" 
                               value="${value}" min="${field.min || 0}" max="${field.max || 100}" 
                               step="${field.step || 1}"
                               oninput="document.getElementById('${key}_value').textContent = this.value">
                    `;
                    break;

                case 'color':
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="color" name="config[${key}]" class="form-control form-control-sm form-control-color" 
                               value="${value || '#000000'}">
                    `;
                    break;

                case 'image':
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="text" name="config[${key}]" class="form-control form-control-sm" 
                               value="${this.escapeHtml(value)}" placeholder="Image URL">
                        <div class="form-text small">Enter image URL or path</div>
                        ${value ? `<img src="${this.escapeHtml(value)}" class="img-thumbnail mt-2" style="max-height: 80px;">` : ''}
                    `;
                    break;

                case 'icon':
                    html += `
                        <label class="form-label small">${label}</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi ${value || 'bi-square'}"></i></span>
                            <select name="config[${key}]" class="form-select form-select-sm icon-select" 
                                    onchange="this.previousElementSibling.querySelector('i').className = 'bi ' + this.value">
                                ${this.getIconOptions(value)}
                            </select>
                        </div>
                    `;
                    break;

                case 'repeater':
                    html += this.renderRepeaterField(key, field, value);
                    break;

                case 'entity_picker':
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="text" name="config[${key}]" class="form-control form-control-sm" 
                               value="${Array.isArray(value) ? value.join(',') : value}" 
                               placeholder="Enter IDs (comma-separated)">
                        <div class="form-text small">Entity type: ${field.entity_types?.join(', ') || 'any'}</div>
                    `;
                    break;

                default:
                    html += `
                        <label class="form-label small">${label}</label>
                        <input type="text" name="config[${key}]" class="form-control form-control-sm" 
                               value="${this.escapeHtml(value)}">
                    `;
            }

            html += `</div>`;
            return html;
        },

        // Render repeater field (for panels, stats, links)
        renderRepeaterField: function(key, field, values) {
            values = Array.isArray(values) ? values : [];
            const fields = field.fields || {};
            
            let html = `
                <label class="form-label small">${field.label || key}</label>
                <div class="repeater-container border rounded p-2" id="repeater_${key}">
            `;

            values.forEach((item, index) => {
                html += this.renderRepeaterItem(key, fields, item, index);
            });

            html += `
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2 add-repeater-item" 
                        data-key="${key}">
                    <i class="bi bi-plus"></i> Add Item
                </button>
            `;

            return html;
        },

        // Render single repeater item
        renderRepeaterItem: function(key, fields, item, index) {
            let html = `
                <div class="repeater-item border-bottom pb-2 mb-2" data-index="${index}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Item ${index + 1}</small>
                        <button type="button" class="btn btn-link btn-sm text-danger p-0 remove-repeater-item">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
            `;

            for (const [fieldKey, fieldDef] of Object.entries(fields)) {
                const fieldValue = item?.[fieldKey] || '';
                const fieldType = fieldDef.type || 'text';
                
                html += `<div class="mb-2">`;
                
                if (fieldType === 'select') {
                    html += `
                        <select name="config[${key}][${index}][${fieldKey}]" class="form-select form-select-sm">
                            ${(fieldDef.options || []).map(opt => 
                                `<option value="${opt}" ${fieldValue == opt ? 'selected' : ''}>${opt}</option>`
                            ).join('')}
                        </select>
                    `;
                } else if (fieldType === 'icon') {
                    html += `
                        <select name="config[${key}][${index}][${fieldKey}]" class="form-select form-select-sm">
                            ${this.getIconOptions(fieldValue)}
                        </select>
                    `;
                } else if (fieldType === 'checkbox') {
                    html += `
                        <div class="form-check">
                            <input type="checkbox" name="config[${key}][${index}][${fieldKey}]" 
                                   class="form-check-input" ${fieldValue ? 'checked' : ''} value="1">
                            <label class="form-check-label small">${fieldKey}</label>
                        </div>
                    `;
                } else {
                    html += `
                        <input type="text" name="config[${key}][${index}][${fieldKey}]" 
                               class="form-control form-control-sm" 
                               value="${this.escapeHtml(fieldValue)}" 
                               placeholder="${fieldKey}">
                    `;
                }
                
                html += `</div>`;
            }

            html += `</div>`;
            return html;
        },

        // Get icon select options
        getIconOptions: function(selected) {
            const icons = [
                'bi-archive', 'bi-building', 'bi-image', 'bi-file-text', 'bi-folder',
                'bi-people', 'bi-tags', 'bi-geo-alt', 'bi-calendar', 'bi-clock',
                'bi-search', 'bi-star', 'bi-heart', 'bi-bookmark', 'bi-link',
                'bi-globe', 'bi-envelope', 'bi-telephone', 'bi-camera', 'bi-film',
                'bi-music-note', 'bi-book', 'bi-journal', 'bi-newspaper', 'bi-collection',
                'bi-grid', 'bi-list', 'bi-card-image', 'bi-images', 'bi-map',
                'bi-info-circle', 'bi-question-circle', 'bi-exclamation-circle',
                'bi-box', 'bi-briefcase', 'bi-house', 'bi-graph-up', 'bi-pie-chart',
                'bi-bar-chart', 'bi-cpu', 'bi-database', 'bi-cloud', 'bi-shield',
                'bi-lock', 'bi-key', 'bi-person', 'bi-person-circle', 'bi-cash',
                'bi-credit-card', 'bi-cart', 'bi-bag', 'bi-truck', 'bi-airplane'
            ];
            
            return icons.map(icon => 
                `<option value="${icon}" ${selected === icon ? 'selected' : ''}>${icon.replace('bi-', '')}</option>`
            ).join('');
        },

        // Save block configuration
        saveBlockConfig: async function(formData) {
            const blockId = this.currentBlockId;
            
            // Build config object from form data
            const config = {};
            const repeaters = {};
            
            for (const [key, value] of formData.entries()) {
                if (key.startsWith('config[')) {
                    // Parse nested config keys
                    const matches = key.match(/config\[([^\]]+)\](?:\[(\d+)\]\[([^\]]+)\])?/);
                    if (matches) {
                        const [, configKey, index, subKey] = matches;
                        
                        if (index !== undefined && subKey) {
                            // Repeater field
                            if (!repeaters[configKey]) repeaters[configKey] = [];
                            if (!repeaters[configKey][index]) repeaters[configKey][index] = {};
                            repeaters[configKey][index][subKey] = value;
                        } else {
                            // Regular field
                            config[configKey] = value;
                        }
                    }
                }
            }

            for (const [key, items] of Object.entries(repeaters)) {
                config[key] = items.filter(item => item !== undefined);
            }
            // Handle checkboxes - set true/false based on checked state
            const form = document.getElementById("block-config-form");
            form.querySelectorAll("input[type=checkbox][name^=config]").forEach(cb => {
                const matches = cb.name.match(/config\[([^\]]+)\]/);
                if (matches) {
                    config[matches[1]] = cb.checked;
                }
            });
            console.log("Config before submit:", config);
            const submitData = new FormData();
            submitData.append('block_id', blockId);
            submitData.append('config', JSON.stringify(config));
            submitData.append('title', formData.get('title') || '');
            submitData.append('container_type', formData.get('container_type') || 'container');
            submitData.append('css_classes', formData.get('css_classes') || '');
            submitData.append('background_color', formData.get('background_color') || '');
            submitData.append('text_color', formData.get('text_color') || '');
            submitData.append('padding_top', formData.get('padding_top') || '3');
            submitData.append('padding_bottom', formData.get('padding_bottom') || '3');
            submitData.append('col_span', formData.get('col_span') || '1');

            try {
                const response = await fetch(LandingPageBuilder.urls.updateBlock, {
                    method: 'POST',
                    body: submitData
                });
                const result = await response.json();

                if (result.success) {
                    this.showToast('Block saved', 'success');
                    // Update card label if title changed
                    const card = this.container.querySelector(`[data-block-id="${blockId}"]`);
                    if (card) {
                        const label = card.querySelector(".block-label");
                        if (label && formData.get("title")) {
                            label.textContent = formData.get("title");
                        }
                    }
                } else {
                    this.showToast(result.error || "Failed to save", "danger");
                }
            } catch (error) {
                console.error('Save config error:', error);
                this.showToast('Failed to save block', 'danger');
            }
        },

        // Close config panel
        closeConfig: function() {
            this.configPanel.style.display = 'none';
            this.currentBlockId = null;
            this.container.querySelectorAll('.block-card').forEach(c => c.classList.remove('border-primary'));
        },

        // =================================================================
        // PAGE OPERATIONS
        // =================================================================

        // Save page settings
        savePageSettings: async function(formData) {
            try {
                const response = await fetch(LandingPageBuilder.urls.updateSettings, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    this.showToast('Settings saved', 'success');
                    bootstrap.Offcanvas.getInstance(document.getElementById('pageSettingsPanel'))?.hide();
                } else {
                    this.showToast(result.error || 'Failed to save', 'danger');
                }
            } catch (error) {
                console.error('Save settings error:', error);
                this.showToast('Failed to save settings', 'danger');
            }
        },

        // Delete page
        deletePage: async function() {
            if (!confirm('Are you sure you want to delete this page? This action cannot be undone.')) return;

            const formData = new FormData();
            formData.append('id', LandingPageBuilder.pageId);

            try {
                const response = await fetch(LandingPageBuilder.urls.deletePage, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    window.location.href = LandingPageBuilder.urls.listPage;
                } else {
                    this.showToast(result.error || 'Failed to delete', 'danger');
                }
            } catch (error) {
                console.error('Delete page error:', error);
                this.showToast('Failed to delete page', 'danger');
            }
        },

        // Save draft
        saveDraft: async function() {
            const formData = new FormData();
            formData.append('page_id', LandingPageBuilder.pageId);

            try {
                const response = await fetch(LandingPageBuilder.urls.saveDraft, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    this.showToast('Draft saved', 'success');
                } else {
                    this.showToast(result.error || 'Failed to save draft', 'danger');
                }
            } catch (error) {
                console.error('Save draft error:', error);
                this.showToast('Failed to save draft', 'danger');
            }
        },

        // Publish page
        publish: async function() {
            if (!confirm('Publish this page? It will become visible to the public.')) return;

            const formData = new FormData();
            formData.append('page_id', LandingPageBuilder.pageId);

            try {
                const response = await fetch(LandingPageBuilder.urls.publish, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    this.showToast('Page published successfully!', 'success');
                } else {
                    this.showToast(result.error || 'Failed to publish', 'danger');
                }
            } catch (error) {
                console.error('Publish error:', error);
                this.showToast('Failed to publish', 'danger');
            }
        },

        // Restore version
        restoreVersion: async function(versionId) {
            if (!confirm('Restore this version? Current layout will be replaced.')) return;

            const formData = new FormData();
            formData.append('version_id', versionId);

            try {
                const response = await fetch(LandingPageBuilder.urls.restoreVersion, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    this.showToast(result.error || 'Failed to restore', 'danger');
                }
            } catch (error) {
                console.error('Restore error:', error);
                this.showToast('Failed to restore version', 'danger');
            }
        },

        // =================================================================
        // UI HELPERS
        // =================================================================

        hideEmptyMessage: function() {
            const empty = document.getElementById('empty-message');
            if (empty) empty.style.display = 'none';
        },

        showEmptyMessageIfNeeded: function() {
            const blocks = this.container.querySelectorAll('.block-card');
            const empty = document.getElementById('empty-message');
            if (blocks.length === 0 && empty) {
                empty.style.display = 'block';
            }
        },

        updateBlockCount: function() {
            const count = this.container.querySelectorAll('.block-card').length;
            const counter = document.getElementById('block-count');
            if (counter) counter.textContent = `(${count} blocks)`;
        },

        showToast: function(message, type = 'info') {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-bg-${type} border-0 show`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${this.escapeHtml(message)}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                            data-bs-dismiss="toast"></button>
                </div>
            `;

            // Get or create toast container
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }

            container.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        escapeHtml: function(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', () => Builder.init());

    // Expose for external access
    window.LandingPageBuilderUI = Builder;
})();
