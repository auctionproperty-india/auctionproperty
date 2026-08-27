<?php
// ============================================================
// spin_widget.php – 8‑Segment Wheel (Final)
// ============================================================

if (!isset($pdo) || !isset($user_id)) {
    return;
}

// 🎨 ये वही colors हैं जो आपने सही कहे थे (पिछली बार)
$segmentColors = [
    '#FFD700', // 0 – Gold (GOLD)
    '#FF6B6B', // 1 – Coral
    '#4ECDC4', // 2 – Turquoise
    '#45B7D1', // 3 – Sky Blue
    '#9B59B6', // 4 – Purple (DIAMOND)
    '#FF9F43', // 5 – Orange
    '#F368E0', // 6 – Magenta
    '#00D2D3'  // 7 – Cyan
];
$segmentLabels = ['GOLD', '', '', '', 'DIAMOND', '', '', ''];
$numSegments = 8;
$angle = 360 / $numSegments; // 45°
$rotationOffset = 80; // 80° घुमाव
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
                <!-- 🎡 WHEEL (200px) with correct colors and clean labels -->
                <div style="position:relative; display:inline-block; width:200px; height:200px;">
                    <div id="spinWheel" class="spin-wheel" style="width:200px; height:200px; border-radius:50%; background: conic-gradient(
                        <?php 
                        $gradientParts = [];
                        for ($i = 0; $i < $numSegments; $i++) {
                            $start = $i * $angle + $rotationOffset;
                            $end = ($i + 1) * $angle + $rotationOffset;
                            $color = $segmentColors[$i];
                            $gradientParts[] = "$color $start" . "deg $end" . "deg";
                        }
                        echo implode(', ', $gradientParts);
                        ?>
                    ); border:5px solid #fff; box-shadow:0 0 40px rgba(251,191,36,0.4); margin:0 auto; position:relative;">
                        <!-- Labels – बिना background, black text, centred, upright -->
                        <?php for ($i = 0; $i < $numSegments; $i++): 
                            $label = $segmentLabels[$i] ?? '';
                            if (empty($label)) continue;
                            $centerAngle = $i * $angle + $angle/2 + $rotationOffset;
                            $rad = deg2rad($centerAngle - 90);
                            $distance = 65;
                            $left = 100 + $distance * cos($rad);
                            $top = 100 + $distance * sin($rad);
                        ?>
                            <span style="position:absolute; left:<?= $left ?>px; top:<?= $top ?>px; transform:translate(-50%, -50%); color:#000; font-weight:bold; font-size:14px; text-shadow:0 0 6px rgba(255,255,255,0.7); pointer-events:none; z-index:5; white-space:nowrap;">
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

    const segmentLabels = <?= json_encode($segmentLabels) ?>;
    const numSegments = 8;
    const segmentAngle = 360 / numSegments;
    const specialIndices = [0, 4]; // GOLD and DIAMOND

    spinBtn.addEventListener('click', function() {
        if (isSpinning) return;
        isSpinning = true;
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';

        // 60% chance to land on GOLD or DIAMOND
        let targetSegment;
        if (Math.random() < 0.6) {
            targetSegment = specialIndices[Math.floor(Math.random() * specialIndices.length)];
        } else {
            targetSegment = Math.floor(Math.random() * numSegments);
        }

        const targetAngle = targetSegment * segmentAngle + segmentAngle/2;
        const extraSpins = 5 + Math.floor(Math.random() * 5);
        const newRotation = extraSpins * 360 + (360 - targetAngle);
        const totalRotation = newRotation + 360 - (currentRotation % 360);
        currentRotation += totalRotation;

        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');

        fetch('spin_ajax.php')
            .then(response => response.json())
            .then(data => {
                wheel.classList.remove('pulse');
                isSpinning = false;

                const label = segmentLabels[targetSegment] || '';

                if (label === 'GOLD' || label === 'DIAMOND') {
                    const modalBody = document.getElementById('propertyModalContent');
                    if (modalBody) {
                        let icon = 'fa-gem';
                        let color = '#fbbf24';
                        if (label === 'DIAMOND') { icon = 'fa-crown'; color = '#9B59B6'; }
                        modalBody.innerHTML = `
                            <div style="text-align:center; padding:20px;">
                                <i class="fas ${icon}" style="font-size:4rem; color:${color};"></i>
                                <h4 class="mt-3">🎉 You landed on <strong>${label}</strong>!</h4>
                                <p class="mt-3" style="font-size:1.2rem;">First activate your package to get benefits!</p>
                                <a href="user_packages.php" class="btn btn-primary mt-3">View Packages</a>
                            </div>
                        `;
                        const modal = new bootstrap.Modal(document.getElementById('propertyModal'));
                        modal.show();
                    }
                }

                // Process backend data (coins, property)
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
                        if (!specialIndices.includes(targetSegment)) {
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
                                if (viewLink) viewLink.href = `property_detail.php?id=${p.id}&source=auction`;
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
            if (container.parentNode) container.parentNode.removeChild(container);
        }, maxDuration * 1000 + 500);
    }
});
</script>
