/**
 * AHG Central JavaScript Helpers
 * @author Johan Pieterse <johan@theahg.co.za>
 */
var AhgHelpers = (function() {
    'use strict';
    var helpers = {};

    helpers.formatBytes = function(bytes, precision) {
        precision = precision || 2;
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(precision)) + ' ' + sizes[i];
    };

    helpers.formatDate = function(dateString, format) {
        if (!dateString) return '';
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        format = format || 'YYYY-MM-DD';
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return format.replace('YYYY', year).replace('MM', month).replace('DD', day);
    };

    helpers.formatDateTime = function(dateString) {
        if (!dateString) return '';
        var date = new Date(dateString);
        if (isNaN(date.getTime())) return '';
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');
        return helpers.formatDate(dateString) + ' ' + hours + ':' + minutes;
    };

    helpers.timeAgo = function(dateString) {
        if (!dateString) return '';
        var date = new Date(dateString);
        var now = new Date();
        var diff = Math.floor((now - date) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return helpers.formatDate(dateString);
    };

    helpers.escapeHtml = function(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    helpers.truncate = function(text, length, suffix) {
        length = length || 100;
        suffix = suffix || '...';
        if (!text || text.length <= length) return text || '';
        return text.substring(0, length - suffix.length) + suffix;
    };

    helpers.debounce = function(func, wait) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() { func.apply(context, args); }, wait);
        };
    };

    helpers.exportTableToCSV = function(tableId, filename) {
        var table = document.getElementById(tableId);
        if (!table) { console.error('Table not found:', tableId); return; }
        var csv = [];
        var rows = table.querySelectorAll('tr');
        rows.forEach(function(row) {
            var cols = row.querySelectorAll('td, th');
            var rowData = [];
            cols.forEach(function(col) {
                var text = col.innerText.replace(/"/g, '""');
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(','));
        });
        var csvContent = '\ufeff' + csv.join('\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = filename || 'export.csv';
        link.click();
    };

    helpers.toggleColumn = function(colIndex, tableId) {
        tableId = tableId || 'reportTable';
        var table = document.getElementById(tableId);
        if (!table) return;
        var cells = table.querySelectorAll('tr > *:nth-child(' + (colIndex + 1) + ')');
        cells.forEach(function(cell) {
            cell.style.display = cell.style.display === 'none' ? '' : 'none';
        });
    };

    helpers.showToast = function(message, type) {
        type = type || 'info';
        var colors = { success: '#28a745', error: '#dc3545', warning: '#ffc107', info: '#17a2b8' };
        var toast = document.createElement('div');
        toast.innerHTML = message;
        toast.style.cssText = 'position:fixed;top:20px;right:20px;padding:15px 25px;border-radius:4px;z-index:9999;background:' + 
            (colors[type] || colors.info) + ';color:' + (type === 'warning' ? '#000' : '#fff') + ';';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 3000);
    };

    helpers.copyToClipboard = function(text, callback) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                if (callback) callback(true);
            }).catch(function() { if (callback) callback(false); });
        } else {
            var textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            var success = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (callback) callback(success);
        }
    };

    return helpers;
})();

// Backward compatibility
window.formatBytes = AhgHelpers.formatBytes;
window.exportTableToCSV = AhgHelpers.exportTableToCSV;
window.toggleColumn = AhgHelpers.toggleColumn;
