<?php
// ============================================================
// spin_widget.php – 8‑Segment Wheel with GOLD & DIAMOND
// ============================================================

if (!isset($pdo) || !isset($user_id)) {
    return;
}

// Helper to generate segment colors (you can change these)
$segmentColors = [
    '#fbbf24', // 0 – Yellow
    '#ef4444', // 1 – Red
    '#10b981', // 2 – Green
    '#3b82f6', // 3 – Blue
    '#8b5cf6', // 4 – Purple
    '#f59e0b', // 5 – Amber
    '#ec4899', // 6 – Pink
    '#14b8a6'  // 7 – Teal
];
// Labels for each segment (index 0=GOLD, index 4=DIAMOND)
$segmentLabels = ['GOLD', '', '', '', 'DIAMOND', '', '', ''];
?>
<div class="spin-card">
    <h4><i class="fas fa-gift me-2" style="color: #fbbf24;"></i>Daily Spin</h4>
    <div class="row g-3 mb-4">
        <?php foreach ($slot_statuses as $slot => $status): 
            $card_class = '';
            $badge_color = '';
            if ($status['is_past']) {
                if ($status['spins_used'] > 0) {
                    $card_class = 'claimed';
                    $badge_color = 'bg-success';
                } else {
                    $card_class = 'missed';
                    $badge_color = 'bg-danger';
                }
            } elseif ($status['is_current']) {
                $card_class = 'current';
                $badge_color = 'bg-primary';
            } else {
                $card_class = 'upcoming';
                $badge_color = 'bg-secondary';
            }
        ?>
        <div class="col-md-4">
            <div class="slot-card <?= $card_class ?>">
                <div class="slot-time"><?= $status['time_range'] ?></div>
                <div class="slot-status">
                    <span class="badge <?= $badge_color ?>"><?= $status['label'] ?></span>
                    <span class="ms-2"><?= $status['message'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($current_slot_data['can_spin']): ?>
    <div class="row align-items-center mt-3">
        <div class="col-md-6">
            <p class="mb-1">Current Slot: <strong><?= getSlotTimeRange($current_slot) ?></strong></p>
            <p class="mb-1">Spins Used: <span id="spinCount"><?= $current_slot_data['spins_used'] ?></span>/5</p>
            <p class="mb-1">Coins Earned this slot: <span id="slotCoins"><?= $current_slot_data['coins_earned'] ?></span>/22</p>
            <div id="spinMessage" class="mt-2 small"></div>
        </div>
        <div class="col-md-6 text-center">
            <div class="spinner-wrapper" style="position:relative; display:inline-block;">
                <!-- 🎡 8‑SEGMENT WHEEL (200px) -->
                <div style="position:relative; display:inline-block; width:200px; height:200px;">
                    <div id="spinWheel" class="spin-wheel" style="width:200px; height:200px; border-radius:50%; background: conic-gradient(
                        <?php 
                        $numSegments = 8;
                        $angle = 360 / $numSegments;
                        $gradientParts = [];
                        for ($i = 0; $i < $numSegments; $i++) {
                            $start = $i * $angle;
                            $end = ($i + 1) * $angle;
                            $color = $segmentColors[$i % count($segmentColors)];
                            $gradientParts[] = "$color $start" . "deg $end" . "deg";
                        }
                        echo implode(', ', $gradientParts);
                        ?>
                    ); border:5px solid #fff; box-shadow:0 0 40px rgba(251,191,36,0.4); margin:0 auto;">
                        <!-- Segment Labels (positioned at center of each wedge) -->
                        <?php for ($i = 0; $i < $numSegments; $i++): 
                            $label = $segmentLabels[$i] ?? '';
                            if (empty($label)) continue;
                            // Calculate position: center of wedge at radius ~65% of 100px
                            $angleRad = deg2rad($i * $angle + $angle/2 - 90); // -90 to start from top
                            $distance = 65; // pixels from center
                            $left = 100 + $distance * cos($angleRad);
                            $top = 100 + $distance * sin($angleRad);
                            // Rotate to align with wedge (45°)
                            $rotation = $i * $angle + $angle/2;
                        ?>
                            <span style="position:absolute; left:<?= $left ?>px; top:<?= $top ?>px; transform:translate(-50%, -50%) rotate(<?= $rotation ?>deg); color:#fff; font-weight:bold; font-size:16px; text-shadow:0 0 12px rgba(0,0,0,0.9); pointer-events:none; z-index:5; white-space:nowrap; background:rgba(0,0,0,0.4); padding:2px 10px; border-radius:12px;">
                                <?= htmlspecialchars($label) ?>
                            </span>
                        <?php endfor; ?>
                    </div>
                    <!-- Center dot -->
                    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:white; width:36px; height:36px; border-radius:50%; border:5px solid #fbbf24; z-index:2;"></div>
                    <!-- Pointer -->
                    <div style="position:absolute; top:-6px; left:50%; transform:translateX(-50%); width:0; height:0; border-left:16px solid transparent; border-right:16px solid transparent; border-top:26px solid #fbbf24; filter:drop-shadow(0 0 16px rgba(251,191,36,0.7)); z-index:2;"></div>
                </div>
                <button id="spinBtn" class="btn btn-warning mt-3 px-4 fw-bold" <?= ($current_slot_data['can_spin']) ? '' : 'disabled' ?>>
                    <i class="fas fa-sync-alt"></i> Spin!
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-secondary text-center mt-3">
        <?php if ($current_slot_data['spins_used'] >= 5): ?>
            You have completed this slot! Total coins earned: <?= $current_slot_data['coins_earned'] ?>/22
        <?php else: ?>
            No spins available for this slot.
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ====== SPIN JAVASCRIPT (updated with segment detection) ====== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const spinBtn = document.getElementById('spinBtn');
    const wheel = document.getElementById('spinWheel');
    const spinCount = document.getElementById('spinCount');
    const spinMessage = document.getElementById('spinMessage');
    const slotCoins = document.getElementById('slotCoins');

    if (!spinBtn) return;

    let currentRotation = 0;
    let isSpinning = false;

    // Segment mapping (index -> label)
    const segmentLabels = <?= json_encode($segmentLabels) ?>;
    const numSegments = 8;
    const segmentAngle = 360 / numSegments;

    spinBtn.addEventListener('click', function() {
        if (isSpinning) return;
        isSpinning = true;
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';

        // Random: decide which segment to land on (0-7)
        const targetSegment = Math.floor(Math.random() * numSegments);
        // Calculate rotation to land on that segment (pointer at top)
        // We want the segment's center to be at top (0°)
        // Segment center angle = targetSegment * segmentAngle + segmentAngle/2
        const targetAngle = targetSegment * segmentAngle + segmentAngle/2;
        // We need to rotate so that this angle aligns with the top (0°)
        // Current rotation is from previous spins.
        // We'll add extra spins for visual effect.
        const extraSpins = 5 + Math.floor(Math.random() * 5);
        const newRotation = extraSpins * 360 + (360 - targetAngle);
        // Ensure total rotation is more than current
        const totalRotation = newRotation + 360 - (currentRotation % 360);
        currentRotation += totalRotation;

        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');

        // Simulate spin result from backend (we keep the existing AJAX for coins)
        fetch('spin_ajax.php')
            .then(response => response.json())
            .then(data => {
                wheel.classList.remove('pulse');
                isSpinning = false;

                // After spin, determine which segment is at top
                const finalAngle = currentRotation % 360;
                // Compute which segment index is at the top (pointer at 0°)
                // Segment index = floor((360 - (finalAngle % 360)) / segmentAngle) % numSegments
                let segIndex = Math.floor(((360 - (finalAngle % 360)) % 360) / segmentAngle) % numSegments;
                // Adjust for offset (pointer is at top, segment center is at segmentAngle/2)
                // Actually, since we aligned targetAngle to top, we can use the targetSegment we set.
                // But we want to detect after spin, we can compute from rotation.
                // Using targetSegment from earlier is more reliable.
                const landedSegment = targetSegment; // we set it earlier

                // Show message if landed on GOLD or DIAMOND
                const label = segmentLabels[landedSegment] || '';
                if (label === 'GOLD' || label === 'DIAMOND') {
                    // Show modal with message
                    const modalBody = document.getElementById('propertyModalContent');
                    if (modalBody) {
                        modalBody.innerHTML = `
                            <div style="text-align:center; padding:20px;">
                                <i class="fas fa-gem" style="font-size:4rem; color:#fbbf24;"></i>
                                <h4 class="mt-3">🎉 You landed on <strong>${label}</strong>!</h4>
                                <p class="mt-3" style="font-size:1.2rem;">First activate your package to get benefits!</p>
                                <a href="user_packages.php" class="btn btn-primary mt-3">View Packages</a>
                            </div>
                        `;
                        const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
                        modal.show();
                    }
                }

                // Process the backend data (coins/property) – as before
                if (data.success) {
                    spinCount.textContent = data.spins_used || 0;
                    slotCoins.textContent = data.total_coins_earned || 0;

                    if (data.is_reward) {
                        spinMessage.innerHTML = `🎉 +${data.coins} coins!`;
                        showCoinAnimation(data.coins);
                        launchStarShower();

                        const coinSpan = document.querySelector('.stat-card .stat-number');
                        if (coinSpan) {
                            let current = parseInt(coinSpan.textContent.replace(/,/g, ''));
                            if (!isNaN(current)) {
                                coinSpan.textContent = (current + data.coins).toLocaleString();
                            }
                        }
                        if (data.spins_used >= 5) {
                            spinBtn.disabled = true;
                            spinBtn.innerHTML = '<i class="fas fa-check"></i> Done';
                        } else {
                            spinBtn.disabled = false;
                        }
                    } else if (data.show_property && data.property) {
                        const p = data.property;
                        const isCar = (p.type && (p.type.toLowerCase().includes('car') || p.type.toLowerCase().includes('vehicle')));
                        const icon = isCar ? '🚗' : '🏠';
                        const imageHtml = p.image_url ? `<img src="${p.image_url}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px; margin-bottom:12px;" alt="${p.title}">` : `<div style="height:150px; background:#1e293b; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fas fa-image fa-2x"></i></div>`;
                        // Only show property if we didn't show the GOLD/DIAMOND modal
                        if (label !== 'GOLD' && label !== 'DIAMOND') {
                            const modalContent = document.getElementById('propertyModalContent');
                            if (modalContent) {
                                modalContent.innerHTML = `
                                    ${imageHtml}
                                    <h5 class="fw-bold">${icon} ${p.title}</h5>
                                    <p class="text-muted">🏦 ${p.bank_name || 'Bank'}</p>
                                    <p class="text-warning fw-bold">₹ ${parseInt(p.price).toLocaleString('en-IN')}</p>
                                    <p><i class="fas fa-map-pin"></i> ${p.city || 'N/A'}</p>
                                    <p><small class="text-muted">Type: ${p.type || 'N/A'}</small></p>
                                    <div class="mt-2 p-2 bg-success bg-opacity-25 rounded-3">
                                        <i class="fas fa-coins text-warning"></i> You earned <strong>${data.coins}</strong> coins!
                                    </div>
                                `;
                                const viewLink = document.getElementById('viewPropertyLink');
                                if (viewLink) {
                                    viewLink.href = `property_detail.php?id=${p.id}&source=auction`;
                                }
                                const propertyModal = new bootstrap.Modal(document.getElementById('propertyModal'));
                                propertyModal.show();
                                spinMessage.innerHTML = data.message || '🏠 Check out this property!';
                                propertyModal._element.addEventListener('hidden.bs.modal', function () {
                                    spinBtn.disabled = false;
                                });
                            }
                        } else {
                            spinBtn.disabled = false;
                        }
                    } else {
                        spinMessage.innerHTML = data.message || 'Spin done!';
                        spinBtn.disabled = false;
                    }
                } else {
                    spinMessage.innerHTML = `❌ ${data.message || 'Something went wrong'}`;
                    spinBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Spin error:', error);
                wheel.classList.remove('pulse');
                spinMessage.innerHTML = '❌ Error spinning. Please try again.';
                spinBtn.disabled = false;
                isSpinning = false;
            });
    });

    function showCoinAnimation(coins) {
        const toast = document.createElement('div');
        toast.style.cssText = 'position:fixed; bottom:20px; right:20px; background:#10b981; color:white; padding:16px 24px; border-radius:12px; font-weight:bold; box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:9999; animation: slideIn 0.5s ease;';
        toast.innerHTML = `🪙 +${coins} coins!`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s';
            setTimeout(() => toast.remove(), 500);
        }, 3000);
    }

    function launchStarShower() {
        const container = document.createElement('div');
        container.className = 'star-shower-container';
        document.body.appendChild(container);

        const count = 130;
        const colors = ['#fbbf24', '#f59e0b', '#fcd34d', '#fde68a', '#fef3c7', '#ffffff', '#ffd700', '#ffb700', '#ffaa00', '#ffcc66'];

        for (let i = 0; i < count; i++) {
            const star = document.createElement('div');
            star.className = 'star-shower';
            const size = 8 + Math.random() * 22;
            const left = Math.random() * 100;
            const duration = 1.5 + Math.random() * 2.8;
            const delay = Math.random() * 1.6;
            const rotation = Math.random() * 360;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const starChar = Math.random() > 0.4 ? '★' : '✦';
            
            star.style.cssText = `
                --duration: ${duration}s;
                --delay: ${delay}s;
                --color: ${color};
                font-size: ${size}px;
                color: ${color};
                left: ${left}%;
                transform: rotate(${rotation}deg);
            `;
            star.textContent = starChar;
            container.appendChild(star);
        }

        const maxDuration = 3.0 + 1.6;
        setTimeout(() => {
            if (container.parentNode) {
                container.parentNode.removeChild(container);
            }
        }, maxDuration * 1000 + 500);
    }
});
</script>
