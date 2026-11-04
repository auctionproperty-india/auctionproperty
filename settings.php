<?php
// ============================================================
// ⚙️ System Settings – Permanent Storage (Supabase) + Tabbed Sidebar
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
    'spin_min_coins', 'spin_max_coins', 'company_qr_code',
    'promo_banner_title', 'promo_message_template',
    
    // Email Toggles & Time
    'email_on_registration', 'email_on_daily_props', 'email_on_downline_reg',
    'email_on_payout_req', 'email_on_payout_approved', 'email_on_package_buy', 'email_on_package_expired',
    'daily_email_time', 'last_daily_email_date',

    // Email Templates
    'tpl_registration_subject', 'tpl_registration_body',
    'tpl_downline_reg_subject', 'tpl_downline_reg_body',
    'tpl_daily_props_subject', 'tpl_daily_props_body',
    'tpl_payout_req_subject', 'tpl_payout_req_body',
    'tpl_payout_approved_subject', 'tpl_payout_approved_body',
    'tpl_package_buy_subject', 'tpl_package_buy_body',
    'tpl_package_expired_subject', 'tpl_package_expired_body'
];

// 🔒 Ensure all keys exist
foreach ($settings_keys as $key) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, '') 
            ON CONFLICT (setting_key) DO NOTHING
        ");
        $stmt->execute([$key]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, '')")->execute([$key]);
        }
    }
}

// ---- Handle POST Actions ----
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
        
        // 4. SAVE QR CODE
        elseif ($action == 'save_qr') {
            $old_qr = '';
            try {
                $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'company_qr_code'");
                $stmt->execute();
                $old_qr = $stmt->fetchColumn() ?: '';
            } catch (Exception $e) {}
            
            if (isset($_POST['remove_qr']) && $_POST['remove_qr'] == '1') {
                if (!empty($old_qr)) deleteCloudFileByUrl($old_qr);
                $saveSetting('company_qr_code', '');
                $message = "<div class='alert alert-success'>✅ QR Code removed successfully!</div>";
            } elseif(isset($_FILES['qr_code']) && $_FILES['qr_code']['error'] == 0) {
                $uploaded_url = uploadFileToCloud($_FILES['qr_code'], 'qr_codes');
                if ($uploaded_url) {
                    if (!empty($old_qr)) deleteCloudFileByUrl($old_qr);
                    $saveSetting('company_qr_code', $uploaded_url);
                    $message = "<div class='alert alert-success'>✅ New QR Code uploaded!</div>";
                } else {
                    $message = "<div class='alert alert-danger'>❌ QR Code upload failed! Check keys/bucket.</div>";
                }
            } else {
                $message = "<div class='alert alert-warning'>⚠️ No file selected.</div>";
            }
        }

        // 🔥 5. SAVE PROMO BANNER & MESSAGE
        elseif ($action == 'save_promo') {
            $saveSetting('promo_banner_title', trim($_POST['promo_banner_title'] ?? ''));
            $saveSetting('promo_message_template', trim($_POST['promo_message_template'] ?? ''));
            $message = "<div class='alert alert-success'>✅ Promo Banner & Message updated!</div>";
        }

        // 🔥 6. SAVE EMAIL SETTINGS & TEMPLATES
        elseif ($action == 'save_email_settings') {
            // Save toggles (1 or 0)
            $toggles = ['email_on_registration', 'email_on_daily_props', 'email_on_downline_reg', 'email_on_payout_req', 'email_on_payout_approved', 'email_on_package_buy', 'email_on_package_expired'];
            foreach ($toggles as $key) {
                $saveSetting($key, isset($_POST[$key]) ? '1' : '0');
            }
            
            // Save Email Time
            $saveSetting('daily_email_time', trim($_POST['daily_email_time'] ?? '06:30'));

            // Save templates
            $templates = [
                'tpl_registration_subject', 'tpl_registration_body', 'tpl_downline_reg_subject', 'tpl_downline_reg_body',
                'tpl_daily_props_subject', 'tpl_daily_props_body', 'tpl_payout_req_subject', 'tpl_payout_req_body',
                'tpl_payout_approved_subject', 'tpl_payout_approved_body', 'tpl_package_buy_subject', 'tpl_package_buy_body',
                'tpl_package_expired_subject', 'tpl_package_expired_body'
            ];
            foreach ($templates as $key) {
                $saveSetting($key, trim($_POST[$key] ?? ''));
            }
            $message = "<div class='alert alert-success'>✅ Email Settings & Templates updated!</div>";
        }
    }
}

include 'header.php';

