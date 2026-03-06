/**
 * Base JS for AHG Theme - Nested dropdown support
 */
document.addEventListener('DOMContentLoaded', function() {
  // Handle nested dropdowns (dropend inside dropdown/dropup)
  document.querySelectorAll('.dropdown-menu .dropend > .dropdown-toggle').forEach(function(toggle) {
    
    // Prevent default link behavior and toggle submenu on click
    toggle.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      var submenu = this.nextElementSibling;
      if (!submenu || !submenu.classList.contains('dropdown-menu')) return;
      
      // Close other open submenus at same level
      var parent = this.closest('.dropdown-menu');
      if (parent) {
        parent.querySelectorAll('.dropend > .dropdown-menu.show').forEach(function(openMenu) {
          if (openMenu !== submenu) {
            openMenu.classList.remove('show');
          }
        });
      }
      
      // Toggle this submenu
      submenu.classList.toggle('show');
      
      // Position adjustment for dropup (menu opens upward)
      var dropup = this.closest('.dropup');
      if (dropup && submenu.classList.contains('show')) {
        submenu.style.top = 'auto';
        submenu.style.bottom = '0';
        submenu.style.left = '100%';
      }
    });
    
    // Also handle hover for better UX
    toggle.parentElement.addEventListener('mouseenter', function() {
      var submenu = this.querySelector('.dropdown-menu');
      if (submenu) {
        submenu.classList.add('show');
        
        // Position for dropup
        var dropup = this.closest('.dropup');
        if (dropup) {
          submenu.style.top = 'auto';
          submenu.style.bottom = '0';
          submenu.style.left = '100%';
        }
      }
    });
    
    toggle.parentElement.addEventListener('mouseleave', function() {
      var submenu = this.querySelector('.dropdown-menu');
      if (submenu) {
        submenu.classList.remove('show');
      }
    });
  });
  
  // Close nested menus when parent dropdown closes
  document.querySelectorAll('.dropdown, .dropup').forEach(function(dropdown) {
    dropdown.addEventListener('hidden.bs.dropdown', function() {
      this.querySelectorAll('.dropend > .dropdown-menu.show').forEach(function(submenu) {
        submenu.classList.remove('show');
      });
    });
  });
  
  console.log('AHG Base JS: Nested dropdown handlers initialized');
});
