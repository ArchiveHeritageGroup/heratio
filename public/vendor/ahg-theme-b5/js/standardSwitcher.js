(function($) {
  'use strict';

  window.StandardSwitcher = {
    init: function() {
      var $selector = $('#displayStandard');
      if (!$selector.length) return;

      $selector.on('change', function() {
        StandardSwitcher.loadFields($(this).val());
      });
    },

    loadFields: function(standard) {
      var resourceId = $('input[name="id"]').val() || '';
      var $container = $('#dynamicFieldsContainer');
      
      // Show loading indicator
      $container.html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Loading ' + standard.toUpperCase() + ' fields...</div>');

      $.ajax({
        url: '/index.php/informationobject/loadStandardFields',
        data: {
          standard: standard,
          id: resourceId
        },
        type: 'GET',
        success: function(html) {
          $container.html(html);
          // Re-initialize any dynamic components
          StandardSwitcher.initializeComponents();
        },
        error: function() {
          $container.html('<div class="alert alert-danger">Error loading fields. Please try again.</div>');
        }
      });

      // Also update hidden field for saving
      $('input[name="displayStandard"]').val(standard);
    },

    initializeComponents: function() {
      // Re-initialize autocomplete fields
      if (typeof jQuery.fn.autocomplete !== 'undefined') {
        $('.form-autocomplete').each(function() {
          var $input = $(this);
          var url = $input.data('autocomplete-url');
          if (url) {
            $input.autocomplete({
              source: url,
              minLength: 2
            });
          }
        });
      }

      // Re-initialize collapsible fieldsets
      $('.collapsible legend').off('click').on('click', function() {
        $(this).parent().toggleClass('collapsed');
      });

      // Re-initialize resizable textareas
      $('.resizable').each(function() {
        $(this).css('resize', 'vertical');
      });
    }
  };

  $(document).ready(function() {
    StandardSwitcher.init();
  });

})(jQuery);