// ---- Fetch current values ----
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
        $has_qr = true;
        $qr_display_url = $qr;
    } else {
        $qr_full_path = __DIR__ . '/' . $qr;
        if (file_exists($qr_full_path)) {
            $has_qr = true;
            $qr_display_url = $qr;
        }
    }
}
?>

<style>
    /* 🔥 Sidebar & Tab Layout CSS */
    .settings-layout { display: flex; gap: 25px; align-items: flex-start; }
    .settings-sidebar { width: 260px; flex-shrink: 0; background: #fff; border-radius: 16px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); position: sticky; top: 90px; }
    .settings-sidebar .nav-link { display: flex; align-items: center; padding: 12px 18px; margin-bottom: 5px; border-radius: 10px; color: #475569; font-weight: 600; border: none; text-align: left; transition: 0.2s; font-size: 0.95rem; width: 100%; }
    .settings-sidebar .nav-link:hover { background: #f1f5f9; color: #1e3a8a; }
    .settings-sidebar .nav-link.active { background: #eff6ff; color: #2563eb; border-left: 3px solid #2563eb; }
    .settings-sidebar .nav-link i { width: 24px; font-size: 1.1rem; margin-right: 10px; }
    
    .settings-content { flex-grow: 1; min-width: 0; }

    .setting-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03); overflow: hidden; }
    .setting-card-header { background: #f8fafc; padding: 15px 20px; border-bottom: 2px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    .setting-card-header h5 { margin: 0; font-weight: 700; color: #1e293b; font-size: 1.1rem; }
    .setting-card-body { padding: 20px; }
    .view-mode .label { font-size: 0.75rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px; display: block; }
    .view-mode .value { font-size: 1.1rem; font-weight: 600; color: #0f172a; margin-bottom: 15px; word-break: break-word; }
    .view-mode .value.empty { color: #94a3b8; font-style: italic; }
    .edit-mode { display: none; background: #f1f5f9; padding: 20px; border-radius: 12px; margin-top: 10px; border: 1px dashed #cbd5e1; }
    .btn-xs { padding: 4px 12px; font-size: 0.8rem; border-radius: 20px; }
    .storage-info { background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; padding: 8px 12px; font-size: 0.78rem; color: #1e40af; margin-bottom: 15px; }

    @media (max-width: 991px) {
        .settings-layout { flex-direction: column; }
        .settings-sidebar { width: 100%; position: static; display: flex; overflow-x: auto; gap: 10px; padding: 10px; }
        .settings-sidebar .nav-link { white-space: nowrap; margin-bottom: 0; padding: 8px 15px; width: auto; }
    }
</style>

<div class="container-fluid py-4">
    <h3 class="fw-bold mb-4"><i class="fas fa-cog me-2"></i> System Settings</h3>
    
    <?= $message ?>

    <div class="storage-info">
        <i class="fas fa-shield-alt me-1"></i> 
        <strong>Permanent Storage Enabled:</strong> सभी settings और QR code अब डेटाबेस और Supabase Storage में सुरक्षित हैं।
    </div>

    <div class="settings-layout">
        <!-- 🔥 LEFT SIDEBAR MENU -->
        <div class="settings-sidebar">
            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                <button class="nav-link active" id="tab-bank-link" data-bs-toggle="pill" data-bs-target="#tab-bank" type="button" role="tab"><i class="fas fa-university text-primary"></i> Bank & Contact</button>
                <button class="nav-link" id="tab-payout-link" data-bs-toggle="pill" data-bs-target="#tab-payout" type="button" role="tab"><i class="fas fa-percent text-success"></i> Payout Deductions</button>
                <button class="nav-link" id="tab-spin-link" data-bs-toggle="pill" data-bs-target="#tab-spin" type="button" role="tab"><i class="fas fa-coins text-warning"></i> Spin Settings</button>
                <button class="nav-link" id="tab-qr-link" data-bs-toggle="pill" data-bs-target="#tab-qr" type="button" role="tab"><i class="fas fa-qrcode text-info"></i> UPI QR Code</button>
                <button class="nav-link" id="tab-promo-link" data-bs-toggle="pill" data-bs-target="#tab-promo" type="button" role="tab"><i class="fas fa-bullhorn text-danger"></i> Promo Banner</button>
                <button class="nav-link" id="tab-email-link" data-bs-toggle="pill" data-bs-target="#tab-email" type="button" role="tab"><i class="fas fa-envelope text-primary"></i> Email Settings</button>
            </div>
        </div>

        <!-- 🔥 RIGHT CONTENT AREA -->
        <div class="settings-content">
            <div class="tab-content" id="v-pills-tabContent">
                
                <!-- TAB 1: BANK -->
                <div class="tab-pane fade show active" id="tab-bank" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-university me-2 text-primary"></i> Company Bank & Contact Details</h5>
                            <button class="btn btn-sm btn-outline-primary btn-xs" onclick="toggleEdit('bank')"><i class="fas fa-edit me-1"></i> Edit</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="bank-view" class="view-mode row">
                                <div class="col-md-6"><span class="label">Bank Name</span><div class="value <?= empty($settings['company_bank_name']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['company_bank_name'] ?: 'Not Set') ?></div></div>
                                <div class="col-md-6"><span class="label">Account Number</span><div class="value <?= empty($settings['company_account_number']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['company_account_number'] ?: 'Not Set') ?></div></div>
                                <div class="col-md-4"><span class="label">IFSC Code</span><div class="value <?= empty($settings['company_ifsc']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['company_ifsc'] ?: 'Not Set') ?></div></div>
                                <div class="col-md-4"><span class="label">Branch</span><div class="value <?= empty($settings['company_branch']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['company_branch'] ?: 'Not Set') ?></div></div>
                                <div class="col-md-4"><span class="label">Default Contact Number</span><div class="value <?= empty($settings['default_contact']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['default_contact'] ?: 'Not Set') ?></div></div>
                            </div>
                            <div id="bank-edit" class="edit-mode">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_bank">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="fw-bold small">Bank Name</label><input type="text" name="company_bank_name" class="form-control" value="<?= htmlspecialchars($settings['company_bank_name']) ?>"></div>
                                        <div class="col-md-6"><label class="fw-bold small">Account Number</label><input type="text" name="company_account_number" class="form-control" value="<?= htmlspecialchars($settings['company_account_number']) ?>"></div>
                                        <div class="col-md-4"><label class="fw-bold small">IFSC Code</label><input type="text" name="company_ifsc" class="form-control" value="<?= htmlspecialchars($settings['company_ifsc']) ?>"></div>
                                        <div class="col-md-4"><label class="fw-bold small">Branch</label><input type="text" name="company_branch" class="form-control" value="<?= htmlspecialchars($settings['company_branch']) ?>"></div>
                                        <div class="col-md-4"><label class="fw-bold small">Default Contact Number</label><input type="text" name="default_contact" class="form-control" value="<?= htmlspecialchars($settings['default_contact']) ?>"></div>
                                        <div class="col-md-4 mt-3"><label class="fw-bold small text-danger">Verify Password *</label><input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required></div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Bank Details</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('bank')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: PAYOUT -->
                <div class="tab-pane fade" id="tab-payout" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-percent me-2 text-success"></i> Referral Payout Deductions</h5>
                            <button class="btn btn-sm btn-outline-success btn-xs" onclick="toggleEdit('payout')"><i class="fas fa-edit me-1"></i> Edit</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="payout-view" class="view-mode row">
                                <div class="col-md-6"><span class="label">TDS %</span><div class="value <?= empty($settings['tds_percent']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['tds_percent'] ?: '0') ?> %</div></div>
                                <div class="col-md-6"><span class="label">Admin Charge %</span><div class="value <?= empty($settings['admin_charge_percent']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['admin_charge_percent'] ?: '0') ?> %</div></div>
                            </div>
                            <div id="payout-edit" class="edit-mode">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_payout">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="fw-bold small">TDS %</label><input type="number" step="0.01" name="tds_percent" class="form-control" value="<?= htmlspecialchars($settings['tds_percent']) ?>"><small class="text-muted">Default TDS percentage for referral payouts.</small></div>
                                        <div class="col-md-6"><label class="fw-bold small">Admin Charge %</label><input type="number" step="0.01" name="admin_charge_percent" class="form-control" value="<?= htmlspecialchars($settings['admin_charge_percent']) ?>"><small class="text-muted">Default Admin Charge percentage for referral payouts.</small></div>
                                        <div class="col-md-4 mt-3"><label class="fw-bold small text-danger">Verify Password *</label><input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required></div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-save me-1"></i> Save Payout Details</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('payout')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: SPIN -->
                <div class="tab-pane fade" id="tab-spin" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-coins me-2 text-warning"></i> Daily Spin Coin Settings</h5>
                            <button class="btn btn-sm btn-outline-warning btn-xs" onclick="toggleEdit('spin')"><i class="fas fa-edit me-1"></i> Edit</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="spin-view" class="view-mode row">
                                <div class="col-md-6"><span class="label">Min Coins per Spin</span><div class="value <?= empty($settings['spin_min_coins']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['spin_min_coins'] ?: '0') ?></div></div>
                                <div class="col-md-6"><span class="label">Max Coins per Spin</span><div class="value <?= empty($settings['spin_max_coins']) ? 'empty' : '' ?>"><?= htmlspecialchars($settings['spin_max_coins'] ?: '0') ?></div></div>
                                <div class="col-12"><small class="text-muted">Per slot total is capped at 22 coins. Adjust min/max to control average.</small></div>
                            </div>
                            <div id="spin-edit" class="edit-mode">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_spin">
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="fw-bold small">Min Coins per Spin</label><input type="number" step="0.01" name="spin_min_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_min_coins']) ?>"></div>
                                        <div class="col-md-6"><label class="fw-bold small">Max Coins per Spin</label><input type="number" step="0.01" name="spin_max_coins" class="form-control" value="<?= htmlspecialchars($settings['spin_max_coins']) ?>"></div>
                                        <div class="col-md-4 mt-3"><label class="fw-bold small text-danger">Verify Password *</label><input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required></div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-warning btn-sm text-dark"><i class="fas fa-save me-1"></i> Save Spin Settings</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('spin')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: QR CODE -->
                <div class="tab-pane fade" id="tab-qr" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-qrcode me-2 text-info"></i> UPI QR Code (Payment Page)</h5>
                            <button class="btn btn-sm btn-outline-info btn-xs" onclick="toggleEdit('qr')"><i class="fas fa-edit me-1"></i> Edit / Upload</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="qr-view" class="view-mode text-center">
                                <?php if ($has_qr): ?>
                                    <p class="text-muted mb-2">Current QR Code:</p>
                                    <img src="<?= htmlspecialchars($qr_display_url) ?>" style="max-height:200px; border:1px solid #ddd; border-radius:12px; padding:10px; background:white;">
                                    <?php if (strpos($qr, 'http') === 0): ?>
                                        <div class="mt-2 small text-success"><i class="fas fa-check-circle"></i> Stored on Supabase (Permanent)</div>
                                    <?php else: ?>
                                        <div class="mt-2 small text-warning"><i class="fas fa-exclamation-triangle"></i> Stored locally (Will delete on deploy!)</div>
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
                                            <small class="text-muted">QR will be saved directly to Supabase Cloud Storage.</small>
                                            <?php if ($has_qr): ?>
                                                <div class="form-check mt-3 text-danger">
                                                    <input class="form-check-input" type="checkbox" name="remove_qr" value="1" id="remove_qr">
                                                    <label class="form-check-label fw-bold" for="remove_qr">Remove existing QR Code</label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-4 mt-3"><label class="fw-bold small text-danger">Verify Password *</label><input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required></div>
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

                <!-- TAB 5: PROMO -->
                <div class="tab-pane fade" id="tab-promo" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-bullhorn me-2 text-danger"></i> Sales Team Promo Message & Banner</h5>
                            <button class="btn btn-sm btn-outline-danger btn-xs" onclick="toggleEdit('promo')"><i class="fas fa-edit me-1"></i> Edit</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="promo-view" class="view-mode row">
                                <div class="col-md-12"><span class="label">Banner Heading</span><div class="value"><?= htmlspecialchars($settings['promo_banner_title'] ?: 'Not Set') ?></div></div>
                                <div class="col-md-12 mt-3"><span class="label">Message Template</span><div class="value" style="white-space: pre-wrap; background:#f8fafc; padding:15px; border-radius:8px; font-size:0.9rem;"><?= htmlspecialchars($settings['promo_message_template'] ?: 'Not Set') ?></div></div>
                            </div>
                            <div id="promo-edit" class="edit-mode">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_promo">
                                    <div class="row g-3">
                                        <div class="col-md-12"><label class="fw-bold small">Banner Heading</label><input type="text" name="promo_banner_title" class="form-control" value="<?= htmlspecialchars($settings['promo_banner_title'] ?? '') ?>" placeholder="e.g. Happy Diwali Special Offer!"></div>
                                        <div class="col-md-12">
                                            <label class="fw-bold small">Message Template</label>
                                            <textarea name="promo_message_template" class="form-control" rows="10"><?= htmlspecialchars($settings['promo_message_template'] ?? '') ?></textarea>
                                            <small class="text-danger mt-2 d-block"><b>⚠️ इन कोड्स को बदलें नहीं:</b> {name}, {email}, {date}, {website}</small>
                                        </div>
                                        <div class="col-md-4 mt-3"><label class="fw-bold small text-danger">Verify Password *</label><input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required></div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-save me-1"></i> Save Promo Settings</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('promo')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 6: EMAIL SETTINGS -->
                <div class="tab-pane fade" id="tab-email" role="tabpanel">
                    <div class="setting-card">
                        <div class="setting-card-header">
                            <h5><i class="fas fa-envelope me-2 text-primary"></i> Email Notification Settings</h5>
                            <button class="btn btn-sm btn-outline-primary btn-xs" onclick="toggleEdit('email')"><i class="fas fa-edit me-1"></i> Edit</button>
                        </div>
                        <div class="setting-card-body">
                            <div id="email-view" class="view-mode">
                                <div class="alert alert-info py-2 mb-0" style="font-size:0.85rem;">यहाँ से आप चुन सकते हैं कि किस-किस घटना पर ग्राहक को ईमेल जाए।</div>
                            </div>

                            <div id="email-edit" class="edit-mode">
                                <form method="POST">
                                    <input type="hidden" name="action" value="save_email_settings">
                                    <div class="row g-3">
                                        
                                        <!-- 🔥 DYNAMIC EMAIL TIME SETTING -->
                                        <div class="col-md-12 p-3 bg-white rounded border mb-3">
                                            <label class="fw-bold text-primary"><i class="fas fa-clock"></i> Daily Email Send Time (24-Hour Format)</label>
                                            <input type="time" name="daily_email_time" class="form-control form-control-sm mt-2" style="max-width:200px;" value="<?= htmlspecialchars($settings['daily_email_time'] ?: '06:30') ?>">
                                            <small class="text-muted d-block mt-1">इस समय पर रोजाना ईमेल जाएंगे (जैसे 06:30)</small>
                                        </div>

                                        <!-- Toggles -->
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="email_on_registration" value="1" <?= ($settings['email_on_registration'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Welcome Email (Registration)</label>
                                            </div>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="email_on_downline_reg" value="1" <?= ($settings['email_on_downline_reg'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Downline Registered (Team Alert)</label>
                                            </div>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="email_on_daily_props" value="1" <?= ($settings['email_on_daily_props'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Daily New Properties</label>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="email_on_payout_req" value="1" <?= ($settings['email_on_payout_req'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Payout Request (To Admin)</label>
                                            </div>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="email_on_payout_approved" value="1" <?= ($settings['email_on_payout_approved'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Payout Approved (To User)</label>
                                            </div>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="email_on_package_buy" value="1" <?= ($settings['email_on_package_buy'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Package Purchased (To User)</label>
                                            </div>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" name="email_on_package_expired" value="1" <?= ($settings['email_on_package_expired'] ?? '0') == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-bold">Package Expired (To User)</label>
                                            </div>
                                        </div>

                                        <!-- Templates -->
                                        <div class="col-12 mt-4">
                                            <label class="fw-bold text-primary small mb-2 d-block">Edit Email Templates</label>
                                            
                                            <div class="mb-3 p-3 bg-white rounded border">
                                                <label class="small fw-bold text-dark">Registration Email Body (Placeholder: {name}, {email}, {password})</label>
                                                <textarea name="tpl_registration_body" class="form-control" rows="3"><?= htmlspecialchars($settings['tpl_registration_body'] ?? '') ?></textarea>
                                            </div>

                                            <div class="mb-3 p-3 bg-white rounded border">
                                                <label class="small fw-bold text-dark">Downline Email Body (Placeholder: {sponsor_name}, {downline_name}, {downline_email})</label>
                                                <textarea name="tpl_downline_reg_body" class="form-control" rows="3"><?= htmlspecialchars($settings['tpl_downline_reg_body'] ?? '') ?></textarea>
                                            </div>

                                            <div class="mb-3 p-3 bg-white rounded border">
                                                <label class="small fw-bold text-dark">Daily Properties Email Body (Placeholder: {name}, {date}, {property_list})</label>
                                                <textarea name="tpl_daily_props_body" class="form-control" rows="3"><?= htmlspecialchars($settings['tpl_daily_props_body'] ?? '') ?></textarea>
                                            </div>

                                            <div class="mb-3 p-3 bg-white rounded border">
                                                <label class="small fw-bold text-dark">Payout Approved Email Body (Placeholder: {name}, {amount})</label>
                                                <textarea name="tpl_payout_approved_body" class="form-control" rows="3"><?= htmlspecialchars($settings['tpl_payout_approved_body'] ?? '') ?></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-4 mt-3">
                                            <label class="fw-bold small text-danger">Verify Password *</label>
                                            <input type="password" name="admin_password" class="form-control" placeholder="Enter admin password" required>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i> Save Email Settings</button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="cancelEdit('email')">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
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
