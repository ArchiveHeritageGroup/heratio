@extends('theme::layouts.1col')

@push('css')
<link rel="stylesheet" href="/css/heritage-landing.css">
<style>
:root {
    --heritage-primary: {{ $primaryColor ?? '#0d6efd' }};
}
</style>
@endpush

@section('content')
<div class="heritage-landing" style="margin: 0; width: 100%; max-width: none;">

    <!-- ================================================================
         Section 1: Hero (Full Viewport)
         ================================================================ -->
    <section class="heritage-hero" id="heritage-hero" style="height: calc(100vh - 280px); min-height: 450px;">

        <!-- Background Images -->
        <div class="heritage-hero-backgrounds">
            @if(!empty($heroImages))
                @foreach($heroImages as $index => $image)
                <div class="heritage-hero-bg {{ $index === 0 ? 'active' : '' }} {{ ($image['ken_burns'] ?? 1) ? 'kenburns' : '' }}"
                     data-index="{{ $index }}"
                     data-duration="{{ $image['display_duration'] ?? 8 }}"
                     style="background-image: url('{{ $image['image_path'] ?? '' }}');">
                </div>
                @endforeach
            @else
                <div class="heritage-hero-bg active" style="background: linear-gradient(135deg, var(--heritage-primary) 0%, #1a1a2e 100%);"></div>
            @endif
        </div>

        <!-- Gradient Overlay -->
        <div class="heritage-hero-overlay"></div>

        <!-- Content -->
        <div class="heritage-hero-content">
            <h1 class="heritage-hero-tagline">{{ $tagline }}</h1>

            @if($subtext)
            <p class="heritage-hero-subtext">{{ $subtext }}</p>
            @endif

            <!-- Search Box -->
            <form action="{{ url('/heritage/search') }}" method="get" class="heritage-search-box">
                <input type="text"
                       name="q"
                       placeholder="{{ $searchPlaceholder }}"
                       autocomplete="off"
                       id="heritage-search-input">
                <button type="submit" aria-label="Search">
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <!-- Suggested Searches -->
            @if(!empty($suggestedSearches))
            <div class="heritage-suggested-searches">
                <span>Try:</span>
                @foreach($suggestedSearches as $search)
                <a href="{{ url('/heritage/search') }}?q={{ urlencode($search) }}">
                    {{ $search }}
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <!-- Scroll Indicator -->
        <a href="#heritage-explore" class="heritage-scroll-indicator">
            <span>Explore</span>
            <i class="fas fa-chevron-down"></i>
        </a>
    </section>

    <!-- ================================================================
         Section 2: Explore By (Category Buttons)
         ================================================================ -->
    <section class="heritage-explore-by" id="heritage-explore">
        <div class="heritage-section-label">Explore By</div>
        <div class="heritage-explore-buttons">
            <a href="{{ url('/heritage/timeline') }}" class="heritage-explore-btn">
                <i class="fas fa-clock"></i> Time
            </a>
            <a href="{{ url('/heritage/explore') }}?category=place" class="heritage-explore-btn">
                <i class="fas fa-map-marker-alt"></i> Place
            </a>
            <a href="{{ url('/heritage/creators') }}" class="heritage-explore-btn">
                <i class="fas fa-users"></i> People
            </a>
            <a href="{{ url('/heritage/explore') }}?category=theme" class="heritage-explore-btn">
                <i class="fas fa-tag"></i> Theme
            </a>
            <a href="{{ url('/heritage/explore') }}?category=format" class="heritage-explore-btn">
                <i class="fas fa-layer-group"></i> Format
            </a>
            <a href="{{ url('/heritage/trending') }}" class="heritage-explore-btn">
                <i class="fas fa-chart-line"></i> Trending
            </a>
            <a href="{{ url('/heritage/graph') }}" class="heritage-explore-btn">
                <i class="fas fa-project-diagram"></i> Knowledge Graph
            </a>
        </div>
    </section>

    <!-- ================================================================
         Section 3: Curated Collections (IIIF + Archival Collections)
         ================================================================ -->
    @php
    $placeholderGradients = [
        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
        'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
    ];
    @endphp
    @if(!empty($curatedCollections))
    <section class="heritage-collections">
        <div class="heritage-section-header">
            <h2 class="heritage-section-title">Curated Collections</h2>
            <a href="{{ route('informationobject.browse') }}" class="heritage-view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="heritage-carousel heritage-carousel-autorotate" data-autorotate="5000">
            <button class="heritage-carousel-arrow left" aria-label="Previous" data-carousel="collections-track" data-scroll="-320">
                <i class="fas fa-chevron-left"></i>
            </button>

            <div class="heritage-carousel-track" id="collections-track">
                @foreach($curatedCollections as $index => $collection)
                @php
                if (($collection['type'] ?? 'iiif') === 'archival') {
                    $collectionUrl = url('/informationobject/browse') . '?collection=' . $collection['id'];
                } else {
                    $collectionUrl = route('iiif-collection.view', ['id' => $collection['id']]);
                }
                $collectionIcon = ($collection['type'] ?? 'iiif') === 'archival' ? 'fa-archive' : 'fa-layer-group';
                @endphp
                <a href="{{ $collectionUrl }}" class="heritage-collection-card">
                    <div class="heritage-card-images single-image">
                        @if(!empty($collection['thumbnail']))
                        <img class="main-image"
                             src="{{ $collection['thumbnail'] }}"
                             alt="{{ $collection['name'] }}"
                             loading="lazy"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="main-image fallback-gradient" style="background: {{ $placeholderGradients[$index % 6] }}; height: 280px; display: none; align-items: center; justify-content: center;">
                            <i class="fas {{ $collectionIcon }}" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        @else
                        <div class="main-image" style="background: {{ $placeholderGradients[$index % 6] }}; height: 280px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas {{ $collectionIcon }}" style="font-size: 3rem; color: rgba(255,255,255,0.5);"></i>
                        </div>
                        @endif
                    </div>
                    <div class="heritage-card-body">
                        <h3 class="heritage-card-title">{{ $collection['name'] ?? 'Untitled Collection' }}</h3>
                        <p class="heritage-card-subtitle">{{ substr($collection['description'] ?? '', 0, 60) }}{{ strlen($collection['description'] ?? '') > 60 ? '...' : '' }}</p>
                        <span class="heritage-card-count">
                            <i class="fas {{ $collectionIcon }} me-1" style="font-size: 0.75em;"></i>
                            {{ number_format($collection['item_count'] ?? 0) }} items
                        </span>
                    </div>
                </a>
                @endforeach
            </div>

            <button class="heritage-carousel-arrow right" aria-label="Next" data-carousel="collections-track" data-scroll="320">
                <i class="fas fa-chevron-right"></i>
            </button>

            <!-- Carousel indicators -->
            <div class="heritage-carousel-indicators" id="collections-indicators"></div>
        </div>
    </section>
    @endif

    <!-- ================================================================
         Section 4: Browse by Creator
         ================================================================ -->
    <section class="heritage-creators">
        <div class="heritage-section-header">
            <h2 class="heritage-section-title">Browse by Creator</h2>
            <a href="{{ url('/heritage/creators') }}" class="heritage-view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="heritage-creators-track" id="creators-track">
            @if($creators->count() > 0)
                @foreach($creators as $creator)
                @php
                    $initial = strtoupper(substr($creator->name, 0, 1));
                    $avatarSvg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect fill='%23667eea' width='100' height='100' rx='50'/%3E%3Ctext x='50' y='62' font-size='40' text-anchor='middle' fill='white' font-family='system-ui'%3E{$initial}%3C/text%3E%3C/svg%3E";
                @endphp
                <a href="{{ route('actor.show', ['slug' => $creator->slug]) }}" class="heritage-creator-card">
                    <img class="heritage-creator-avatar"
                         src="{{ $avatarSvg }}"
                         alt="{{ $creator->name }}">
                    <div class="heritage-creator-name">{{ $creator->name }}</div>
                    <div class="heritage-creator-count">{{ number_format($creator->item_count) }} items</div>
                </a>
                @endforeach
            @else
                <p class="text-muted text-center w-100 py-4">No creators found</p>
            @endif
        </div>
    </section>

    <!-- ================================================================
         Section 5: Interactive Timeline
         ================================================================ -->
    <section class="heritage-timeline">
        <div class="heritage-section-header">
            <h2 class="heritage-section-title">Explore by Time</h2>
            <a href="{{ url('/heritage/timeline') }}" class="heritage-view-all">
                Full Timeline <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        @if($timelinePeriods->count() > 0)
        @php $periodCount = $timelinePeriods->count(); @endphp
        <div class="heritage-timeline-bar">
            @foreach($timelinePeriods as $index => $period)
            @php
                $position = $periodCount > 1 ? ($index / ($periodCount - 1)) * 100 : 50;
            @endphp
            <a href="{{ url('/heritage/timeline') }}?period_id={{ $period->id }}"
               class="heritage-timeline-marker"
               style="left: {{ $position }}%;"
               title="{{ $period->name }}">
                <div class="heritage-timeline-label">
                    <span class="heritage-period-name">{{ $period->short_name ?? $period->name }}</span>
                    <span class="heritage-period-years">{{ $period->start_year }}{{ $period->end_year ? '-' . ($period->end_year > 2000 ? 'Present' : $period->end_year) : '+' }}</span>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </section>

    <!-- ================================================================
         Section 6: Recently Added (Masonry Grid)
         ================================================================ -->
    <section class="heritage-recent">
        <div class="heritage-section-header">
            <h2 class="heritage-section-title">Recently Added</h2>
            <a href="{{ url('/heritage/search') }}?sort=recent" class="heritage-view-all">
                View All <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <div class="heritage-masonry">
            @if($recentItems->count() > 0)
                @foreach($recentItems as $item)
                @php
                    $thumbPath = null;
                    if (!empty($item->thumb_child_path) && !empty($item->thumb_child_name)) {
                        $thumbPath = rtrim($item->thumb_child_path, '/') . '/' . $item->thumb_child_name;
                    } elseif (!empty($item->image_path) && !empty($item->image_name)) {
                        $mime = $item->mime_type ?? '';
                        if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'application/pdf')) {
                            $basePath = $item->image_path;
                            $basename = pathinfo($item->image_name, PATHINFO_FILENAME);
                            $candidate = $basePath . $basename . '_142.jpg';
                            $rootDir = config('heratio.uploads_path', '/usr/share/nginx/archive');
                            if (file_exists($rootDir . $candidate)) {
                                $thumbPath = $candidate;
                            }
                        }
                    }
                @endphp
                @if($thumbPath)
                <a href="{{ route('informationobject.show', ['slug' => $item->slug]) }}" class="heritage-masonry-item">
                    <img src="{{ $thumbPath }}"
                         alt="{{ $item->title ?? 'Item' }}"
                         onerror="this.parentElement.style.display='none';">
                    <div class="heritage-masonry-overlay">
                        <h4 class="heritage-masonry-title">{{ $item->title ?? 'Untitled' }}</h4>
                    </div>
                </a>
                @endif
                @endforeach
            @else
                <p class="text-muted text-center w-100 py-4" style="column-span: all;">No recent items with images found</p>
            @endif
        </div>
    </section>

    <!-- ================================================================
         Section 7: Help Us Improve (Contributions CTA)
         ================================================================ -->
    <section class="heritage-contribute">
        <div class="heritage-contribute-inner">
            <h2 class="heritage-contribute-title">Help Us Preserve History</h2>
            <p class="heritage-contribute-subtitle">
                Join our community of contributors helping to document and preserve our shared heritage.
            </p>

            <div class="heritage-cta-cards">
                <div class="heritage-cta-card">
                    <div class="heritage-cta-icon"><i class="fas fa-file-alt"></i></div>
                    <h3 class="heritage-cta-title">Transcribe</h3>
                    <p class="heritage-cta-description">Help make handwritten documents searchable by transcribing them.</p>
                    <a href="{{ url('/heritage/login') }}" class="heritage-cta-button">Start Transcribing</a>
                </div>

                <div class="heritage-cta-card">
                    <div class="heritage-cta-icon"><i class="fas fa-id-badge"></i></div>
                    <h3 class="heritage-cta-title">Identify</h3>
                    <p class="heritage-cta-description">Help identify people, places, and objects in historical photographs.</p>
                    <a href="{{ url('/heritage/login') }}" class="heritage-cta-button">Help Identify</a>
                </div>

                <div class="heritage-cta-card">
                    <div class="heritage-cta-icon"><i class="fas fa-book"></i></div>
                    <h3 class="heritage-cta-title">Add Context</h3>
                    <p class="heritage-cta-description">Share your knowledge about local history and personal memories.</p>
                    <a href="{{ url('/heritage/login') }}" class="heritage-cta-button">Share Stories</a>
                </div>
            </div>

            <!-- Leaderboard -->
            <div class="heritage-leaderboard">
                <h4 class="heritage-leaderboard-title">Top Contributors This Month</h4>
                <div class="heritage-leaderboard-row">
                    @if($topContributors->count() > 0)
                        @foreach($topContributors as $contributor)
                        <div class="heritage-leaderboard-item">
                            <img class="heritage-leaderboard-avatar"
                                 src="{{ $contributor->avatar_url ?? '' }}"
                                 alt=""
                                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 40 40%22><rect fill=%22%23fff3%22 width=%2240%22 height=%2240%22 rx=%2220%22/></svg>';">
                            <div>
                                <div class="heritage-leaderboard-name">{{ $contributor->display_name }}</div>
                                <div class="heritage-leaderboard-points">{{ number_format($contributor->points) }} points</div>
                            </div>
                        </div>
                        @endforeach
                    @else
                        <p style="opacity: 0.8; font-size: 0.875rem;">Be the first to contribute!</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- ================================================================
         Section 8: Footer
         ================================================================ -->
    <footer class="heritage-footer">
        <div class="heritage-footer-inner">
            <div class="heritage-footer-links">
                <a href="{{ url('/about') }}">About</a>
                <a href="{{ url('/contact') }}">Contact</a>
                <a href="{{ url('/privacy') }}">Privacy</a>
                <a href="{{ url('/terms') }}">Terms</a>
            </div>
            <div class="heritage-footer-copyright">
                &copy; {{ date('Y') }} {{ $themeData['siteTitle'] ?? config('app.name', 'Heratio') }}. All rights reserved.
            </div>
        </div>
    </footer>

