<!-- ============================================================
     🎡 SPIN WIDGET – Slot Info + Clean Wheel + Property Modal
     ============================================================ -->

<?php
// This file is included in user_dashboard.php
// It expects $user_id, $pdo, and $current_slot_data, $current_slot, $slot_statuses if defined
// If not defined, we will fetch data ourselves.

// If the variables are not set, we can fetch them directly.
if (!isset($user_id)) {
    // Assuming session started and user logged in
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) return;
}

// ---- Get current slot and spin data ----
$today = date('Y-m-d');
$hour = (int)date('H');
if ($hour < 8) $slot = 1;
elseif ($hour < 14) $slot = 2;
else $slot = 3;

$slot_names = [1 => '12 AM – 8 AM', 2 => '8 AM – 2 PM', 3 => '2 PM – 12 AM'];
$current_slot = $slot_names[$slot];

$stmt = $pdo->prepare("SELECT spins_used, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
$stmt->execute([$user_id, $today, $slot]);
$data = $stmt->fetch();
if (!$data) {
    $spins_used = 0;
    $coins_earned = 0;
} else {
    $spins_used = (int)$data['spins_used'];
    $coins_earned = (int)$data['coins_earned'];
}
$current_slot_data = ['spins_used' => $spins_used, 'coins_earned' => $coins_earned];
?>

<style>
    .spin-container {
        background: linear-gradient(145deg, #1e293b, #0f172a);
        border-radius: 24px;
        padding: 25px 20px;
        margin-bottom: 30px;
        color: #fff;
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    }
    .spin-container h4 {
        color: #fbbf24;
        font-weight: 700;
    }
    .spin-wheel-wrapper {
        position: relative;
        display: inline-block;
    }
    .spin-wheel {
        width: 280px;
        height: 280px;
        border-radius: 50%;
        background: conic-gradient(
            #f94144 0deg 45deg,
            #f8961e 45deg 90deg,
            #f9c74f 90deg 135deg,
            #90be6d 135deg 180deg,
            #43aa8b 180deg 225deg,
            #577590 225deg 270deg,
            #7209b7 270deg 315deg,
            #f72585 315deg 360deg
        );
        border: 6px solid #fff;
        box-shadow: 0 0 30px rgba(0,0,0,0.5);
        transition: transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99);
        margin: 0 auto;
    }
    .spin-pointer {
        position: absolute;
        top: -12px;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 0;
        border-left: 18px solid transparent;
        border-right: 18px solid transparent;
        border-top: 28px solid #fbbf24;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.5));
        z-index: 10;
    }
    .spin-btn {
        background: linear-gradient(135deg, #fbbf24, #f59e0b);
        border: none;
        padding: 12px 40px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.2rem;
        color: #0f172a;
        box-shadow: 0 6px 20px rgba(251,191,36,0.3);
        transition: all 0.3s;
    }
    .spin-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(251,191,36,0.5);
    }
    .spin-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    .spin-stats {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
        font-size: 0.9rem;
        background: rgba(255,255,255,0.05);
        border-radius: 16px;
        padding: 12px;
    }
    .spin-stats span {
        font-weight: 600;
    }
    .spin-stats .badge {
        font-size: 0.8rem;
        padding: 4px 12px;
    }
    .spin-message {
        min-height: 28px;
        font-weight: 600;
        color: #fbbf24;
        margin-top: 6px;
    }
    @keyframes spinPulse {
        0% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
        50% { box-shadow: 0 0 60px rgba(251,191,36,0.6); }
        100% { box-shadow: 0 0 30px rgba(251,191,36,0.3); }
    }
    .spin-wheel.pulse {
        animation: spinPulse 1s infinite;
    }
    .property-modal-content {
        background: #fff;
        border-radius: 20px;
        padding: 20px;
        color: #0f172a;
    }
    .property-modal-content .btn-primary {
        background: #2563eb;
        border: none;
    }
</style>

<div class="spin-container text-center">
    <h4><i class="fas fa-sync-alt me-2"></i> Daily Spin</h4>
    <p class="text-muted small">Spin the wheel to win coins or a property!</p>

    <div class="spin-wheel-wrapper">
        <div class="spin-pointer"></div>
        <div class="spin-wheel" id="spinWheel"></div>
    </div>

    <button class="spin-btn mt-3" id="spinBtn" <?= ($spins_used >= 5) ? 'disabled' : '' ?>>
        <i class="fas fa-sync-alt me-2"></i> SPIN!
    </button>

    <div id="spinMessage" class="spin-message"></div>

    <div class="spin-stats">
        <div>🎰 Slot: <strong><?= $current_slot ?></strong></div>
        <div>🔄 Spins Used: <strong><span id="spinCount"><?= $spins_used ?></span>/5</strong></div>
        <div>🪙 Coins Earned: <strong><span id="slotCoins"><?= $coins_earned ?></span>/22</strong></div>
    </div>
</div>

<!-- ====== PROPERTY MODAL (included here) ====== -->
<div class="modal fade" id="propertyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content property-modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title"><i class="fas fa-gift text-warning"></i> 🎉 Congratulations!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="propertyModalContent">
                <!-- Dynamic content will be injected here -->
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="viewPropertyLink" class="btn btn-primary">View Details</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const spinBtn = document.getElementById('spinBtn');
    const wheel = document.getElementById('spinWheel');
    const spinCount = document.getElementById('spinCount');
    const slotCoins = document.getElementById('slotCoins');
    const spinMessage = document.getElementById('spinMessage');

    let isSpinning = false;
    let currentRotation = 0;

    spinBtn.addEventListener('click', function() {
        if (isSpinning) return;
        isSpinning = true;
        this.disabled = true;
        spinMessage.innerHTML = '🔄 Spinning...';

        // ---- ONE ROTATION ----
        const extra = Math.floor(Math.random() * 360);
        const total = 360 * 5 + extra;
        currentRotation += total;

        wheel.style.transition = 'transform 4s cubic-bezier(0.17, 0.67, 0.12, 0.99)';
        wheel.style.transform = `rotate(${currentRotation}deg)`;
        wheel.classList.add('pulse');

        fetch('spin_ajax.php')
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                wheel.classList.remove('pulse');
                isSpinning = false;

                if (data.success) {
                    spinCount.textContent = data.spins_used || 0;
                    slotCoins.textContent = data.total_coins_earned || 0;

                    if (data.show_property && data.property) {
                        const p = data.property;
                        const modalContent = document.getElementById('propertyModalContent');
                        if (modalContent) {
                            const imageHtml = p.image_url ? 
                                `<img src="${p.image_url}" style="width:100%; max-height:200px; object-fit:cover; border-radius:12px; margin-bottom:12px;" alt="${p.title}">` :
                                `<div style="height:150px; background:#f1f5f9; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fas fa-image fa-2x"></i></div>`;
                            modalContent.innerHTML = `
                                ${imageHtml}
                                <h5 class="fw-bold">🏠 ${p.title}</h5>
                                <p class="text-muted">🏦 ${p.bank_name || 'Bank'}</p>
                                <p class="text-warning fw-bold">₹ ${parseInt(p.price).toLocaleString('en-IN')}</p>
                                <p><i class="fas fa-map-pin"></i> ${p.city || 'N/A'}</p>
                                <p><small>Type: ${p.type || 'N/A'}</small></p>
                                <div class="mt-2 p-2 bg-success bg-opacity-25 rounded-3">
                                    <i class="fas fa-calendar-check me-1"></i> Auction Date: ${p.auction_date ? new Date(p.auction_date).toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'}) : 'N/A'}
                                </div>
                            `;
                            const viewLink = document.getElementById('viewPropertyLink');
                            if (viewLink) viewLink.href = `property_detail.php?id=${p.id}&source=auction`;
                            const propertyModal = new bootstrap.Modal(document.getElementById('propertyModal'));
                            propertyModal.show();
                            spinMessage.innerHTML = '🏠 Check out this property!';
                            propertyModal._element.addEventListener('hidden.bs.modal', function() {
                                spinBtn.disabled = (data.spins_used >= 5);
                            });
                        } else {
                            spinMessage.innerHTML = '🏠 Property found!';
                            spinBtn.disabled = (data.spins_used >= 5);
                        }
                    } else if (data.coins > 0) {
                        spinMessage.innerHTML = `🎉 +${data.coins} coins!`;
                        const coinStat = document.querySelector('.stat-card .stat-number');
                        if (coinStat) {
                            let cur = parseInt(coinStat.textContent.replace(/,/g, ''));
                            if (!isNaN(cur)) coinStat.textContent = (cur + data.coins).toLocaleString();
                        }
                        spinBtn.disabled = (data.spins_used >= 5);
                    } else {
                        spinMessage.innerHTML = data.message || 'Spin done!';
                        spinBtn.disabled = (data.spins_used >= 5);
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
});
</script>
