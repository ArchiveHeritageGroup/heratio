
/* DAM Edit Form - Collapsible Fieldsets */
document.addEventListener('DOMContentLoaded', function() {
  // Handle collapsible fieldsets
  document.querySelectorAll('fieldset.collapsible legend').forEach(function(legend) {
    legend.addEventListener('click', function(e) {
      var fieldset = this.closest('fieldset');
      if (fieldset) {
        fieldset.classList.toggle('collapsed');
      }
    });
  });
});
