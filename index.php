<?php
// सत्र (session) शुरू करें
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// डेटाबेस और फंक्शन शामिल करें
require_once 'db.php';
require_once 'functions.php';

// क्या यूजर लॉगिन है?
$isLoggedIn = isset($_SESSION['user_id']);
$userName = $isLoggedIn ? ($_SESSION['username'] ?? 'User') : '';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>आज की ऑक्शन – होम</title>
    <!-- आपका header.php में शैलियाँ (CSS) होंगी, उसे शामिल करें -->
    <?php include 'header.php'; ?>
    <!-- अगर header.php में <head> बंद नहीं होता, तो आपको यहाँ अतिरिक्त CSS/JS डालने की ज़रूरत नहीं -->
</head>
<body>
    <!-- नेविगेशन / हेडर – header.php पहले से शामिल -->
    <?php include 'header.php'; ?>

    <main>
        <div class="container mt-4">
            <!-- ✅ डैशबोर्ड / रजिस्टर / लॉगिन लिंक (ऊपर) -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <h2>🏠 आज की ऑक्शन प्रॉपर्टी</h2>
                    <?php if ($isLoggedIn): ?>
                        <p>
                            नमस्ते, <strong><?php echo htmlspecialchars($userName); ?></strong>!
                            <a href="dashboard.php" class="btn btn-primary btn-sm">📊 डैशबोर्ड</a>
                            <a href="logout.php" class="btn btn-danger btn-sm">लॉगआउट</a>
                        </p>
                    <?php else: ?>
                        <p>
                            <a href="login.php" class="btn btn-success btn-sm">🔑 लॉगिन करें</a>
                            <a href="register.php" class="btn btn-warning btn-sm">📝 रजिस्टर करें</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================================================== -->
            <!-- ✅ कैरोसेल (Carousel) – यहाँ नीचे चलेगा               -->
            <!-- ================================================== -->
            <div class="carousel-wrapper">
                <button class="arrow arrow-left" id="prevBtn">‹</button>
                <div class="carousel-track-container">
                    <div class="carousel-track" id="carouselTrack"></div>
                </div>
                <button class="arrow arrow-right" id="nextBtn">›</button>
                <div class="dots-container" id="dotsContainer"></div>
            </div>
            <!-- ================================================== -->
        </div>
    </main>

    <!-- फूटर शामिल करें -->
    <?php include 'footer.php'; ?>

    <!-- ✅ कैरोसेल की CSS (यदि header.php में नहीं है तो) -->
    <style>
        /* ---- Carousel Styles (पूरी तरह से) ---- */
        .carousel-wrapper {
            width: 100%;
            max-width: 1000px;
            margin: 30px auto;
            overflow: hidden;
            border-radius: 32px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.7), 0 0 0 1px rgba(255,255,255,0.06) inset;
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(4px);
            padding: 16px 0;
            position: relative;
        }
        .carousel-track-container {
            overflow: hidden;
            border-radius: 24px;
            margin: 0 12px;
        }
        .carousel-track {
            display: flex;
            transition: transform 0.65s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            will-change: transform;
        }
        .slide {
            flex: 0 0 100%;
            min-height: 380px;
            padding: 40px 44px 30px 44px;
            border-radius: 24px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            padding-top: 52px;
            box-sizing: border-box;
        }
        .slide::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.04);
            pointer-events: none;
            border-radius: 24px;
        }
        .slide::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.10) 0%, transparent 70%);
            pointer-events: none;
            z-index: 1;
        }
        .slide-content {
            position: relative;
            z-index: 2;
            color: #fff;
            text-shadow: 0 2px 12px rgba(0,0,0,0.25);
        }
        .slide-icon { font-size: 2.8rem; margin-bottom: 14px; display: block; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3)); }
        .slide-title { font-size: 2rem; font-weight: 700; line-height: 1.2; letter-spacing: -0.02em; margin-bottom: 8px; }
        .slide-sub { font-size: 1.3rem; font-weight: 500; opacity: 0.92; margin-bottom: 4px; }
        .slide-bank { font-size: 1.1rem; font-weight: 400; opacity: 0.8; margin-top: 2px; letter-spacing: 0.3px; }
        .slide-date {
            margin-top: 18px;
            font-size: 1rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(0,0,0,0.25);
            padding: 8px 22px 8px 18px;
            border-radius: 60px;
            backdrop-filter: blur(6px);
            border: 1px solid rgba(255,255,255,0.12);
            width: fit-content;
        }
        .slide-date span { opacity: 0.7; font-weight: 300; }
        .slide-badge {
            position: absolute;
            top: 20px;
            right: 24px;
            z-index: 3;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(8px);
            padding: 6px 18px;
            border-radius: 60px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            color: #fff;
            border: 1px solid rgba(255,255,255,0.1);
            text-transform: uppercase;
        }
        .auction-tag {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 5;
            text-align: center;
            padding: 6px 0;
            background: rgba(0,0,0,0.25);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            border-radius: 24px 24px 0 0;
        }

        /* Slide colors – अलग-अलग ग्रेडिएंट */
        .slide-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .slide-2 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .slide-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .slide-4 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: #0a1a1a; }
        .slide-4 .slide-date { background: rgba(0,0,0,0.15); color: #0a1a1a; border-color: rgba(0,0,0,0.08); }
        .slide-4 .slide-date span { opacity: 0.6; }
        .slide-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #1a0e0e; }
        .slide-5 .slide-date { background: rgba(0,0,0,0.15); color: #1a0e0e; border-color: rgba(0,0,0,0.08); }
        .slide-6 { background: linear-gradient(135deg, #fddb92 0%, #d1fdff 100%); color: #1a1a1a; }
        .slide-6 .slide-date { background: rgba(0,0,0,0.1); color: #1a1a1a; }

        .no-auction {
            background: linear-gradient(135deg, #2b2d42 0%, #1a1a2e 100%);
            color: #fff;
            text-align: center;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 600;
            padding: 40px;
        }
        .no-auction .slide-content { color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); }

        /* डॉट्स और एरो */
        .dots-container {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
            padding: 0 12px;
        }
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 60px;
            background: rgba(255,255,255,0.2);
            border: none;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            padding: 0;
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.05);
        }
        .dot.active { background: #fff; width: 36px; box-shadow: 0 0 24px rgba(255,255,255,0.3); }
        .dot:hover { transform: scale(1.2); background: rgba(255,255,255,0.5); }

        .arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 44px;
            height: 44px;
            border-radius: 60px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.10);
            color: #fff;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            user-select: none;
        }
        .arrow:hover { background: rgba(255,255,255,0.18); transform: translateY(-50%) scale(1.05); }
        .arrow-left { left: 20px; }
        .arrow-right { right: 20px; }

        /* मोबाइल के लिए रिस्पॉन्सिव */
        @media (max-width: 640px) {
            .carousel-wrapper { border-radius: 20px; padding: 10px 0; }
            .carousel-track-container { margin: 0 6px; }
            .slide { min-height: 300px; padding: 28px 24px 20px 24px; padding-top: 44px; }
            .slide-title { font-size: 1.4rem; }
            .slide-sub { font-size: 1rem; }
            .slide-bank { font-size: 0.95rem; }
            .slide-icon { font-size: 2.2rem; }
            .slide-date { font-size: 0.85rem; padding: 6px 16px; }
            .slide-badge { font-size: 0.6rem; padding: 4px 12px; top: 14px; right: 16px; }
            .arrow { width: 34px; height: 34px; font-size: 1rem; }
            .arrow-left { left: 8px; }
            .arrow-right { right: 8px; }
            .dots-container { gap: 8px; margin-top: 16px; }
            .dot { width: 10px; height: 10px; }
            .dot.active { width: 28px; }
            .no-auction { font-size: 1.4rem; min-height: 220px; }
        }
    </style>

    <!-- ✅ कैरोसेल की JavaScript -->
    <script>
        (function() {
            // API एंडपॉइंट – जो आपने बनाया है
            const API_URL = 'api_todays_auctions.php';

            const track = document.getElementById('carouselTrack');
            const dotsContainer = document.getElementById('dotsContainer');
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');

            let currentIndex = 0;
            let autoPlayInterval = null;
            const AUTO_DELAY = 3200; // 3.2 सेकंड

            // तारीख को फॉर्मेट करें
            function formatDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr + 'T00:00:00');
                return d.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            }

            // स्लाइड बनाएँ
            function buildSlides(properties) {
                track.innerHTML = '';
                dotsContainer.innerHTML = '';

                if (!properties || properties.length === 0) {
                    const slide = document.createElement('div');
                    slide.className = 'slide no-auction';
                    slide.innerHTML = `
                        <div class="slide-content">
                            <span style="font-size:3rem;">📭</span>
                            <div style="margin-top:15px;">आज के लिए कोई Auction नहीं है</div>
                            <div style="font-size:1rem; opacity:0.7; margin-top:10px; font-weight:400;">कृपया कल फिर देखें</div>
                        </div>
                    `;
                    track.appendChild(slide);
                    return;
                }

                properties.forEach((prop, index) => {
                    const slide = document.createElement('div');
                    const colorIndex = (index % 6) + 1; // 1-6 तक रंग
                    slide.className = `slide slide-${colorIndex}`;

                    const formattedDate = formatDate(prop.auction_date) || 'तारीख निर्धारित नहीं';
                    const icon = prop.icon || '🏷️';
                    const bank = prop.bank_name || '🏦 बैंक';
                    const location = prop.location || '';

                    slide.innerHTML = `
                        <div class="auction-tag">🔴 Live Auction</div>
                        <div class="slide-badge">#${index + 1}</div>
                        <div class="slide-content">
                            <span class="slide-icon">${icon}</span>
                            <div class="slide-title">${prop.title || 'प्रॉपर्टी'}</div>
                            <div class="slide-sub">${location}</div>
                            <div class="slide-bank">${bank}</div>
                            <div class="slide-date">
                                📅 ${formattedDate} &nbsp;<span>•</span>&nbsp; ⏰ 11:00 AM
                            </div>
                        </div>
                    `;
                    track.appendChild(slide);

                    // डॉट बनाएँ
                    const dot = document.createElement('button');
                    dot.classList.add('dot');
                    if (index === 0) dot.classList.add('active');
                    dot.setAttribute('data-index', index);
                    dotsContainer.appendChild(dot);
                });
            }

            // कैरोसेल को इनिशियलाइज़ करें
            function initCarousel() {
                const slides = track.querySelectorAll('.slide');
                const dots = dotsContainer.querySelectorAll('.dot');
                const total = slides.length;

                if (total === 0) return;

                function goToSlide(index) {
                    if (index < 0) index = total - 1;
                    if (index >= total) index = 0;
                    currentIndex = index;
                    track.style.transform = `translateX(-${currentIndex * 100}%)`;
                    dots.forEach((dot, i) => {
                        dot.classList.toggle('active', i === currentIndex);
                    });
                }

                function nextSlide() { goToSlide(currentIndex + 1); }
                function prevSlide() { goToSlide(currentIndex - 1); }

                function startAutoPlay() {
                    if (autoPlayInterval) clearInterval(autoPlayInterval);
                    if (total > 1) {
                        autoPlayInterval = setInterval(nextSlide, AUTO_DELAY);
                    }
                }
                function stopAutoPlay() {
                    if (autoPlayInterval) {
                        clearInterval(autoPlayInterval);
                        autoPlayInterval = null;
                    }
                }

                // बटन इवेंट
                prevBtn.onclick = function(e) {
                    e.stopPropagation();
                    stopAutoPlay();
                    prevSlide();
                    startAutoPlay();
                };
                nextBtn.onclick = function(e) {
                    e.stopPropagation();
                    stopAutoPlay();
                    nextSlide();
                    startAutoPlay();
                };

                // डॉट क्लिक
                dots.forEach((dot) => {
                    dot.onclick = function() {
                        const index = parseInt(this.getAttribute('data-index'), 10);
                        if (index === currentIndex) return;
                        stopAutoPlay();
                        goToSlide(index);
                        startAutoPlay();
                    };
                });

                // होवर पर रुके
                const wrapper = document.querySelector('.carousel-wrapper');
                wrapper.onmouseenter = stopAutoPlay;
                wrapper.onmouseleave = startAutoPlay;

                // टच/स्वाइप
                let touchStartX = 0;
                wrapper.ontouchstart = function(e) { touchStartX = e.changedTouches[0].screenX; };
                wrapper.ontouchend = function(e) {
                    const diff = touchStartX - e.changedTouches[0].screenX;
                    if (Math.abs(diff) > 40) {
                        stopAutoPlay();
                        if (diff > 0) nextSlide(); else prevSlide();
                        startAutoPlay();
                    }
                };

                // कीबोर्ड (←, →)
                document.onkeydown = function(e) {
                    if (e.key === 'ArrowRight') { e.preventDefault(); stopAutoPlay(); nextSlide(); startAutoPlay(); }
                    else if (e.key === 'ArrowLeft') { e.preventDefault(); stopAutoPlay(); prevSlide(); startAutoPlay(); }
                };

                goToSlide(0);
                startAutoPlay();
            }

            // API से डेटा लाएँ और कैरोसेल बनाएँ
            fetch(API_URL)
                .then(response => response.json())
                .then(data => {
                    buildSlides(data);
                    initCarousel();
                })
                .catch(err => {
                    console.error('Error fetching auctions:', err);
                    track.innerHTML = `
                        <div class="slide no-auction">
                            <div class="slide-content">⚠️ डेटा लोड नहीं हो पाया, कृपया बाद में प्रयास करें</div>
                        </div>
                    `;
                });
        })();
    </script>

</body>
</html>
