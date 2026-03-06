/**
 * GLAM Identifier System
 * Handles identifier type selection, validation, lookup, and barcode generation
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.identifier-selector').forEach(initIdentifierSelector);
});

function initIdentifierSelector(container) {
    const objectId = container.dataset.objectId;
    const typeSelect = container.querySelector('.identifier-type-select');
    const valueInput = container.querySelector('.identifier-value-input');
    const iconSpan = container.querySelector('.identifier-icon i');
    const validateBtn = container.querySelector('.validate-btn');
    const lookupBtn = container.querySelector('.lookup-btn');
    const generateBtn = container.querySelector('.generate-barcode-btn');
    const feedbackDiv = container.querySelector('.validation-feedback');
    const lookupResults = container.querySelector('.lookup-results');
    const barcodeDisplay = container.querySelector('.barcode-display');

    let lookupData = null;

    // Update icon when type changes
    if (typeSelect) {
        typeSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const icon = option.dataset.icon || 'fa-barcode';
            if (iconSpan) {
                iconSpan.className = 'fa-solid ' + icon;
            }

            if (lookupBtn) {
                lookupBtn.classList.toggle('d-none', option.dataset.lookup !== '1');
            }

            if (feedbackDiv) {
                feedbackDiv.innerHTML = '';
                feedbackDiv.className = 'validation-feedback mt-1 small';
            }
        });
    }

    // Auto-detect on Enter (barcode scanner sends Enter)
    if (valueInput) {
        valueInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const value = this.value.trim();
                if (value && lookupBtn && !lookupBtn.classList.contains('d-none')) {
                    lookupBtn.click();
                } else if (value && validateBtn) {
                    validateBtn.click();
                }
            }
        });

        // Auto-detect identifier type on input
        valueInput.addEventListener('input', debounce(function() {
            const value = this.value.trim();
            if (value.length < 8) return;

            fetch('/api/identifier/detect?value=' + encodeURIComponent(value))
                .then(r => r.json())
                .then(data => {
                    if (data.detected_type && typeSelect) {
                        typeSelect.value = data.detected_type;
                        typeSelect.dispatchEvent(new Event('change'));

                        if (data.validation) {
                            showValidation(data.validation);
                        }
                    }
                })
                .catch(() => {});
        }, 300));
    }

    // Validate button
    if (validateBtn) {
        validateBtn.addEventListener('click', function() {
            const type = typeSelect ? typeSelect.value : 'identifier';
            const value = valueInput ? valueInput.value.trim() : '';

            if (!value) {
                showValidation({valid: false, message: 'Enter a value'});
                return;
            }

            this.disabled = true;
            fetch('/api/identifier/validate?type=' + type + '&value=' + encodeURIComponent(value))
                .then(r => r.json())
                .then(data => {
                    if (data.validation) {
                        showValidation(data.validation);
                        if (data.validation.normalized && valueInput) {
                            valueInput.value = data.validation.normalized;
                        }
                    }
                })
                .catch(() => showValidation({valid: false, message: 'Validation failed'}))
                .finally(() => { this.disabled = false; });
        });
    }

    // Lookup button
    if (lookupBtn) {
        lookupBtn.addEventListener('click', function() {
            const type = typeSelect ? typeSelect.value : 'isbn';
            const value = valueInput ? valueInput.value.trim() : '';

            if (!value) {
                showValidation({valid: false, message: 'Enter ISBN/ISSN to lookup'});
                return;
            }

            const lookupType = type === 'issn' ? 'issn' : 'isbn';

            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

            fetch('/api/identifier/lookup?type=' + lookupType + '&value=' + encodeURIComponent(value))
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.mapped) {
                        lookupData = data.mapped;
                        showLookupResults(data.mapped, data.raw);
                    } else {
                        showValidation({valid: false, message: data.message || 'No results'});
                    }
                })
                .catch(() => showValidation({valid: false, message: 'Lookup failed'}))
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fa-solid fa-search"></i>' +
                        '<span class="d-none d-md-inline ms-1">Lookup</span>';
                });
        });
    }

    // Generate barcode button
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            const type = typeSelect ? typeSelect.value : '';

            this.disabled = true;
            let url = '/api/identifier/barcode/' + objectId;
            if (type) url += '?type=' + type;

            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.barcode) {
                        showBarcodes(data.barcode);
                    } else {
                        showValidation({valid: false, message: data.error || 'Generation failed'});
                    }
                })
                .catch(() => showValidation({valid: false, message: 'Failed'}))
                .finally(() => { this.disabled = false; });
        });
    }

    function showValidation(result) {
        if (!feedbackDiv) return;
        const icon = result.valid ? 'check text-success' : 'times text-danger';
        feedbackDiv.innerHTML = '<i class="fa-solid fa-' + icon + ' me-1"></i>' + result.message;
        feedbackDiv.className = 'validation-feedback mt-1 small ' +
            (result.valid ? 'text-success' : 'text-danger');
    }

    function showLookupResults(mapped, raw) {
        if (!lookupResults) return;

        const content = lookupResults.querySelector('.lookup-content');
        let html = '<div class="row"><div class="col-md-8">';

        if (mapped.title) {
            html += '<p class="mb-1"><strong>Title:</strong> ' + escapeHtml(mapped.title) + '</p>';
        }
        if (mapped.creator) {
            html += '<p class="mb-1"><strong>Author:</strong> ' + escapeHtml(mapped.creator) + '</p>';
        }
        if (mapped.publisher) {
            html += '<p class="mb-1"><strong>Publisher:</strong> ' + escapeHtml(mapped.publisher) + '</p>';
        }
        if (mapped.date_of_publication) {
            html += '<p class="mb-1"><strong>Published:</strong> ' +
                escapeHtml(mapped.date_of_publication) + '</p>';
        }
        if (mapped.isbn) {
            html += '<p class="mb-1"><strong>ISBN:</strong> ' + escapeHtml(mapped.isbn) + '</p>';
        }

        html += '</div>';

        if (raw && raw.cover_url) {
            html += '<div class="col-md-4 text-center">' +
                '<img src="' + escapeHtml(raw.cover_url) + '" alt="Cover" ' +
                'class="img-fluid rounded" style="max-height:150px;"></div>';
        }

        html += '</div>';
        content.innerHTML = html;
        lookupResults.classList.remove('d-none');
    }

    function showBarcodes(data) {
        if (!barcodeDisplay) return;

        const linearDiv = barcodeDisplay.querySelector('.barcode-linear');
        const qrDiv = barcodeDisplay.querySelector('.barcode-qr');

        if (data.barcodes && data.barcodes.linear && data.barcodes.linear.svg && linearDiv) {
            linearDiv.innerHTML = data.barcodes.linear.svg;
        }
        if (data.barcodes && data.barcodes.qr && data.barcodes.qr.svg && qrDiv) {
            qrDiv.innerHTML = data.barcodes.qr.svg;
        }

        barcodeDisplay.classList.remove('d-none');
    }

    // Close lookup results
    container.querySelectorAll('.close-lookup').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (lookupResults) {
                lookupResults.classList.add('d-none');
            }
            lookupData = null;
        });
    });

    // Apply lookup to form
    const applyBtn = container.querySelector('.apply-lookup');
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            if (!lookupData) return;

            const fieldMap = {
                'title': ['title', 'edit-title'],
                'creator': ['creator', 'nameAccessPoints'],
                'publisher': ['publisher'],
                'isbn': ['isbn']
            };

            for (const key in fieldMap) {
                if (!lookupData[key]) continue;

                const selectors = fieldMap[key];
                for (let i = 0; i < selectors.length; i++) {
                    const sel = selectors[i];
                    const field = document.querySelector('[name="' + sel + '"], #' + sel);
                    if (field) {
                        field.value = lookupData[key];
                        field.dispatchEvent(new Event('change', {bubbles: true}));
                        break;
                    }
                }
            }

            if (lookupResults) {
                lookupResults.classList.add('d-none');
            }
            showValidation({valid: true, message: 'Applied to form'});
        });
    }

    // Print barcode
    const printBtn = container.querySelector('.print-barcode');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            const linearEl = barcodeDisplay ? barcodeDisplay.querySelector('.barcode-linear') : null;
            const qrEl = barcodeDisplay ? barcodeDisplay.querySelector('.barcode-qr') : null;
            const linearSvg = linearEl ? linearEl.innerHTML : '';
            const qrSvg = qrEl ? qrEl.innerHTML : '';

            const w = window.open('', '_blank');
            w.document.write('<html><head><title>Print Barcode</title></head>');
            w.document.write('<body style="text-align:center;padding:20px;">');
            w.document.write('<div>' + linearSvg + '</div><br><div>' + qrSvg + '</div>');
            w.document.write('</body></html>');
            w.document.close();
            w.print();
        });
    }
}

function debounce(fn, delay) {
    let timer;
    return function() {
        const args = arguments;
        const context = this;
        clearTimeout(timer);
        timer = setTimeout(function() {
            fn.apply(context, args);
        }, delay);
    };
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}
