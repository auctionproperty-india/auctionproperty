<?php
// ---- Currency ----
function indianCurrencyFormat($number) {
    if ($number === null || $number === '') return '0';
    $number = (float) $number;
    $num = (string) floor($number);
    $len = strlen($num);
    if ($len <= 3) return $num;
    $last = substr($num, -3);
    $rest = substr($num, 0, $len - 3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $rest . ',' . $last;
}

// ---- Subscription ----
function hasActiveSubscription($pdo, $user_id, $property_id = null) {
    if($property_id) {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND property_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id, $property_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id]);
    }
    return $stmt->rowCount() > 0;
}

function userHasActiveSubscription($pdo, $user_id) {
    if(!$user_id) return false;
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->rowCount() > 0;
}

// ---- Permission Helpers ----
function getUserPermissions($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT permissions, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if(!$user) return [];

        if(!empty($user['is_super_admin']) && $user['is_super_admin']) {
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            $full = [];
            foreach($modules as $m) $full[$m] = ['view' => true, 'edit' => true];
            return $full;
        }

        if(empty($user['permissions'])) {
            $default = [];
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            foreach($modules as $m) $default[$m] = ['view' => false, 'edit' => false];
            return $default;
        }

        $perms = json_decode($user['permissions'], true);
        if(!is_array($perms)) $perms = [];

        $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
        $new_perms = [];
        foreach($modules as $mod) {
            if(isset($perms[$mod])) {
                if(is_array($perms[$mod])) {
                    $new_perms[$mod] = [
                        'view' => isset($perms[$mod]['view']) ? (bool)$perms[$mod]['view'] : false,
                        'edit' => isset($perms[$mod]['edit']) ? (bool)$perms[$mod]['edit'] : false
                    ];
                } else {
                    $val = (bool)$perms[$mod];
                    $new_perms[$mod] = ['view' => $val, 'edit' => $val];
                }
            } else {
                $new_perms[$mod] = ['view' => false, 'edit' => false];
            }
        }
        return $new_perms;
    } catch (Exception $e) {
        return [];
    }
}

function hasViewPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['view']) && $perms[$permission]['view'] === true;
}

function hasEditPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['edit']) && $perms[$permission]['edit'] === true;
}

// ---- Referral System ----
function generateReferralCode() { return strtoupper(substr(md5(uniqid()), 0, 8)); }
function getReferrerIdByCode($pdo, $code) {
    if(empty($code)) return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}
function getReferralLink($user_id) {
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    if(!$code) { $new_code = generateReferralCode(); $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$new_code, $user_id]); $code = $new_code; }
    return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $code;
}
function getReferralEarnings($pdo, $user_id, $status = null) {
    $sql = "SELECT e.*, u.name as referred_name, p.name as package_name FROM user_referral_earnings e JOIN users u ON e.referred_user_id = u.id JOIN packages p ON e.package_id = p.id WHERE e.user_id = ?";
    if($status) $sql .= " AND e.status = ?";
    $sql .= " ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if($status) $stmt->execute([$user_id, $status]);
    else $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferredUsers($pdo, $user_id) {
    $sql = "SELECT u.id, u.name, u.email, u.created_at as reg_date, (SELECT s.start_date FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' ORDER BY s.id LIMIT 1) as activation_date FROM users u WHERE u.referred_by = ? ORDER BY u.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferrerName($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT u.name FROM users u JOIN users r ON u.id = r.referred_by WHERE r.id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'N/A';
}
function calculateReferralNet($amount, $tds_percent, $admin_charge_percent) {
    $tds = ($amount * $tds_percent) / 100; $admin_charge = ($amount * $admin_charge_percent) / 100; $net = $amount - $tds - $admin_charge;
    return ['tds' => $tds, 'admin_charge' => $admin_charge, 'net' => $net];
}
function changeReferrer($pdo, $user_id, $new_referrer_id) {
    if($user_id == $new_referrer_id) return false;
    $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_referrer_id, $user_id]);
    return true;
}

// ---- Accounting ----
function addAccountEntry($pdo, $type, $amount, $description, $category, $entry_date = null) {
    if($entry_date === null) $entry_date = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO account_entries (type, amount, description, category, entry_date) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$type, $amount, $description, $category, $entry_date]);
}
function getAccountBalance($pdo) {
    $income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'income'")->fetchColumn();
    $expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense'")->fetchColumn();
    return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
}
function getAccountEntries($pdo, $limit = 100) {
    $stmt = $pdo->query("SELECT * FROM account_entries ORDER BY entry_date DESC, id DESC LIMIT $limit");
    return $stmt->fetchAll();
}

// ===== 🆕 WALLET FUNCTIONS =====
function getUserWalletBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (float) $stmt->fetchColumn();
}

function creditWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'credit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}

function debitWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $balance = getUserWalletBalance($pdo, $user_id);
    if($balance < $amount) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'debit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}

// ---- Social Image ----
function generateSocialCard($property) {
    // (keep your existing code or placeholder)
    return $property['image_url'] ?? '';
}
function saveImage($img) { /* ... */ }
?>
