<?php
// ============================================================
// ⚙️ System Settings – Permanent Storage (Supabase + UPSERT)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit;
}
if(!hasViewPermission('settings', $pdo)) {
    die("Permission denied.");
}

$message = '';
$settings_keys = [
    'default_contact', 'company_bank_name', 'company_account_number', 
    'company_ifsc', 'company_branch', 'tds_percent', 'admin_charge_percent', 
    'spin_min_coins', 'spin_max_coins', 'company_qr_code'
];

// 🔒 Ensure all keys exist (UPSERT to handle UNIQUE constraint)
foreach ($settings_keys as $key) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, '') 
            ON CONFLICT (setting_key) DO NOTHING
        ");
        $stmt->execute([$key]);
    } catch (Exception $e) {
        // If constraint doesn't exist yet, fallback to old method
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, '')")->execute([$key]);
        }
    }
}

// ---- Handle POST Actions (Section Wise) ----
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if(!hasEditPermission('settings', $pdo)) {
        die("You don't have permission to edit settings.");
    }
    
    $admin_password = $_POST['admin_password'] ?? '';
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch();
    
    if(!$admin || !password_verify($admin_password, $admin['password'])) {
        $message = "<div class='alert alert-danger'>❌ Incorrect admin password! Settings not saved.</div>";
    } else {
        $action = $_POST['action'];
        
        // Helper: Save multiple settings via UPSERT
        $saveSetting = function($key, $value) use ($pdo) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON CONFLICT (setting_key) 
                    DO UPDATE SET setting_value = EXCLUDED.setting_value
                ");
                $stmt->execute([$key, $value]);
            } catch (Exception $e) {
                // Fallback if no UNIQUE constraint
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?")->execute([$value, $key]);
            }
        };
        
        // 1. SAVE BANK & CONTACT DETAILS
        if ($action == 'save_bank') {
            $keys = ['company_bank_name', 'company_account_number', 'company_ifsc', 'company_branch', 'default_contact'];
            foreach($keys as $key) {
                $val = trim($_POST[$key] ?? '');
                $saveSetting($key, $val);
            }
            $message = "<div class='alert alert-success'>✅ Bank & Contact Details updated!</div>";
        }
        
        // 2. SAVE REFERRAL PAYOUT DEDUCTIONS
        elseif ($action == 'save_payout') {
            $keys = ['tds_percent', 'admin_charge_percent'];
            foreach($keys as $key) {
                $val = trim($_POST[$key] ?? '');
                $saveSetting($key, $val);
            }
            $message = "<div class='alert alert-success'>✅ Referral Payout Deductions updated!</div>";
        }
        
        // 3. SAVE DAILY SPIN SETTINGS
        elseif ($action == 'save_spin') {
            $keys = ['spin_min_coins', 'spin_max_coins'];
            foreach($keys as $key) {
                $val = trim($_POST[$key] ?? '');
                $saveSetting($key, $val);
            }
            $message = "<div class='alert alert-success'>✅ Daily Spin Settings updated!</div>";
        }
        
        // 4. SAVE QR CODE (using Supabase Storage)
        elseif ($action == 'save_qr') {
            
            // Get old QR for cleanup
            $old_qr = '';
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_qr_code'");
                $stmt->execute();
                $old_qr = $stmt->fetchColumn() ?: '';
            } catch (Exception $e) {}
            
            // ---- REMOVE QR CODE ----
            if (isset($_POST['remove_qr']) && $_POST['remove_qr'] == '1') {
                // Delete from Supabase if URL
                if (!empty($old_qr) && strpos($old_qr, 'http') === 0) {
                    if (function_exists('deleteFromSupabase')) {
                        @deleteFromSupabase($old_qr);
                    }
                }
                // Delete local file if it exists
                elseif (!empty($old_qr) && file_exists(__DIR__ . '/' . $old_qr)) {
                    @unlink(__DIR__ . '/' . $old_qr);
                }
                
                $saveSetting('company_qr_code', '');
                $message = "<div class='alert alert-success'>✅ QR Code removed successfully!</div>";
            } 
            // ---- UPLOAD NEW QR CODE ----
            elseif(isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
                
                $uploaded_url = null;
                
                // 🔥 Try Supabase Storage first
                if (function_exists('uploadToSupabase')) {
                    $uploaded_url = uploadToSupabase($_FILES['qr_code'], 'qr_codes', 'payment_screenshots');
                }
                
                // If Supabase fails, fallback to local
                if (!$uploaded_url) {
                    $upload_dir = 'uploads/';
                    if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $ext = pathinfo($_FILES['qr_code']['name'], PATHINFO_EXTENSION);
                    $filename = 'qr_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['qr_code']['tmp_name'], __DIR__ . '/' . $upload_dir . $filename)) {
                        $uploaded_url = $upload_dir . $filename;
                    }
                }
                
                if ($uploaded_url) {
                    // Delete old QR from Supabase if URL
                    if (!empty($old_qr) && strpos($old_qr, 'http') === 0) {
                        if (function_exists('deleteFromSupabase')) {
                            @deleteFromSupabase($old_qr);
                        }
                    }
                    // Delete old local file
                    elseif (!empty($old_qr) && file_exists(__DIR__ . '/' . $old_qr)) {
                        @unlink(__DIR__ . '/' . $old_qr);
                    }
                    
                    $saveSetting('company_qr_code', $uploaded_url);
                    $message = "<div class='alert alert-success'>✅ New QR Code uploaded successfully!<br><small>Storage: " . (strpos($uploaded_url, 'http') === 0 ? 'Supabase (permanent)' : 'Local (temporary)') . "</small></div>";
                } else {
                    $message = "<div class='alert alert-danger'>❌ QR Code upload failed. Please check Supabase settings.</div>";
                }
            } else {
                $message = "<div class='alert alert-warning'>⚠️ No file selected.</div>";
            }
        }
    }
}

