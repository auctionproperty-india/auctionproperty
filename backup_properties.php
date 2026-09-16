<?php
// ============================================================
// 🗑️ Delete ALL Properties (Bulk Uploaded + Manual)
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$is_admin = ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'sub_admin');
if (!$is_admin) {
    die("Only Admin can access this page.");
}

$action = $_GET['action'] ?? 'preview';
$deleted = 0;

// ---- Get Total Count ----
$totalCount = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

// ---- Handle DELETE ----
if ($action === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Delete all
    $pdo->exec("DELETE FROM properties");
    $deleted = $totalCount;
    $action = 'deleted';
}

// ---- Get Samples for Preview (Last 20) ----
$stmt = $pdo->query("SELECT id, title, city, price, created_at FROM properties ORDER BY id DESC LIMIT 20");
$previewProps = $stmt->fetchAll();

include 'header.php';
?>

<style>
    .del-container { max-width: 950px; margin: 30px auto; padding: 30px; background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .del-container h2 { color: #dc2626; font-weight: 800; margin-bottom: 20px; }
    .warning-box { background: #fef2f2; border: 2px solid #dc2626; padding: 24px; border-radius: 16px; margin-bottom: 24px; }
    .warning-box h4 { color: #b91c1c; font-weight: 800; margin-bottom: 12px; }
    .info-box { background: #eff6ff; padding: 18px 22px; border-radius: 12px; border-left: 5px solid #2563eb; margin-bottom: 20px; }
    .preview-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .preview-table th { background: #1e3a8a; color: #fff; padding: 10px; text-align: left; font-size: 0.85rem; }
    .preview-table td { padding: 10px; border-bottom: 1px solid #e8edf4; font-size: 14px; }
    .preview-table tr:hover { background: #f8faff; }
    .btn-danger-big { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; padding: 16px 45px; border: none; border-radius: 50px; font-weight: 800; font-size: 1.1rem; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: 0 6px 20px rgba(220,38,38,0.3); transition: all 0.3s; }
    .btn-danger-big:hover { background: linear-gradient(135deg, #b91c1c, #991b1b); color: #fff; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(220,38,38,0.5); }
    .btn-backup { background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 14px 35px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; margin-bottom: 20px; }
    .btn-backup:hover { color: #fff; transform: translateY(-2px); }
    .btn-secondary-big { background: #e2e8f0; color: #1e293b; padding: 14px 40px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; }
    .success-box { background: #ecfdf5; border-left: 5px solid #10b981; padding: 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; color: #065f46; }
    .big-count { font-size: 3rem; font-weight: 900; color: #dc2626; text-align: center; margin: 20px 0; }
</style>

<div class="container">
    <div class="del-container">
        <?php if ($action === 'deleted'): ?>
            <div class="success-box">
                ✅ <strong><?= $deleted ?></strong> Properties successfully delete हो गईं!
            </div>
            <div class="text-center">
                <a href="properties.php" class="btn-secondary-big">View Properties Page</a>
                <a href="bulk_upload_properties.php" class="btn-danger-big ms-2" style="background: linear-gradient(135deg, #1e40af, #2563eb);">
                    <i class="fas fa-upload me-2"></i> नई Properties Upload करें
                </a>
            </div>

        <?php else: ?>
            <h2><i class="fas fa-exclamation-triangle me-2"></i> Delete ALL Properties</h2>

            <!-- 🔥 STEP 1: Backup -->
            <div class="info-box">
                <h5 style="color: #1e40af; font-weight: 700;">
                    <i class="fas fa-step-forward me-2"></i> Step 1: पहले Backup लें
                </h5>
                <p class="mb-2">Delete करने से पहले सारी Properties का Backup ज़रूर लें। Backup से आप कभी भी Restore कर सकते हैं।</p>
                <a href="backup_properties.php" class="btn-backup">
                    <i class="fas fa-download me-2"></i> Download Backup CSV
                </a>
            </div>

            <!-- 🔥 STEP 2: Warning -->
            <div class="warning-box">
                <h4><i class="fas fa-radiation me-2"></i> Step 2: चेतावनी – यह Action वापस नहीं होगा!</h4>
                <p class="mb-0">
                    नीचे दिए गए बटन पर Click करने से <strong>DATABASE की सारी Properties</strong> permanently delete हो जाएँगी। 
                    यह Action <strong>UNDO नहीं किया जा सकता</strong>। इसलिए पहले Backup लेना ज़रूरी है।
                </p>
            </div>

            <!-- Total Count -->
            <div class="big-count">
                <?= number_format($totalCount) ?>
                <div style="font-size: 1rem; color: #64748b; font-weight: 600;">Total Properties in Database</div>
            </div>

            <!-- Preview -->
            <?php if (count($previewProps) > 0): ?>
                <p class="mt-4"><strong>🔍 पिछली 20 Properties का Preview:</strong></p>
                <div style="max-height: 400px; overflow-y: auto; border: 1px solid #e8edf4; border-radius: 12px;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>City</th>
                                <th>Price</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($previewProps as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['id']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($p['title'] ?? '', 0, 60)) ?></td>
                                    <td><?= htmlspecialchars($p['city'] ?? '') ?></td>
                                    <td>₹ <?= number_format($p['price'] ?? 0) ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($p['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- 🔥 STEP 3: Delete Button -->
            <div class="text-center mt-4">
                <?php if ($totalCount > 0): ?>
                    <a href="?action=delete&confirm=yes"
                       class="btn-danger-big"
                       onclick="return confirm('⚠️ आखिरी चेतावनी!\n\nक्या आप वाकई सभी <?= $totalCount ?> Properties को PERMANENTLY DELETE करना चाहते हैं?\n\nक्या आपने Backup ले लिया है?\n\nयह Action वापस नहीं हो सकता!');">
                        <i class="fas fa-trash-alt me-2"></i> Delete All <?= number_format($totalCount) ?> Properties
                    </a>
                <?php else: ?>
                    <div class="alert alert-info">
                        ℹ️ Database में कोई Properties नहीं हैं।
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
