<?php
// ---- ALL EXISTING FUNCTIONS (indianCurrencyFormat, hasActiveSubscription, etc.) ----
// ... (नीचे नए Functions डालें) ...

// ---- Referral Functions ----
function generateReferralCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function getReferrerIdByCode($pdo, $code) {
    if(empty($code)) return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}

function getReferralLink($user_id) {
    $stmt = $GLOBALS['pdo']->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $code;
}

function getReferralEarnings($pdo, $user_id, $status = null) {
    $sql = "SELECT e.*, u.name as referred_name, p.name as package_name 
            FROM user_referral_earnings e 
            JOIN users u ON e.referred_user_id = u.id 
            JOIN packages p ON e.package_id = p.id 
            WHERE e.user_id = ?";
    if($status) $sql .= " AND e.status = ?";
    $sql .= " ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if($status) $stmt->execute([$user_id, $status]);
    else $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function calculateReferralNet($amount, $tds_percent, $admin_charge_percent) {
    $tds = ($amount * $tds_percent) / 100;
    $admin_charge = ($amount * $admin_charge_percent) / 100;
    $net = $amount - $tds - $admin_charge;
    return ['tds' => $tds, 'admin_charge' => $admin_charge, 'net' => $net];
}
?>
