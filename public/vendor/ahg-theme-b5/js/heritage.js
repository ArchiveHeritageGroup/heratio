/**
 * Heritage Platform JavaScript
 *
 * Modern discovery interface for archival collections.
 */

(function() {
    'use strict';

    // Heritage App namespace
    window.HeritageApp = window.HeritageApp || {};

    /**
     * Initialize Heritage features.
     */
    HeritageApp.init = function(options) {
        options = options || {};

        // Initialize hero image rotation
        if (document.getElementById('heritage-hero')) {
            HeritageApp.initHeroRotation(options.heroEffect || 'kenburns', options.rotationSeconds || 8);
        }

        // Initialize autocomplete
        if (document.getElementById('heritage-search-input')) {
            HeritageApp.initAutocomplete();
        }

        // Initialize recent additions scroll
        if (document.querySelector('.heritage-recent-scroll')) {
            HeritageApp.initRecentScroll();
        }

        // Initialize smooth scroll for anchor links
        HeritageApp.initSmoothScroll();
    };

    /**
     * Hero image rotation.
     */
    HeritageApp.initHeroRotation = function(effect, intervalSeconds) {
        var backgrounds = document.querySelectorAll('.heritage-hero-bg');
        var caption = document.getElementById('heritage-hero-caption');
        var captionText = document.getElementById('caption-text');
        var captionCollection = document.getElementById('caption-collection');

        if (backgrounds.length <= 1) {
            return;
        }

        var currentIndex = 0;
        var heroImages = [];

        // Collect image data
        backgrounds.forEach(function(bg, index) {
            heroImages.push({
                caption: bg.getAttribute('data-caption') || '',
                collection: bg.getAttribute('data-collection') || ''
            });
        });

        // Rotation function
        function rotate() {
            // Fade out current
            backgrounds[currentIndex].classList.remove('active');

            // Move to next
            currentIndex = (currentIndex + 1) % backgrounds.length;

            // Fade in next
            backgrounds[currentIndex].classList.add('active');

            // Update caption if exists
            if (captionText && heroImages[currentIndex]) {
                captionText.textContent = heroImages[currentIndex].caption;
            }
            if (captionCollection && heroImages[currentIndex]) {
                captionCollection.textContent = heroImages[currentIndex].collection;
            }
        }

        // Start rotation
        setInterval(rotate, intervalSeconds * 1000);
    };

    /**
     * Search autocomplete.
     */
    HeritageApp.initAutocomplete = function() {
        var input = document.getElementById('heritage-search-input');
        var dropdown = document.getElementById('heritage-autocomplete');

        if (!input || !dropdown) {
            return;
        }

        var debounceTimer;
        var minChars = 2;

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);

            var query = input.value.trim();

            if (query.length < minChars) {
                dropdown.classList.add('d-none');
                dropdown.innerHTML = '';
                return;
            }

            debounceTimer = setTimeout(function() {
                fetchAutocomplete(query);
            }, 300);
        });

        input.addEventListener('blur', function() {
            // Delay to allow click on dropdown item
            setTimeout(function() {
                dropdown.classList.add('d-none');
            }, 200);
        });

        function fetchAutocomplete(query) {
            fetch('/heritage/api/autocomplete?q=' + encodeURIComponent(query))
                .then(function(response) {
                    return response.json();
                })
                .then(function(result) {
                    if (result.success && result.data && result.data.length > 0) {
                        renderSuggestions(result.data);
                    } else {
                        dropdown.classList.add('d-none');
                        dropdown.innerHTML = '';
                    }
                })
                .catch(function(error) {
                    console.error('Autocomplete error:', error);
                    dropdown.classList.add('d-none');
                });
        }

        function renderSuggestions(suggestions) {
            dropdown.innerHTML = '';

            suggestions.forEach(function(suggestion) {
                var item = document.createElement('div');
                item.className = 'heritage-autocomplete-item';
                item.textContent = suggestion;
                item.addEventListener('click', function() {
                    input.value = suggestion;
                    dropdown.classList.add('d-none');
                    input.form.submit();
                });
                dropdown.appendChild(item);
            });

            dropdown.classList.remove('d-none');
        }
    };

    /**
     * Recent additions horizontal scroll.
     */
    HeritageApp.initRecentScroll = function() {
        var container = document.querySelector('.heritage-recent-scroll');
        var scrollArea = container.querySelector('.d-flex');
        var prevBtn = container.querySelector('.heritage-scroll-prev');
        var nextBtn = container.querySelector('.heritage-scroll-next');

        if (!scrollArea || !prevBtn || !nextBtn) {
            return;
        }

        var scrollAmount = 200;

        prevBtn.addEventListener('click', function() {
            scrollArea.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
        });

        nextBtn.addEventListener('click', function() {
            scrollArea.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
        });

        // Update button visibility based on scroll position
        function updateButtons() {
            prevBtn.style.opacity = scrollArea.scrollLeft > 0 ? '1' : '0.3';
            nextBtn.style.opacity =
                scrollArea.scrollLeft < (scrollArea.scrollWidth - scrollArea.clientWidth - 10)
                    ? '1' : '0.3';
        }

        scrollArea.addEventListener('scroll', updateButtons);
        window.addEventListener('resize', updateButtons);
        updateButtons();
    };

    /**
     * Smooth scroll for anchor links.
     */
    HeritageApp.initSmoothScroll = function() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (href === '#') return;

                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    };

    /**
     * Animated counter for stats.
     */
    HeritageApp.animateCounter = function(element) {
        var target = parseInt(element.dataset.count, 10);
        var duration = 2000;
        var step = target / (duration / 16);
        var current = 0;

        function update() {
            current += step;
            if (current < target) {
                element.textContent = Math.floor(current).toLocaleString();
                requestAnimationFrame(update);
            } else {
                element.textContent = target.toLocaleString();
            }
        }

        update();
    };

    /**
     * Lazy load images.
     */
    HeritageApp.lazyLoadImages = function() {
        if ('IntersectionObserver' in window) {
            var lazyImages = document.querySelectorAll('img[loading="lazy"]');
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src || img.src;
                        imageObserver.unobserve(img);
                    }
                });
            });

            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        }
    };

    /**
     * Click tracking for search results.
     * Tracks when users click on search results to improve ranking.
     */
    HeritageApp.initClickTracking = function() {
        var searchId = HeritageApp.currentSearchId;
        var searchTime = Date.now();

        // Listen for clicks on search result links
        document.querySelectorAll('.heritage-result-item a[data-item-id]').forEach(function(link, index) {
            link.addEventListener('click', function(e) {
                var itemId = parseInt(link.dataset.itemId, 10);
                var position = index + 1;
                var timeToClick = Date.now() - searchTime;

                // Send click tracking asynchronously
                HeritageApp.trackClick(searchId, itemId, position, timeToClick);
            });
        });
    };

    /**
     * Track a click on a search result.
     */
    HeritageApp.trackClick = function(searchId, itemId, position, timeToClick) {
        if (!searchId || !itemId) {
            return;
        }

        // Use navigator.sendBeacon for reliable tracking even on navigation
        var data = JSON.stringify({
            search_id: searchId,
            item_id: itemId,
            position: position,
            time_to_click: timeToClick
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon('/heritage/api/click', new Blob([data], { type: 'application/json' }));
        } else {
            // Fallback to fetch
            fetch('/heritage/api/click', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true
            }).catch(function() {});
        }
    };

    /**
     * Track dwell time when user returns from viewing an item.
     */
    HeritageApp.trackDwell = function(clickId, dwellTime) {
        if (!clickId || dwellTime < 1) {
            return;
        }

        var data = JSON.stringify({
            click_id: clickId,
            dwell_time: dwellTime
        });

        if (navigator.sendBeacon) {
            navigator.sendBeacon('/heritage/api/dwell', new Blob([data], { type: 'application/json' }));
        } else {
            fetch('/heritage/api/dwell', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: data,
                keepalive: true
            }).catch(function() {});
        }
    };

    /**
     * Set search context for click tracking.
     */
    HeritageApp.setSearchContext = function(searchId) {
        HeritageApp.currentSearchId = searchId;

        // Initialize click tracking if on search results page
        if (document.querySelector('.heritage-results')) {
            HeritageApp.initClickTracking();
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            HeritageApp.init();
        });
    } else {
        HeritageApp.init();
    }

})();
