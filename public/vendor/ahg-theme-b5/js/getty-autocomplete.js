/**
 * Getty Vocabulary Autocomplete
 * Adds Getty AAT/TGN/ULAN suggestions to taxonomy term fields
 */
(function($) {
    'use strict';

    var GettyAutocomplete = {
        debounceTimer: null,
        minChars: 3,
        
        init: function() {
            // Find subject, place, and name input fields
            this.attachToFields();
        },

        attachToFields: function() {
            var self = this;
            
            // Target autocomplete inputs for subjects, places, actors
            $(document).on('keyup', '.form-autocomplete input[type="text"]', function(e) {
                var $input = $(this);
                var query = $input.val();
                
                // Determine vocabulary based on field
                var vocabulary = self.detectVocabulary($input);
                
                if (query.length >= self.minChars) {
                    clearTimeout(self.debounceTimer);
                    self.debounceTimer = setTimeout(function() {
                        self.search(query, vocabulary, $input);
                    }, 300);
                } else {
                    self.hideDropdown($input);
                }
            });

            // Hide dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.getty-dropdown').length) {
                    $('.getty-dropdown').remove();
                }
            });
        },

        detectVocabulary: function($input) {
            var fieldName = $input.attr('name') || '';
            var fieldId = $input.attr('id') || '';
            var label = $input.closest('.form-group').find('label').text().toLowerCase();
            
            if (label.indexOf('place') !== -1 || fieldName.indexOf('place') !== -1) {
                return 'tgn';
            }
            if (label.indexOf('creator') !== -1 || label.indexOf('actor') !== -1 || 
                fieldName.indexOf('actor') !== -1 || fieldName.indexOf('name') !== -1) {
                return 'ulan';
            }
            // Default to AAT for subjects, materials, techniques, etc.
            return 'aat';
        },

        search: function(query, vocabulary, $input) {
            var self = this;
            
            $.ajax({
                url: '/index.php/ahgMuseumPlugin/gettyAutocomplete',
                data: {
                    q: query,
                    vocabulary: vocabulary,
                    limit: 8
                },
                dataType: 'json',
                success: function(data) {
                    if (data.results && data.results.length > 0) {
                        self.showDropdown(data.results, $input, vocabulary);
                    } else {
                        self.hideDropdown($input);
                    }
                },
                error: function() {
                    self.hideDropdown($input);
                }
            });
        },

        showDropdown: function(results, $input, vocabulary) {
            var self = this;
            
            // Remove existing dropdown
            this.hideDropdown($input);
            
            var $dropdown = $('<div class="getty-dropdown"></div>');
            $dropdown.css({
                position: 'absolute',
                zIndex: 9999,
                backgroundColor: '#fff',
                border: '1px solid #ccc',
                borderRadius: '4px',
                boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
                maxHeight: '300px',
                overflowY: 'auto',
                width: $input.outerWidth()
            });

            var vocabColors = {
                'AAT': '#17a2b8',
                'TGN': '#28a745',
                'ULAN': '#6f42c1'
            };

            $.each(results, function(i, result) {
                var $item = $('<div class="getty-item"></div>');
                $item.css({
                    padding: '8px 12px',
                    cursor: 'pointer',
                    borderBottom: '1px solid #eee'
                });
                
                $item.html(
                    '<div style="display: flex; justify-content: space-between; align-items: center;">' +
                        '<strong>' + self.escapeHtml(result.label) + '</strong>' +
                        '<span class="badge" style="background-color: ' + vocabColors[result.vocabulary] + '; color: #fff; font-size: 0.7em;">' + result.vocabulary + '</span>' +
                    '</div>' +
                    (result.scopeNote ? '<small style="color: #666;">' + self.escapeHtml(result.scopeNote) + '</small>' : '')
                );
                
                $item.data('getty', result);
                
                $item.on('mouseenter', function() {
                    $(this).css('backgroundColor', '#f5f5f5');
                }).on('mouseleave', function() {
                    $(this).css('backgroundColor', '#fff');
                });
                
                $item.on('click', function() {
                    self.selectResult(result, $input);
                });
                
                $dropdown.append($item);
            });

            // Add "Powered by Getty" footer
            var $footer = $('<div style="padding: 5px 12px; background: #f8f9fa; font-size: 0.75em; color: #666; text-align: right;">Powered by Getty Vocabularies</div>');
            $dropdown.append($footer);

            // Position dropdown
            var offset = $input.offset();
            $dropdown.css({
                top: offset.top + $input.outerHeight(),
                left: offset.left
            });

            $('body').append($dropdown);
        },

        hideDropdown: function($input) {
            $('.getty-dropdown').remove();
        },

        selectResult: function(result, $input) {
            // Set the input value to the Getty preferred label
            $input.val(result.label);
            
            // Store the Getty URI in a hidden field or data attribute
            var $hidden = $input.siblings('input[name$="_getty_uri"]');
            if ($hidden.length === 0) {
                $hidden = $('<input type="hidden" name="' + $input.attr('name') + '_getty_uri">');
                $input.after($hidden);
            }
            $hidden.val(result.uri);

            // Add visual indicator
            var $badge = $input.siblings('.getty-selected-badge');
            if ($badge.length === 0) {
                $badge = $('<span class="getty-selected-badge badge bg-info ms-2" style="font-size: 0.8em;"></span>');
                $input.after($badge);
            }
            $badge.text(result.vocabulary).attr('title', result.uri);

            this.hideDropdown($input);
            
            // Trigger change event
            $input.trigger('change');
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        GettyAutocomplete.init();
    });

})(jQuery);
