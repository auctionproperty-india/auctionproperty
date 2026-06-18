<?php
require_once 'db.php';
require_once 'functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$property_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
$stmt->execute([$property_id]);
$prop = $stmt->fetch();
if(!$prop) { die("Property not found!"); }

// Check if user has ANY active subscription (for all properties) - CURRENT_DATE
$active_sub = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
$active_sub->execute([$user_id]);
$has_access = $active_sub->rowCount() > 0;

if(!$has_access) {
    header("Location: dashboard.php?msg=subscribe_first");
    exit;
}

include 'header.php'; 
?>
<div class="container mt-4">
    <div class="card p-4 shadow">
        <h2><?= htmlspecialchars($prop['title']) ?></h2>
        <hr>
        <div class="row">
            <div class="col-md-8">
                <p><strong>Bank:</strong> <?= htmlspecialchars($prop['bank_name'] ?? 'N/A') ?></p>
                <p><strong>Borrower:</strong> <?= htmlspecialchars($prop['borrower_name'] ?? 'N/A') ?></p>
                <p><strong>Location:</strong> <?= htmlspecialchars($prop['location']) ?>, <?= htmlspecialchars($prop['city']) ?></p>
                <p><strong>Area:</strong> <?= $prop['sqft'] ?? 0 ?> Sq Ft</p>
                <p><strong>Possession:</strong> <?= htmlspecialchars($prop['possession_type'] ?? 'Physical') ?></p>
                <p><strong>Reserve Price:</strong> ₹ <?= indianCurrencyFormat($prop['price']) ?></p>
                <p><strong>EMD:</strong> ₹ <?= indianCurrencyFormat($prop['emd_amount'] ?? 0) ?></p>
                <p><strong>Bid Increment:</strong> ₹ <?= indianCurrencyFormat($prop['bid_increment'] ?? 0) ?></p>
                <p><strong>Auction:</strong> <?= htmlspecialchars($prop['auction_start_time'] ?? '') ?> to <?= htmlspecialchars($prop['auction_end_time'] ?? '') ?></p>
                <p><strong>Contact:</strong> <?= htmlspecialchars($prop['contact_number'] ?? '') ?></p>
                <?php if(!empty($prop['google_location'])): ?>
                    <p><a href="<?= $prop['google_location'] ?>" target="_blank">📍 View on Maps</a></p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <img src="<?= htmlspecialchars($prop['image_url'] ?: 'https://via.placeholder.com/400x300') ?>" class="img-fluid rounded">
            </div>
        </div>
        <a href="dashboard.php" class="btn btn-secondary mt-3">⬅ Back</a>
    </div>
</div>
<?php include 'footer.php'; ?>
