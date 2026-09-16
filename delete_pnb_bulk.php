<?php
// ============================================================
// 🗑️ Delete ONLY PNB Bulk Uploaded Properties
// Filters: bank_name = 'PNB Housing' AND created_at >= '2026-09-10'
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

// ============================================================
// ⚙️ CONFIGURATION – यहाँ बदलाव करें
// ============================================================
$BANK_FILTER   = 'PNB Housing';      // Bulk Upload की Bank Name
$FROM_DATE     = '2026-09-10 00:00:00'; // इस Date के बाद की Properties
// ============================================================

$action = $_GET['action'] ?? 'preview';
$deletedCount = 0;
$errorMsg = '';

// ---- Helper: Safe quote ----
function sqlQuote($pdo, $value) {
    return $pdo->quote($value);
}

// ============================================================
// Handle DELETE
// ============================================================
if ($action === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    try {
        $sql = "DELETE FROM properties 
                WHERE bank_name = " . sqlQuote($pdo, $BANK_FILTER) . "
                  AND created_at >= " . sqlQuote($pdo, $FROM_DATE);
        $deletedCount = $pdo->exec($sql);
        $action = 'deleted';
    } catch (PDOException $e) {
        $errorMsg = "Delete failed: " . $e->getMessage();
    }
}

// ============================================================
// Preview – Count & Sample List
// ============================================================
try {
    // Count Matching Properties
    $countSql = "SELECT COUNT(*) FROM properties 
                 WHERE bank_name = " . sqlQuote($pdo, $BANK_FILTER) . "
                   AND created_at >= " . sqlQuote($pdo, $FROM_DATE);
    $matchingCount = $pdo->query($countSql)->fetchColumn();

    // Total Properties in DB
    $totalCount = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

    // Count Non-Matching (Safe Properties)
    $safeCount = $totalCount - $matchingCount;

    // Sample 10 Matching Properties
    $sampleSql = "SELECT id, title, city, bank_name, auction_date, created_at 
                  FROM properties 
                  WHERE bank_name = " . sqlQuote($pdo, $BANK_FILTER) . "
                    AND created_at >= " . sqlQuote($pdo, $FROM_DATE) . "
                  ORDER BY id ASC LIMIT 10";
    $samples = $pdo->query($sampleSql)->fetchAll();

    // Batch Info – Group by minute
    $batchSql = "SELECT 
                    MIN(created_at) as batch_start,
                    MAX(created_at) as batch_end,
                    COUNT(*) as total,
                    MIN(id) as min_id,
                    MAX(id) as max_id
                 FROM (
                    SELECT id, created_at,
                        (EXTRACT(EPOCH FROM created_at)::bigint / 300) AS grp
                    FROM properties
                    WHERE bank_name = " . sqlQuote($pdo, $BANK_FILTER) . "
                      AND created_at >= " . sqlQuote($pdo, $FROM_DATE) . "
                 ) sub
                 GROUP BY grp
                 ORDER BY batch_start ASC";
    $batchInfo = $pdo->query($batchSql)->fetchAll();

} catch (PDOException $e) {
    $errorMsg = "Query failed: " . $e->getMessage();
    $matchingCount = 0; $totalCount = 0; $safeCount = 0; $samples = []; $batchInfo = [];
}

include 'header.php';
?>

