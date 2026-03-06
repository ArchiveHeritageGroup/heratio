(function() {
    'use strict';

    // Transform [identifier] Title (Level) to Title (identifier) - Level
    function transformText(text) {
        if (!text || text.indexOf('Select') !== -1 || text.indexOf('--') === 0) {
            return text;
        }
        
        var identifier = '';
        var title = text;
        var level = '';
        
        var idMatch = text.match(/^\[([^\]]+)\]\s*/);
        if (idMatch) {
            identifier = idMatch[1];
            title = text.substring(idMatch[0].length);
        }
        
        var lvlMatch = title.match(/\s*\(([^)]+)\)$/);
        if (lvlMatch) {
            level = lvlMatch[1];
            title = title.substring(0, title.length - lvlMatch[0].length).trim();
        }
        
        var result = title;
        if (identifier) result += ' (' + identifier + ')';
        if (level) result += ' - ' + level;
        
        return result;
    }

    // Transform all select options on page
    function transformAllSelects() {
        document.querySelectorAll('select option').forEach(function(opt) {
            if (/^\[[^\]]+\]/.test(opt.text) && !opt.getAttribute('data-transformed')) {
                opt.text = transformText(opt.text);
                opt.setAttribute('data-transformed', '1');
            }
        });
    }

    // Run immediately
    transformAllSelects();

    // Run on DOM ready
    document.addEventListener('DOMContentLoaded', transformAllSelects);

    // Watch for new selects
    var observer = new MutationObserver(function(mutations) {
        transformAllSelects();
    });

    if (document.body) {
        observer.observe(document.body, { childList: true, subtree: true });
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }

    // Add green theme styles
    var style = document.createElement('style');
    style.textContent = '\
.ts-wrapper .ts-control { border: 1px solid #ced4da; border-radius: 4px; }\
.ts-wrapper.focus .ts-control { border-color: #1d6f42; box-shadow: 0 0 0 3px rgba(29,111,66,0.25); }\
.ts-dropdown .option.active { background-color: #1d6f42 !important; color: #fff !important; }\
.ts-dropdown .option:hover:not(.active) { background-color: #e9f5ee; }\
';
    document.head.appendChild(style);

    console.log('Record select transformer loaded');
})();
