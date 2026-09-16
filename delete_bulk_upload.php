<?php
// ============================================================
// 🗑️ Delete Bulk Uploaded Properties – Batch-wise
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

$action = $_GET['action'] ?? 'list';
$deletedCount = 0;

// ============================================================
// Handle DELETE (by exact timestamp range of a batch)
// ============================================================
if ($action === 'delete' && isset($_POST['batch_start']) && isset($_POST['batch_end']) && isset($_POST['confirm'])) {
    $batchStart = $_POST['batch_start'];
    $batchEnd   = $_POST['batch_end'];

    $stmt = $pdo->prepare("DELETE FROM properties WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$batchStart, $batchEnd]);
    $deletedCount = $stmt->rowCount();
    $action = 'deleted';
}

// ============================================================
// Get Properties Grouped by Time Batches (5-minute window)
// ============================================================
$stmt = $pdo->query("
    SELECT 
        MIN(created_at) as batch_start,
        MAX(created_at) as batch_end,
        COUNT(*) as total,
        MIN(id) as min_id,
        MAX(id) as max_id
    FROM (
        SELECT id, created_at,
            (EXTRACT(EPOCH FROM created_at)::bigint / 300) AS batch_group
        FROM properties
    ) sub
    GROUP BY batch_group
    ORDER BY batch_start DESC
");
$batches = $stmt->fetchAll();

// ---- Get sample titles for each batch ----
$batchDetails = [];
foreach ($batches as $b) {
    $stmt = $pdo->prepare("
        SELECT id, title, city, created_at 
        FROM properties 
        WHERE created_at >= ? AND created_at <= ? 
        ORDER BY id ASC 
        LIMIT 5
    ");
    $stmt->execute([$b['batch_start'], $b['batch_end']]);
    $samples = $stmt->fetchAll();

    $batchDetails[] = [
        'batch_start' => $b['batch_start'],
        'batch_end' => $b['batch_end'],
        'total' => $b['total'],
        'min_id' => $b['min_id'],
        'max_id' => $b['max_id'],
        'samples' => $samples,
    ];
}

$totalProps = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();

include 'header.php';
?>

<style>
    .del-container { max-width: 1100px; margin: 30px auto; padding: 30px; background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .del-container h2 { color: #dc2626; font-weight: 800; margin-bottom: 20px; }
    .info-box { background: #eff6ff; padding: 18px 22px; border-radius: 12px; border-left: 5px solid #2563eb; margin-bottom: 20px; }
    .batch-card { background: #f8faff; border: 2px solid #d1d9e6; border-radius: 16px; padding: 20px; margin-bottom: 18px; transition: all 0.2s; }
    .batch-card:hover { border-color: #2563eb; box-shadow: 0 6px 20px rgba(37,99,235,0.1); }
    .batch-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 14px; }
    .batch-title { font-weight: 800; color: #1e3a8a; font-size: 1.1rem; }
    .batch-time { font-size: 0.85rem; color: #64748b; margin-top: 4px; }
    .batch-count { background: #dc2626; color: #fff; padding: 6px 18px; border-radius: 50px; font-weight: 800; font-size: 1.1rem; }
    .btn-danger-big { background: linear-gradient(135deg, #dc2626, #b91c1c); color: #fff; padding: 12px 30px; border: none; border-radius: 50px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s; }
    .btn-danger-big:hover { background: linear-gradient(135deg, #b91c1c, #991b1b); color: #fff; transform: translateY(-2px); }
    .btn-backup { background: linear-gradient(135deg, #10b981, #059669); color: #fff; padding: 14px 35px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; margin-bottom: 20px; }
    .btn-backup:hover { color: #fff; transform: translateY(-2px); }
    .btn-secondary-big { background: #e2e8f0; color: #1e293b; padding: 12px 30px; border: none; border-radius: 50px; font-weight: 700; text-decoration: none; display: inline-block; }
    .success-box { background: #ecfdf5; border-left: 5px solid #10b981; padding: 20px; border-radius: 12px; margin-bottom: 20px; font-weight: 700; color: #065f46; }
    .sample-list { background: #fff; border-radius: 10px; padding: 12px 16px; border: 1px solid #e8edf4; margin-bottom: 14px; }
    .sample-list li { font-size: 0.85rem; padding: 3px 0; color: #334155; }
    .big-count { font-size: 2.5rem; font-weight: 900; color: #dc2626; text-align: center; }
</style>

<div class="container">
    <div class="del-container">

        <?php if ($action === 'deleted'): ?>
            <div class="success-box">
                ✅ <strong><?= $deletedCount ?></strong> Properties successfully delete हो गईं!
            </div>
            <div class="text-center mt-3">
                <a href="delete_bulk_upload.php" class="btn-secondary-big">फिर से देखें</a>
                <a href="bulk_upload_properties.php" class="btn-danger-big ms-2" style="background: linear-gradient(135deg, #1e40af, #2563eb);">
                    <i class="fas fa-upload me-2"></i> नई Properties Upload करें
                </a>
            </div>

        <?php else: ?>

            <h2><i class="fas fa-layer-group me-2"></i> Bulk Uploaded Properties – Batch-wise Delete</h2>

            <!-- Backup सुझाव -->
            <div class="info-box">
                <h5 class="fw-bold mb-2" style="color: #1e40af;">
                    <i class="fas fa-step-forward me-2"></i> Step 1: पहले Backup लें (ज़रूरी!)
                </h5>
                <p class="mb-2">Delete से पहले सारी Properties का Backup लें।</p>
                <a href="backup_properties.php" class="btn-backup">
                    <i class="fas fa-download me-2"></i> Download Full Backup CSV
                </a>
            </div>

            <div class="big-count"><?= number_format($totalProps) ?></div>
            <p class="text-center text-muted mb-4">Total Properties in Database</p>

            <hr>

            <h5 class="fw-bold mb-3" style="color: #1e3a8a;">
                <i class="fas fa-list me-2"></i> Step 2: कौन-सा Batch Delete करना है, चुनें
            </h5>

            <?php if (count($batchDetails) > 0): ?>
                <?php foreach ($batchDetails as $batch): ?>
                    <div class="batch-card">
                        <div class="batch-header">
                            <div>
                                <div class="batch-title">
                                    <i class="fas fa-layer-group me-2"></i> Upload Batch
                                </div>
                                <div class="batch-time">
                                    <i class="far fa-clock me-1"></i>
                                    <?= date('d M Y, h:i A', strtotime($batch['batch_start'])) ?>
                                    &nbsp;→&nbsp;
                                    <?= date('d M Y, h:i A', strtotime($batch['batch_end'])) ?>
                                    <span class="ms-2 text-muted">
                                        (IDs: <?= $batch['min_id'] ?> – <?= $batch['max_id'] ?>)
                                    </span>
                                </div>
                            </div>
                            <div class="batch-count"><?= number_format($batch['total']) ?> Properties</div>
                        </div>

                        <?php if (count($batch['samples']) > 0): ?>
                            <div class="sample-list">
                                <strong class="small text-muted">Sample Titles (पहली <?= count($batch['samples']) ?>):</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($batch['samples'] as $s): ?>
                                        <li>#<?= $s['id'] ?> – <?= htmlspecialchars(mb_substr($s['title'] ?? '', 0, 70)) ?> <em>(<?= htmlspecialchars($s['city']) ?>)</em></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="?action=delete" style="display:inline;"
                              onsubmit="return confirm('⚠️ चेतावनी!\n\nक्या आप वाकई इस Batch की <?= $batch['total'] ?> Properties DELETE करना चाहते हैं?\n\nसमय: <?= date('d M Y, h:i A', strtotime($batch['batch_start'])) ?>\n\nक्या आपने Backup ले लिया है?\n\nयह Action वापस नहीं हो सकता!');">
                            <input type="hidden" name="batch_start" value="<?= htmlspecialchars($batch['batch_start']) ?>">
                            <input type="hidden" name="batch_end" value="<?= htmlspecialchars($batch['batch_end']) ?>">
                            <input type="hidden" name="confirm" value="yes">
                            <button type="submit" class="btn-danger-big">
                                <i class="fas fa-trash-alt me-2"></i> इस Batch को Delete करें (<?= $batch['total'] ?>)
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="alert alert-info">Database में कोई Properties नहीं मिलीं।</div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>
