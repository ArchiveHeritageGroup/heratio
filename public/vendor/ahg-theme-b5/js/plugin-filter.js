document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('[data-filter]');
    const pluginCards = document.querySelectorAll('.plugin-card');
    
    filterButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const filter = this.getAttribute('data-filter');
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            pluginCards.forEach(function(card) {
                if (filter === 'all' || card.getAttribute('data-category') === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