</div>
@endsection

@section('after-content')
<script>
// Add body class for CSS targeting
document.body.classList.add('heritage-landing-page');

// Carousel scroll function
function scrollCarousel(trackId, amount) {
    const track = document.getElementById(trackId);
    if (track) {
        track.scrollBy({ left: amount, behavior: 'smooth' });
    }
}

// Attach carousel button event listeners
document.querySelectorAll('.heritage-carousel-arrow').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var trackId = this.getAttribute('data-carousel');
        var amount = parseInt(this.getAttribute('data-scroll'), 10);
        scrollCarousel(trackId, amount);
    });
});

// Hero image rotation
(function() {
    const backgrounds = document.querySelectorAll('.heritage-hero-bg');
    if (backgrounds.length <= 1) return;

    let currentIndex = 0;
    const defaultDuration = 8000;

    function rotateHero() {
        const current = backgrounds[currentIndex];
        const duration = (parseInt(current.dataset.duration) || 8) * 1000;

        setTimeout(function() {
            current.classList.remove('active');
            currentIndex = (currentIndex + 1) % backgrounds.length;
            backgrounds[currentIndex].classList.add('active');
            rotateHero();
        }, duration);
    }

    rotateHero();
})();

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// Auto-rotating carousel for collections
(function() {
    const carousels = document.querySelectorAll('.heritage-carousel-autorotate');

    carousels.forEach(function(carousel) {
        const track = carousel.querySelector('.heritage-carousel-track');
        const indicatorsContainer = carousel.querySelector('.heritage-carousel-indicators');
        if (!track) return;

        const cards = track.querySelectorAll('.heritage-collection-card');
        if (cards.length === 0) return;

        const autorotateDelay = parseInt(carousel.dataset.autorotate) || 5000;
        const cardWidth = 320;
        let currentPosition = 0;
        let autorotateInterval = null;
        let isPaused = false;

        const trackWidth = track.offsetWidth;
        const visibleCards = Math.floor(trackWidth / cardWidth);
        const totalPositions = Math.max(1, cards.length - visibleCards + 1);

        if (indicatorsContainer && totalPositions > 1) {
            for (let i = 0; i < totalPositions; i++) {
                const dot = document.createElement('button');
                dot.className = 'heritage-carousel-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
                dot.addEventListener('click', function() {
                    goToPosition(i);
                    resetAutorotate();
                });
                indicatorsContainer.appendChild(dot);
            }
        }

        function updateIndicators() {
            if (!indicatorsContainer) return;
            const dots = indicatorsContainer.querySelectorAll('.heritage-carousel-dot');
            dots.forEach(function(dot, index) {
                dot.classList.toggle('active', index === currentPosition);
            });
        }

        function goToPosition(position) {
            currentPosition = position;
            const scrollAmount = position * cardWidth;
            track.scrollTo({ left: scrollAmount, behavior: 'smooth' });
            updateIndicators();
        }

        function nextPosition() {
            if (isPaused) return;
            currentPosition = (currentPosition + 1) % totalPositions;
            goToPosition(currentPosition);
        }

        function startAutorotate() {
            if (totalPositions <= 1) return;
            autorotateInterval = setInterval(nextPosition, autorotateDelay);
        }

        function resetAutorotate() {
            clearInterval(autorotateInterval);
            startAutorotate();
        }

        carousel.addEventListener('mouseenter', function() {
            isPaused = true;
        });

        carousel.addEventListener('mouseleave', function() {
            isPaused = false;
        });

        carousel.addEventListener('focusin', function() {
            isPaused = true;
        });

        carousel.addEventListener('focusout', function() {
            isPaused = false;
        });

        track.addEventListener('scroll', function() {
            const newPosition = Math.round(track.scrollLeft / cardWidth);
            if (newPosition !== currentPosition && newPosition >= 0 && newPosition < totalPositions) {
                currentPosition = newPosition;
                updateIndicators();
            }
        });

        startAutorotate();
    });
})();
</script>
@endsection
