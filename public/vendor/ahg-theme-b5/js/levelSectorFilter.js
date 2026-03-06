/**
 * Level of Description Sector Filter
 */
(function() {
    'use strict';
    
    const sectorLevels = {
        archive: [236, 237, 238, 239, 240, 241, 242, 299, 434, 1704],
        dam: [1161, 1753, 1754, 1755, 1756, 1757, 1758],
        gallery: [512, 1750, 1753],
        library: [1161, 1700, 1701, 1702, 1703, 1704, 1759],
        museum: [500, 512, 1750, 1751, 1752, 1757]
    };
    
    // Slug to term ID - all lowercase
    const slugToId = {
        'fonds': 236, 'subfonds': 237, 'collection': 238, 'collection-2': 238,
        'series': 239, 'subseries': 240, 'file': 241, 'item': 242,
        'part': 299, 'record-group': 434,
        'material-cco': 500, 'object': 500,
        'technique-cco': 512, 'installation': 512,
        'document': 1161,
        'book': 1700, 'monograph': 1701, 'periodical': 1702, 
        'journal': 1703, 'manuscript': 1704,
        'artwork': 1750, 'artifact': 1751, 'specimen': 1752,
        'photograph': 1753, 'audio': 1754, 'video': 1755,
        'image': 1756, '3d-model': 1757, 'dataset': 1758, 'article': 1759,
        // Level-prefixed slugs for GLAM sectors
        'level-photograph': 1753, 'level-audio': 1754, 'level-video': 1755,
        'level-image': 1756, 'level-3d-model': 1757, 'level-dataset': 1758,
        'level-document': 1161, 'level-book': 1700, 'level-monograph': 1701,
        'level-periodical': 1702, 'level-journal': 1703, 'level-manuscript': 1704,
        'level-article': 1759, 'level-artwork': 1750, 'level-artifact': 1751,
        'level-specimen': 1752, 'level-object': 500, 'level-installation': 512,
        // Weird slugs from imports
        'enhanced-mushroom-141-jpg-8': 1753,
        'enhanced-mushroom-142-jpg-8': 1754,
        'enhanced-mushroom-jpg-8': 1752
    };
    
    const templateSector = {
        'isad': 'archive', 'rad': 'archive', 'dacs': 'archive', 'dc': 'archive',
        'mods': 'library', 'museum': 'museum', 'cco': 'museum',
        'cdwa': 'gallery', 'library': 'library', 'gallery': 'gallery', 'dam': 'dam'
    };
    
    function detectCurrentSector() {
        // Check data-sector attribute first (from server-side)
        const formWithSector = document.querySelector('[data-sector]');
        if (formWithSector && formWithSector.dataset.sector) {
            return formWithSector.dataset.sector;
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        const templateParam = urlParams.get('template');
        if (templateParam && templateSector[templateParam.toLowerCase()]) {
            return templateSector[templateParam.toLowerCase()];
        }
        const ds = document.querySelector('select[name*="displayStandard"]');
        if (ds && ds.selectedIndex > 0) {
            const text = (ds.options[ds.selectedIndex]?.text || '').toLowerCase();
            for (const [t, s] of Object.entries(templateSector)) {
                if (text.includes(t)) return s;
            }
        }
        return 'archive';
    }
    
    function extractTermIdFromUrl(url) {
        if (!url) return null;
        
        // Get last path segment
        const parts = url.split('/').filter(p => p && p !== 'index.php');
        const slug = parts[parts.length - 1];
        
        if (!slug) return null;
        
        // Try exact match (lowercase)
        const lowerSlug = slug.toLowerCase();
        if (slugToId[lowerSlug] !== undefined) {
            return slugToId[lowerSlug];
        }
        
        // Try partial match
        for (const [key, id] of Object.entries(slugToId)) {
            if (lowerSlug.includes(key) || key.includes(lowerSlug)) {
                return id;
            }
        }
        
        // Try extracting numeric ID from URL
        const numMatch = url.match(/\/(\d+)(?:\?|$)/);
        if (numMatch) {
            return parseInt(numMatch[1], 10);
        }
        
        console.warn('LevelSectorFilter: Cannot match slug:', slug, 'from URL:', url);
        return null;
    }
    
    function findLevelDropdowns() {
        const found = new Set();
        ['select[name*="levelOfDescription"]', 'select[name*="updateChildLevels"]'].forEach(sel => {
            document.querySelectorAll(sel).forEach(el => found.add(el));
        });
        return Array.from(found);
    }
    
    function filterLevelDropdown(sector) {
        const selects = findLevelDropdowns();
        if (!selects.length) return;
        
        const allowedIds = sectorLevels[sector] || [];
        console.log('LevelSectorFilter: sector=' + sector + ', allowedIds=', allowedIds);
        
        selects.forEach(select => {
            if (!select.dataset.originalOptions) {
                const options = [];
                Array.from(select.options).forEach(opt => {
                    const termId = extractTermIdFromUrl(opt.value);
                    options.push({ value: opt.value, text: opt.text, termId: termId });
                });
                select.dataset.originalOptions = JSON.stringify(options);
            }
            
            const originalOptions = JSON.parse(select.dataset.originalOptions);
            const currentValue = select.value;
            
            select.innerHTML = '';
            
            // Always add empty option first
            select.add(new Option('', ''));
            
            originalOptions.forEach(opt => {
                // Skip empty options (already added)
                if (!opt.value) return;
                
                // Skip unmatched options (termId is null)
                if (opt.termId === null) {
                    console.log('Skipping unmatched:', opt.text, opt.value);
                    return;
                }
                
                // Keep only if in allowed list
                if (allowedIds.includes(opt.termId)) {
                    select.add(new Option(opt.text, opt.value));
                }
            });
            
            if (currentValue) select.value = currentValue;
            console.log('Filtered to', select.options.length, 'options');
        });
    }
    
    function showAllLevels() {
        findLevelDropdowns().forEach(select => {
            if (select.dataset.originalOptions) {
                const opts = JSON.parse(select.dataset.originalOptions);
                const val = select.value;
                select.innerHTML = '';
                opts.forEach(o => select.add(new Option(o.text, o.value)));
                select.value = val;
            }
        });
    }
    
    function init() {
        setTimeout(() => {
            const sector = detectCurrentSector();
            filterLevelDropdown(sector);
            
            const ds = document.querySelector('select[name*="displayStandard"]');
            if (ds) ds.addEventListener('change', () => setTimeout(() => filterLevelDropdown(detectCurrentSector()), 100));
        }, 300);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    window.LevelSectorFilter = { detect: detectCurrentSector, filter: filterLevelDropdown, showAll: showAllLevels, sectorLevels };
})();