include 'header.php';

// ---- Fetch current values (UPSERT-safe read) ----
$settings = [];
foreach($settings_keys as $key) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $settings[$key] = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) {
        $settings[$key] = '';
    }
}

$qr = $settings['company_qr_code'] ?? '';
$has_qr = false;
$qr_display_url = '';

if (!empty($qr)) {
    if (strpos($qr, 'http') === 0) {
        // Supabase URL - always available
        $has_qr = true;
        $qr_display_url = $qr;
    } else {
        // Local path - check file exists
        $qr_full_path = __DIR__ . '/' . $qr;
        if (file_exists($qr_full_path)) {
            $has_qr = true;
            $qr_display_url = $qr;
        }
    }
}
?>

<style>
    .setting-card {
        background: #fff;
        border-radius: 16px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        margin-bottom: 25px;
        overflow: hidden;
    }
    .setting-card-header {
        background: #f8fafc;
        padding: 15px 20px;
        border-bottom: 2px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .setting-card-header h5 {
        margin: 0;
        font-weight: 700;
        color: #1e293b;
        font-size: 1.1rem;
    }
    .setting-card-body { padding: 20px; }
    .view-mode .label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #64748b;
        font-weight: 700;
        margin-bottom: 4px;
        display: block;
    }
    .view-mode .value {
        font-size: 1.1rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 15px;
        word-break: break-word;
    }
    .view-mode .value.empty {
        color: #94a3b8;
        font-style: italic;
    }
    .edit-mode {
        display: none;
        background: #f1f5f9;
        padding: 20px;
        border-radius: 12px;
        margin-top: 10px;
        border: 1px dashed #cbd5e1;
    }
    .btn-xs {
        padding: 4px 12px;
        font-size: 0.8rem;
        border-radius: 20px;
    }
    .storage-info {
        background: #eff6ff;
        border-left: 4px solid #2563eb;
        border-radius: 6px;
        padding: 8px 12px;
        font-size: 0.78rem;
        color: #1e40af;
        margin-bottom: 15px;
    }
</style>