<style>
    .del-container { max-width: 1050px; margin: 30px auto; padding: 30px; background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .del-container h2 { color: #dc2626; font-weight: 800; margin-bottom: 20px; }
    .filter-info { background: #eff6ff; padding: 20px; border-radius: 14px; border-left: 5px solid #2563eb; margin-bottom: 24px; }
    .filter-info code { background: #dbeafe; padding: 3px 10px; border-radius: 6px; font-weight: 700; color: #1e40af; }
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 24px; }
    .stat-box { padding: 20px; border-radius: 14px; text-align: center; }
    .stat-box .num { font-size: 2.5rem; font-weight: 900; line-height: 1; }
    .stat-box .lbl { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; margin-top: 6px; }
    .stat-danger { background: #fef2f2; border: 2px solid #dc2626; }
    .stat-danger .num { color: #dc2626; }
    .stat-danger .lbl { color: #991b1b; }
    .stat-safe { background: #ecfdf5; border: 2px solid #10b981; }
    .stat-safe .num { color: #059669; }
    .stat-safe .lbl { color: #065f46; }
    .stat-total { background: #f8fafc; border: 2px solid #64748b; }
    .stat-total .num { color: #334155; }
    .stat-total .lbl { color: #475569; }
    .preview-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .preview-table th { background: #1e3a8a; color: #fff; padding: 10px; text-align: left; font-size: 0.8rem; }
    .preview-table td { padding: 10px; border-bottom: 1px solid #e8edf4; font-size: 13px; }
    .preview-table tr:hover { background: #f8faff; }
    .btn-danger-big { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; padding: 18px 55px; border: none; border-radius: 50px; font-weight: 800; font-size: 1.2rem; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: 0 6px 20px rgba(220,38,38,0.3); transition: all 0.3s; }
    .btn-danger-big:hover { background: linear-gradient(135deg, #b91c1c, #991b1b); color: #fff; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(220,38,38,0.5); }
    .btn-backup { background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 14px 35px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; margin-bottom: 20px; }
    .btn-backup:hover { color: #fff; transform: translateY(-2px); }
    .btn-secondary-big { background: #e2e8f0; color: #1e293b; padding: 12px 30px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; }
    .success-box { background: #ecfdf5; border-left: 5px solid #10b981; padding: 22px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; color: #065f46; font-size: 1.1rem; }
    .error-box { background: #fef2f2; border-left: 5px solid #dc2626; padding: 20px; border-radius: 12px; margin-bottom: 20px; color: #991b1b; }
    .batch-badge { background: #f0f5ff; padding: 8px 14px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 8px; border-left: 3px solid #2563eb; }
</style>

<div class="container">
    <div class="del-container">

        <?php if (!empty($errorMsg)): ?>
            <div class="error-box">❌ <strong>Error:</strong> <?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <?php if ($action === 'deleted'): ?>
            <div class="success-box">
                ✅ <strong><?= number_format($deletedCount) ?></strong> PNB Bulk Uploaded Properties successfully delete हो गईं!
            </div>
            <div class="text-center mt-3">
                <a href="delete_pnb_bulk.php" class="btn-secondary-big">फिर से देखें</a>
                <a href="bulk_upload_properties.php" class="btn-danger-big ms-2" style="background: linear-gradient(135deg, #1e40af, #2563eb);">
                    <i class="fas fa-upload me-2"></i> नई Properties Upload करें
                </a>
            </div>

        <?php else: ?>

            <h2><i class="fas fa-filter me-2"></i> Delete Only PNB Bulk Uploaded Properties</h2>

            <!-- Filter Info -->
            <div class="filter-info">
                <h6 class="fw-bold mb-2" style="color: #1e40af;">
                    <i class="fas fa-info-circle me-2"></i> यह Script सिर्फ इन Properties को Delete करेगी:
                </h6>
                <ul class="mb-2" style="font-size: 0.95rem;">
                    <li><strong>Bank Name:</strong> <code><?= htmlspecialchars($BANK_FILTER) ?></code></li>
                    <li><strong>Created After:</strong> <code><?= date('d M Y', strtotime($FROM_DATE)) ?></code></li>
                </ul>
                <small class="text-muted">
                    ✅ Manual Add Properties और दूसरी Banks की Properties <strong>सुरक्षित रहेंगी</strong>
                </small>
            </div>

            <!-- Backup सुझाव -->
            <div class="text-center mb-4">
                <a href="backup_properties.php" class="btn-backup">
                    <i class="fas fa-download me-2"></i> पहले Full Backup लें (ज़रूरी!)
                </a>
            </div>

            <!-- Stats -->
            <div class="stat-grid">
                <div class="stat-box stat-total">
                    <div class="num"><?= number_format($totalCount) ?></div>
                    <div class="lbl">Total Properties</div>
                </div>
                <div class="stat-box stat-danger">
                    <div class="num"><?= number_format($matchingCount) ?></div>
                    <div class="lbl">Delete होंगी (PNB Bulk)</div>
                </div>
                <div class="stat-box stat-safe">
                    <div class="num"><?= number_format($safeCount) ?></div>
                    <div class="lbl">Safe रहेंगी</div>
                </div>
            </div>

            <!-- Batch Info -->
            <?php if (count($batchInfo) > 0): ?>
                <h6 class="fw-bold mt-4 mb-2" style="color: #1e3a8a;">
                    <i class="fas fa-layer-group me-2"></i> Delete होने वाले Batches (<?= count($batchInfo) ?>):
                </h6>
                <?php foreach ($batchInfo as $i => $b): ?>
                    <div class="batch-badge">
                        <strong>Batch #<?= $i + 1 ?>:</strong>
                        <?= date('d M Y, h:i A', strtotime($b['batch_start'])) ?>
                        → <?= date('d M Y, h:i A', strtotime($b['batch_end'])) ?>
                        &nbsp;|&nbsp; <strong><?= number_format($b['total']) ?></strong> Properties
                        &nbsp;|&nbsp; IDs: <?= $b['min_id'] ?>–<?= $b['max_id'] ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Sample Preview -->
            <?php if (count($samples) > 0): ?>
                <h6 class="fw-bold mt-4 mb-2" style="color: #1e3a8a;">
                    <i class="fas fa-eye me-2"></i> Sample Properties (पहली 10 दिखाई जा रही हैं):
                </h6>
                <div style="max-height: 350px; overflow-y: auto; border: 1px solid #e8edf4; border-radius: 12px;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>City</th>
                                <th>Bank</th>
                                <th>Auction Date</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($samples as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['id']) ?></td>
                                    <td><?= htmlspecialchars(mb_substr($s['title'] ?? '', 0, 55)) ?></td>
                                    <td><?= htmlspecialchars($s['city'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($s['bank_name'] ?? '') ?></td>
                                    <td><?= !empty($s['auction_date']) ? date('d M Y', strtotime($s['auction_date'])) : 'N/A' ?></td>
                                    <td><?= date('d M Y, h:i A', strtotime($s['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Delete Button -->
            <?php if ($matchingCount > 0): ?>
                <div class="text-center mt-5">
                    <a href="?action=delete&confirm=yes"
                       class="btn-danger-big"
                       onclick="return confirm('⚠️ चेतावनी!\n\nक्या आप वाकई <?= number_format($matchingCount) ?> PNB Bulk Uploaded Properties को PERMANENTLY DELETE करना चाहते हैं?\n\nक्या आपने Backup लिया है?\n\nSafe रहेंगी: <?= number_format($safeCount) ?> Properties\n\nयह Action वापस नहीं हो सकता!');">
                        <i class="fas fa-trash-alt me-2"></i> Delete <?= number_format($matchingCount) ?> PNB Bulk Properties
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center mt-4">
                    ℹ️ कोई PNB Bulk Uploaded Properties नहीं मिलीं। शायद सब पहले ही Delete हो चुकी हैं।
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
