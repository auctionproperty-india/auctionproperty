<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php';

// Fetch user's properties
$stmt = $pdo->prepare("SELECT * FROM user_properties WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$props = $stmt->fetchAll();

// ✅ Unlimited properties – no limit check
$total_props = count($props);
// Always allow adding more properties
$can_add = true; // Always true
?>
<style>
    .property-card {
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        transition: 0.3s;
        background: white;
    }
    .property-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .property-card img { height: 180px; width: 100%; object-fit: cover; }
    .badge-pending { background: #f59e0b; }
    .badge-approved { background: #10b981; }
    .badge-rejected { background: #ef4444; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4><i class="fas fa-building me-2"></i>My Properties</h4>
        <div>
            <!-- ✅ Show total count only, no limit -->
            <span class="badge bg-secondary me-2">Total: <?= $total_props ?></span>
            <!-- ✅ Always show Add Property button -->
            <a href="add_user_property.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Property</a>
        </div>
    </div>

    <?php if(count($props) > 0): ?>
        <div class="row">
            <?php foreach($props as $p): 
                $status_class = $p['status'] == 'approved' ? 'approved' : ($p['status'] == 'pending' ? 'pending' : 'rejected');
            ?>
                <div class="col-md-4 mb-4">
                    <div class="property-card">
                        <?php if($p['image_url']): ?>
                            <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display:none; height:180px; background:#f1f5f9; align-items:center; justify-content:center; color:#94a3b8;">
                                <i class="fas fa-image fa-3x"></i>
                            </div>
                        <?php else: ?>
                            <div style="height:180px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">
                                <i class="fas fa-image fa-3x"></i>
                            </div>
                        <?php endif; ?>
                        <div class="p-3">
                            <h5><?= htmlspecialchars($p['title']) ?></h5>
                            <p class="text-muted small">
                                <?= htmlspecialchars($p['city']) ?>, <?= htmlspecialchars($p['state']) ?>
                                <?php if(!empty($p['sqft'])): ?> | Area: <?= $p['sqft'] ?> Sq Ft <?php endif; ?>
                                <?php if(!empty($p['construction_sqft'])): ?> | Built-up: <?= $p['construction_sqft'] ?> Sq Ft <?php endif; ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">₹ <?= indianCurrencyFormat($p['price']) ?></span>
                                <span class="badge badge-<?= $status_class ?>"><?= ucfirst($p['status']) ?></span>
                            </div>
                            <?php if($p['status'] == 'rejected' && $p['admin_remarks']): ?>
                                <div class="text-danger small mt-1">Reason: <?= htmlspecialchars($p['admin_remarks']) ?></div>
                            <?php endif; ?>
                            <div class="mt-2">
                                <a href="edit_user_property.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                <a href="delete_user_property.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete?')">Delete</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-4">
            <i class="fas fa-home fa-2x"></i>
            <p class="mt-2">You haven't added any properties yet.</p>
            <!-- ✅ Always show Add button in empty state -->
            <a href="add_user_property.php" class="btn btn-primary">Add Your First Property</a>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