<div class="container-fluid py-4">
    <h3 class="fw-bold mb-4"><i class="fas fa-cog me-2"></i> System Settings</h3>
    
    <?= $message ?>

    <div class="storage-info">
        <i class="fas fa-shield-alt me-1"></i> 
        <strong>Permanent Storage Enabled:</strong> सभी settings और QR code अब डेटाबेस और Supabase Storage में सुरक्षित हैं। Code deploy होने पर भी कुछ नहीं हटेगा।
    </div>

    <!-- ========================================== -->
    <!-- SECTION 1: BANK & CONTACT DETAILS          -->
    <!-- ========================================== -->
    <div class="setting-card">
        <div class="setting-card-header">
            <h5><i class="fas fa-university me-2 text-primary"></i> Company Bank & Contact Details</h5>
            <button class="btn btn-sm btn-outline-primary btn-xs" onclick="toggleEdit('bank')">
                <i class="fas fa-edit me-1"></i> Edit
            </button>
        </div>
        <div class="setting-card-body">
            <div id="bank-view" class="view-mode row">
                <div class="col-md-6">
                    <span class="label">Bank Name</span>
                    <div class="value <?= empty($settings['company_bank_name']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['company_bank_name'] ?: 'Not Set') ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="label">Account Number</span>
                    <div class="value <?= empty($settings['company_account_number']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['company_account_number'] ?: 'Not Set') ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="label">IFSC Code</span>
                    <div class="value <?= empty($settings['company_ifsc']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['company_ifsc'] ?: 'Not Set') ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="label">Branch</span>
                    <div class="value <?= empty($settings['company_branch']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['company_branch'] ?: 'Not Set') ?>
                    </div>
                </div>
                <div class="col-md-4">
                    <span class="label">Default Contact Number</span>
                    <div class="value <?= empty($settings['default_contact']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['default_contact'] ?: 'Not Set') ?>
                    </div>
                </div>
            </div>

            <div id="bank-edit" class="edit-mode">
                <form method="POST">
                    <input type="hidden" name="action" value="save_bank">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold small">Bank Name</label>
                            <input type="text" name="company_bank_name" class="form-control" value="<?= htmlspecialchars($settings['company_bank_name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Account Number</label>
                            <input type="text" name="company_account_number" class="form-control" value="<?= htmlspecialchars($settings['company_account_number']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold small">IFSC Code</label>
                            <input type="text" name="company_ifsc" class="form-control" value="<?= htmlspecialchars($settings['company_ifsc']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold small">Branch</label>
                            <input type="text" name="company_branch" class="form-control" value="<?= htmlspecialchars($settings['company_branch']) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="fw-bold small">Default Contact Number</label>
                            <input type="text" name="default_contact" class="form-control" value="<?= htmlspecialchars($settings['default_contact']) ?>">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="fw-bold small text-danger">Verify Password *</label>
                            <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Bank Details</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('bank')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SECTION 2: REFERRAL PAYOUT DEDUCTIONS      -->
    <!-- ========================================== -->
    <div class="setting-card">
        <div class="setting-card-header">
            <h5><i class="fas fa-percent me-2 text-success"></i> Referral Payout Deductions</h5>
            <button class="btn btn-sm btn-outline-success btn-xs" onclick="toggleEdit('payout')">
                <i class="fas fa-edit me-1"></i> Edit
            </button>
        </div>
        <div class="setting-card-body">
            <div id="payout-view" class="view-mode row">
                <div class="col-md-6">
                    <span class="label">TDS %</span>
                    <div class="value <?= empty($settings['tds_percent']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['tds_percent'] ?: '0') ?> %
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="label">Admin Charge %</span>
                    <div class="value <?= empty($settings['admin_charge_percent']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['admin_charge_percent'] ?: '0') ?> %
                    </div>
                </div>
            </div>

            <div id="payout-edit" class="edit-mode">
                <form method="POST">
                    <input type="hidden" name="action" value="save_payout">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold small">TDS %</label>
                            <input type="number" step="0.01" name="tds_percent" class="form-control" value="<?= htmlspecialchars($settings['tds_percent']) ?>">
                            <small class="text-muted">Default TDS percentage for referral payouts.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Admin Charge %</label>
                            <input type="number" step="0.01" name="admin_charge_percent" class="form-control" value="<?= htmlspecialchars($settings['admin_charge_percent']) ?>">
                            <small class="text-muted">Default Admin Charge percentage for referral payouts.</small>
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="fw-bold small text-danger">Verify Password *</label>
                            <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i> Save Payout Details</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('payout')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SECTION 3: DAILY SPIN SETTINGS             -->
    <!-- ========================================== -->
    <div class="setting-card">
        <div class="setting-card-header">
            <h5><i class="fas fa-coins me-2 text-warning"></i> Daily Spin Coin Settings</h5>
            <button class="btn btn-sm btn-outline-warning btn-xs" onclick="toggleEdit('spin')">
                <i class="fas fa-edit me-1"></i> Edit
            </button>
        </div>
        <div class="setting-card-body">
            <div id="spin-view" class="view-mode row">
                <div class="col-md-6">
                    <span class="label">Min Coins per Spin</span>
                    <div class="value <?= empty($settings['spin_min_coins']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['spin_min_coins'] ?: '0') ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <span class="label">Max Coins per Spin</span>
                    <div class="value <?= empty($settings['spin_max_coins']) ? 'empty' : '' ?>">
                        <?= htmlspecialchars($settings['spin_max_coins'] ?: '0') ?>
                    </div>
                </div>
                <div class="col-12">
                    <small class="text-muted">Per slot total is capped at 22 coins. Adjust min/max to control average.</small>
                </div>
            </div>

            <div id="spin-edit" class="edit-mode">
                <form method="POST">
                    <input type="hidden" name="action" value="save_spin">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="fw-bold small">Min Coins per Spin</label>
                            <input type="number" step="0.01" name="spin_min_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_min_coins']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="fw-bold small">Max Coins per Spin</label>
                            <input type="number" step="0.01" name="spin_max_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_max_coins']) ?>">
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="fw-bold small text-danger">Verify Password *</label>
                            <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-warning btn-sm text-dark"><i class="fas fa-save me-1"></i> Save Spin Settings</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('spin')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- SECTION 4: UPI QR CODE                     -->
    <!-- ========================================== -->
    <div class="setting-card">
        <div class="setting-card-header">
            <h5><i class="fas fa-qrcode me-2 text-info"></i> UPI QR Code (Payment Page)</h5>
            <button class="btn btn-sm btn-outline-info btn-xs" onclick="toggleEdit('qr')">
                <i class="fas fa-edit me-1"></i> Edit / Upload
            </button>
        </div>
        <div class="setting-card-body">
            <div id="qr-view" class="view-mode text-center">
                <?php if ($has_qr): ?>
                    <p class="text-muted mb-2">Current QR Code:</p>
                    <img src="<?= htmlspecialchars($qr_display_url) ?>" style="max-height:200px; border:1px solid #ddd; border-radius:12px; padding:10px; background:white;">
                    <?php if (strpos($qr, 'http') === 0): ?>
                        <div class="mt-2 small text-success"><i class="fas fa-check-circle"></i> Stored on Supabase (permanent)</div>
                    <?php else: ?>
                        <div class="mt-2 small text-warning"><i class="fas fa-exclamation-triangle"></i> Stored locally (may disappear on redeploy)</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="py-4">
                        <i class="fas fa-qrcode fa-3x text-muted opacity-25 mb-2"></i>
                        <p class="text-muted mb-0">No QR code uploaded yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="qr-edit" class="edit-mode">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_qr">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="fw-bold small">Upload New QR Code</label>
                            <input type="file" name="qr_code" class="form-control" accept="image/*">
                            <small class="text-muted">QR will be saved to Supabase Storage (permanent).</small>
                            
                            <?php if ($has_qr): ?>
                                <div class="form-check mt-3 text-danger">
                                    <input class="form-check-input" type="checkbox" name="remove_qr" value="1" id="remove_qr">
                                    <label class="form-check-label fw-bold" for="remove_qr">
                                        Remove existing QR Code
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 mt-3">
                            <label class="fw-bold small text-danger">Verify Password *</label>
                            <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-info btn-sm text-white"><i class="fas fa-upload me-1"></i> Save QR Code</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('qr')">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script>
    function toggleEdit(section) {
        document.getElementById(section + '-view').style.display = 'none';
        document.getElementById(section + '-edit').style.display = 'block';
    }
    function cancelEdit(section) {
        document.getElementById(section + '-edit').style.display = 'none';
        document.getElementById(section + '-view').style.display = 'block';
    }
</script>

<?php include 'footer.php'; ?>
