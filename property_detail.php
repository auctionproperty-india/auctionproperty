<?php
// ============================================================
// 🏠 Property Detail Page – With Similar Properties Fix
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user_id = $_SESSION['user_id'] ?? null;
$show_images = userHasActiveSubscription($pdo, $user_id); // ✅ Define early

$property_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = isset($_GET['source']) ? $_GET['source'] : 'auction';

if ($source == 'auction') {
    $stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ? AND status = 'available'");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();
    $similar_sql = "SELECT * FROM properties WHERE status = 'available' AND city = ? AND id != ? ORDER BY id DESC LIMIT 6";
} else {
    $stmt = $pdo->prepare("SELECT * FROM user_properties WHERE id = ? AND status = 'approved'");
    $stmt->execute([$property_id]);
    $property = $stmt->fetch();
    $similar_sql = "SELECT * FROM user_properties WHERE status = 'approved' AND city = ? AND id != ? ORDER BY id DESC LIMIT 6";
}

if (!$property) {
    die("Property not found.");
}

// Fetch similar properties
$similar_stmt = $pdo->prepare($similar_sql);
$similar_stmt->execute([$property['city'], $property_id]);
$similar_props = $similar_stmt->fetchAll();

// Render function (uses $show_images from outer scope)
function renderPropertyCard($prop, $show_images, $source_type = 'auction') {
    $gradients = [
        ['bg' => 'linear-gradient(135deg, #0f172a 0%, #1e293b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #1e3a5f 0%, #3b82f6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #064e3b 0%, #10b981 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #4c1d95 0%, #8b5cf6 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #b91c1c 0%, #ef4444 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #78350f 0%, #f59e0b 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #172554 0%, #6366f1 100%)', 'text' => 'white'],
        ['bg' => 'linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%)', 'text' => 'dark'],
    ];
    $g = $gradients[array_rand($gradients)];
    $text_color = ($g['text'] == 'white') ? '#ffffff' : '#0f172a';
    $shadow = ($g['text'] == 'white') ? '0 15px 40px -10px rgba(0,0,0,0.3)' : '0 15px 40px -10px rgba(0,0,0,0.1)';
    $border = ($g['text'] == 'white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.05)';
    $image_url = $prop['image_url'] ?? '';
    ?>
    <div class="col-md-4 mb-4">
        <div class="card h-100" style="border-radius:24px; overflow:hidden; border:none; box-shadow:<?= $shadow ?>; transition:all 0.4s; background: <?= $g['bg'] ?>; color:<?= $text_color ?>;">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; padding:4px 14px; border-radius:30px; color:<?= $text_color ?>;">🏦 <?= htmlspecialchars($prop['bank_name'] ?? 'Bank') ?></span>
                    <?php if(!empty($prop['auction_start_time'])): ?>
                        <span style="font-size:0.75rem; opacity:0.8; color:<?= $text_color ?>;"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($prop['auction_start_time']) ?></span>
                    <?php endif; ?>
                </div>
                <h5 class="fw-bold mt-2" style="color:<?= $text_color ?>;"><?= htmlspecialchars($prop['title']) ?></h5>
                <div style="font-size:1.6rem; font-weight:800; color:<?= $text_color ?>;">₹ <?= indianCurrencyFormat($prop['price']) ?></div>
                <div style="font-size:0.85rem; opacity:0.8; color:<?= $text_color ?>;"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($prop['city'] ?? '') ?></div>
                <a href="property_detail.php?id=<?= $prop['id'] ?>&source=<?= $source_type ?>" style="display:block; margin-top:16px; background:<?= ($g['text']=='white') ? 'rgba(255,255,255,0.15)' : 'rgba(0,0,0,0.08)' ?>; backdrop-filter:blur(4px); border:1px solid <?= $border ?>; color:<?= $text_color ?>; font-weight:700; padding:12px; border-radius:16px; text-align:center; text-decoration:none; transition:all 0.3s;">View Details →</a>
            </div>
            <?php if($show_images && !empty($image_url)): ?>
                <img src="<?= htmlspecialchars($image_url) ?>" style="height:200px; width:100%; object-fit:cover; border-top:3px solid <?= $border ?>;" alt="<?= htmlspecialchars($prop['title']) ?>">
            <?php else: ?>
                <div style="height:150px; background:rgba(255,255,255,0.08); display:flex; flex-direction:column; align-items:center; justify-content:center; backdrop-filter:blur(4px); border-top:3px solid <?= $border ?>; padding:10px;">
                    <i class="fas fa-lock" style="font-size:1.8rem; opacity:0.7; color:<?= $text_color ?>;"></i>
                    <span style="font-size:0.8rem; font-weight:600; margin-top:4px; color:<?= $text_color ?>;">🔒 Subscribe to unlock</span>
                    <a href="user_packages.php" class="btn btn-sm btn-primary mt-2" style="border-radius:30px; font-weight:600; color:#ffffff; background:#2563eb; border:none;">Subscribe Now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <!-- Main Property Detail -->
            <div class="card-premium">
                <h2><?= htmlspecialchars($property['title']) ?></h2>
                <p><strong>🏦 <?= htmlspecialchars($property['bank_name'] ?? 'Bank') ?></strong></p>
                <p><strong>Price:</strong> ₹ <?= indianCurrencyFormat($property['price']) ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($property['city'] ?? '') ?>, <?= htmlspecialchars($property['state'] ?? '') ?></p>
                <p><strong>Type:</strong> <?= htmlspecialchars($property['type'] ?? 'N/A') ?></p>
                <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($property['description'] ?? '')) ?></p>
                <?php if ($show_images && !empty($property['image_url'])): ?>
                    <img src="<?= htmlspecialchars($property['image_url']) ?>" style="max-width:100%; border-radius:12px;" alt="<?= htmlspecialchars($property['title']) ?>">
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <!-- Sidebar info -->
            <div class="card-premium">
                <h5>Property Details</h5>
                <ul class="list-unstyled">
                    <li><strong>EMD:</strong> ₹ <?= indianCurrencyFormat($property['emd_amount'] ?? 0) ?></li>
                    <li><strong>Bid Increment:</strong> ₹ <?= indianCurrencyFormat($property['bid_increment'] ?? 0) ?></li>
                    <li><strong>Auction Date:</strong> <?= safeDateFormat($property['auction_date'] ?? null) ?></li>
                    <li><strong>Contact:</strong> <?= htmlspecialchars($property['contact_number'] ?? 'N/A') ?></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ====== SIMILAR PROPERTIES ====== -->
    <?php if (count($similar_props) > 0): ?>
        <div class="row mt-4">
            <div class="col-12">
                <h4 class="section-title"><i class="fas fa-arrow-right me-2"></i>Similar Properties</h4>
            </div>
            <?php 
            // ✅ Ensure $show_images is defined (it is defined at top, but double-check)
            if (!isset($show_images)) $show_images = false;
            foreach ($similar_props as $sim): 
                renderPropertyCard($sim, $show_images, $source);
            endforeach; 
            ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
