<?php 
require_once 'db.php'; 

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Admin Redirect actions...
if($role == 'admin') { /* ... same as before ... */ }

include 'header.php'; 
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

if($role == 'admin'): 
    // --- Admin View (Same Stats) --- 
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_sold = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'sold'")->fetchColumn();
?>
    <div class="row g-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-users"></i></div><div><h5><?= $total_users ?></h5><small>Total Users</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-check-circle"></i></div><div><h5><?= $total_sold ?></h5><small>Sold</small></div></div></div>
    </div>
    <div id="users-section" class="mt-4"><div class="card-premium"><h4>👥 Manage Users</h4> ... (same table) ... </div></div>
<?php else: 
    // =============================================
    // ========= USER DASHBOARD WITH AUCTION CARDS =========
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?")->execute([$user_id]);
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $user = $user->fetch();
    
    try {
        $purchases = $pdo->prepare("SELECT p.*, pr.title FROM purchases p JOIN properties pr ON p.property_id = pr.id WHERE p.user_id = ?");
        $purchases->execute([$user_id]);
        $purchases = $purchases->fetchAll();
        $purchase_count = count($purchases);
    } catch(Exception $e) { $purchases = []; $purchase_count = 0; }
?>
    <!-- User Welcome Banner -->
    <div class="user-welcome-banner">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div><h2>🏡 Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h2><p>Find your dream property today.</p></div>
            <div><a href="index.php" class="btn btn-light text-success fw-bold">Explore More →</a></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-primary me-3"><i class="fas fa-shopping-bag"></i></div><div><h5><?= $purchase_count ?></h5><small>Purchases</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center" style="border-left:4px solid #10b981;"><div class="stat-icon bg-soft-success me-3"><i class="fas fa-gift"></i></div><div><h5><span class="badge bg-success"><?= $user['referral_code'] ?></span></h5><small>Referral Code</small></div></div></div>
        <div class="col-md-4"><div class="card-premium d-flex align-items-center"><div class="stat-icon bg-soft-warning me-3"><i class="fas fa-building"></i></div><div><h5><?= $total_props ?></h5><small>Total Properties</small></div></div></div>
    </div>

    <!-- Referral Link -->
    <div class="card-premium mt-3" style="border:1px solid #10b981; background:#f0fdf4;">
        <h5 class="text-success"><i class="fas fa-link me-2"></i>Share & Earn</h5>
        <div class="input-group"><input type="text" class="form-control border-success" id="refLink" value="https://<?= $_SERVER['HTTP_HOST'] ?>/register.php?ref=<?= $user['referral_code'] ?>" readonly><button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button></div>
    </div>

    <!-- ===== LATEST AUCTION PROPERTIES (Cards inside User Dashboard) ===== -->
    <div class="card-premium mt-3">
        <div class="d-flex justify-content-between"><h5><i class="fas fa-fire me-2" style="color:#f97316;"></i>Live Auctions for You</h5><a href="index.php" class="btn btn-sm btn-outline-primary">View All</a></div>
        <div class="row mt-3">
            <?php
            $stmt = $pdo->query("SELECT * FROM properties WHERE status = 'available' ORDER BY id DESC LIMIT 6");
            $props = $stmt->fetchAll();
            if(count($props) > 0) {
                foreach($props as $p) { ?>
                    <div class="col-md-4 col-sm-6 mb-3">
                        <div class="property-card" style="background: white; border-radius: 16px; overflow: hidden; border: 1px solid #e9edf4; height: 100%;">
                            <img src="<?= htmlspecialchars($p['image_url'] ?: 'https://via.placeholder.com/300x200?text=Property') ?>" style="height: 150px; width:100%; object-fit: cover;">
                            <div class="p-3">
                                <span class="bank-badge" style="font-size:10px; background:#e0e7ff; color:#1e3a8a; padding:2px 10px; border-radius:30px;">🏦 <?= htmlspecialchars($p['bank_name'] ?? 'Bank') ?></span>
                                <h6 class="fw-bold mt-1"><?= htmlspecialchars($p['title']) ?></h6>
                                <p class="text-muted small"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($p['city']) ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-success">₹ <?= number_format($p['price'], 2) ?></span>
                                    <span class="badge bg-secondary"><?= $p['sqft'] ?? 0 ?> Sq Ft</span>
                                </div>
                                <a href="#" class="btn btn-primary btn-sm w-100 mt-2">View Auction</a>
                            </div>
                        </div>
                    </div>
                <?php }
            } else { echo "<p class='text-muted'>No live auctions right now.</p>"; }
            ?>
        </div>
    </div>

    <script>
        function copyRef() {
            let inp = document.getElementById('refLink');
            inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Copied!')).catch(() => document.execCommand('copy'));
        }
    </script>
<?php endif; ?>
<?php include 'footer.php'; ?>
