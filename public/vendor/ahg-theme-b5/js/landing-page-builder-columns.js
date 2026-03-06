/**
 * Column Drop Zone Handling for Landing Page Builder
 * Uses Sortable.js for consistent drag/drop with main builder
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initColumnDropZones();
    });

    function initColumnDropZones() {
        const dropZones = document.querySelectorAll('.column-drop-zone');
        
        if (dropZones.length === 0) {
            console.log('No column drop zones found');
            return;
        }

        console.log('Initializing', dropZones.length, 'column drop zones');

        dropZones.forEach(function(zone) {
            // Initialize Sortable on each drop zone
            new Sortable(zone, {
                group: {
                    name: 'columns',
                    pull: true,
                    put: ['palette', 'columns', 'blocks']
                },
                animation: 150,
                ghostClass: 'drop-ghost',
                onAdd: function(evt) {
                    const item = evt.item;
                    const dropZone = evt.to;
                    const parentBlockId = dropZone.dataset.parentBlock;
                    const columnSlot = dropZone.dataset.column;
                    
                    console.log('Sortable onAdd:', item, 'to zone:', dropZone, 'parent:', parentBlockId, 'slot:', columnSlot);
                    
                    // Check if it's from palette (has block-type-id)
                    const blockTypeId = item.dataset.typeId;
                    if (blockTypeId) {
                        console.log('Adding from palette:', blockTypeId);
                        // Remove the dragged clone
                        item.remove();
                        addBlockToColumn(blockTypeId, parentBlockId, columnSlot);
                        return;
                    }
                    
                    // Moving existing nested block
                    const blockId = item.dataset.blockId;
                    if (blockId) {
                        console.log('Moving block:', blockId);
                        item.remove();
                        moveBlockToColumn(blockId, parentBlockId, columnSlot);
                    }
                }
            });
            
            // Visual feedback on drag over
            zone.addEventListener('dragenter', function(e) {
                this.style.borderColor = '#0d6efd';
                this.style.backgroundColor = '#e7f1ff';
            });
            
            zone.addEventListener('dragleave', function(e) {
                this.style.borderColor = '';
                this.style.backgroundColor = '#fff';
            });
            
            zone.addEventListener('drop', function(e) {
                this.style.borderColor = '';
                this.style.backgroundColor = '#fff';
            });
        });

        // Make nested blocks draggable within columns
        const nestedBlocks = document.querySelectorAll('.nested-block');
        nestedBlocks.forEach(function(block) {
            block.setAttribute('draggable', 'true');
        });
    }

    function addBlockToColumn(blockTypeId, parentBlockId, columnSlot) {
        if (!window.LandingPageBuilder) {
            console.error('LandingPageBuilder config not found');
            return;
        }

        const formData = new FormData();
        formData.append('page_id', LandingPageBuilder.pageId);
        formData.append('block_type_id', blockTypeId);
        formData.append('parent_block_id', parentBlockId);
        formData.append('column_slot', columnSlot);

        console.log('Sending addBlock request:', {
            page_id: LandingPageBuilder.pageId,
            block_type_id: blockTypeId,
            parent_block_id: parentBlockId,
            column_slot: columnSlot
        });

        fetch(LandingPageBuilder.urls.addBlock, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            console.log('addBlock result:', result);
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to add block to column');
            }
        })
        .catch(error => {
            console.error('addBlock error:', error);
            alert('Failed to add block: ' + error.message);
        });
    }

    function moveBlockToColumn(blockId, parentBlockId, columnSlot) {
        const formData = new FormData();
        formData.append('block_id', blockId);
        formData.append('parent_block_id', parentBlockId);
        formData.append('column_slot', columnSlot);

        fetch(LandingPageBuilder.urls.moveToColumn || '/admin/landing-pages/ajax/move-to-column', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to move block');
            }
        })
        .catch(error => {
            console.error('moveBlock error:', error);
        });
    }

    // Expose for debugging
    window.ColumnDropZones = {
        init: initColumnDropZones,
        addBlockToColumn: addBlockToColumn,
        moveBlockToColumn: moveBlockToColumn
    };
})();

// Handle nested block edit and delete buttons
document.addEventListener('click', function(e) {
    // Edit nested block
    if (e.target.closest('.btn-edit-nested')) {
        const btn = e.target.closest('.btn-edit-nested');
        const blockId = btn.dataset.blockId;
        console.log('Edit nested block:', blockId);
        
        // Use main builder's edit modal
        if (window.LandingPageBuilderUI && window.LandingPageBuilderUI.openEditModal) {
            window.LandingPageBuilderUI.openEditModal(blockId);
        } else {
        }
        e.preventDefault();
        e.stopPropagation();
        return;
    }
    
    // Delete nested block
    if (e.target.closest('.btn-delete-nested')) {
        const btn = e.target.closest('.btn-delete-nested');
        const blockId = btn.dataset.blockId;
        console.log('Delete nested block:', blockId);
        
        if (!confirm('Delete this block?')) return;
        
        const formData = new FormData();
        formData.append('block_id', blockId);
        
        fetch(window.LandingPageBuilder.urls.deleteBlock, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                location.reload();
            } else {
                alert(result.error || 'Failed to delete block');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            alert('Failed to delete block');
        });
        
        e.preventDefault();
        e.stopPropagation();
    }
});
