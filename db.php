<?php
// ============================================================
// db.php – Complete Database Connection (Stable – 22 Aug)
// ============================================================

// ----- Read Environment Variables from Render -----
$host = getenv('DB_HOST') ?: 'aws-0-ap-northeast-2.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '6543';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.bqspzgwpqimjyhispwtp';
$password = getenv('DB_PASSWORD') ?: 'Dugguai20143';

// ----- Fallback: If individual variables are not set, try DATABASE_URL -----
$database_url = getenv('DATABASE_URL');
if ($database_url && (!$host || $host === 'aws-0-ap-northeast-2.pooler.supabase.com')) {
    $parts = parse_url($database_url);
    $host = $parts['host'];
    $port = $parts['port'] ?? 6543;
    $dbname = ltrim($parts['path'], '/');
    $user = $parts['user'];
    $password = $parts['pass'];
}

// ----- Set Timezone -----
date_default_timezone_set('Asia/Kolkata');

// ----- Connect to Supabase -----
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Database Connection Failed: " . $e->getMessage());
}

// ----- Create sessions table (if not exists) -----
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            access TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (PDOException $e) {
    // ignore if table already exists
}

// ----- Session Handler (Database-based) -----
require_once __DIR__ . '/session_handler.php';

$handler = new DatabaseSessionHandler($pdo);

if (session_status() == PHP_SESSION_NONE) {
    session_set_save_handler($handler, true);
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// ============================================================
// 🔥 सभी functions जो पहले db.php में थे – अब यहाँ हैं
// ============================================================

// ---- Currency Formatting ----
if (!function_exists('indianCurrencyFormat')) {
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
}

// ---- Subscription Check ----
if (!function_exists('userHasActiveSubscription')) {
    function userHasActiveSubscription($pdo, $user_id) {
        if (!$user_id) return false;
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE LIMIT 1");
        $stmt->execute([$user_id]);
        return $stmt->rowCount() > 0;
    }
}

// ---- Get User Permissions ----
if (!function_exists('getUserPermissions')) {
    function getUserPermissions($user_id, $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT permissions, is_super_admin FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if (!$user) return [];
            if (!empty($user['is_super_admin']) && $user['is_super_admin']) {
                $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
                $full = [];
                foreach ($modules as $m) $full[$m] = ['view' => true, 'edit' => true];
                return $full;
            }
            if (empty($user['permissions'])) {
                $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
                $default = [];
                foreach ($modules as $m) $default[$m] = ['view' => false, 'edit' => false];
                return $default;
            }
            $perms = json_decode($user['permissions'], true);
            if (!is_array($perms)) $perms = [];
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            $new_perms = [];
            foreach ($modules as $mod) {
                if (isset($perms[$mod])) {
                    if (is_array($perms[$mod])) {
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
}

// ---- Permission Check ----
if (!function_exists('hasViewPermission')) {
    function hasViewPermission($permission, $pdo) {
        if (!isset($_SESSION['user_id'])) return false;
        $perms = getUserPermissions($_SESSION['user_id'], $pdo);
        return isset($perms[$permission]['view']) && $perms[$permission]['view'] === true;
    }
}

if (!function_exists('hasEditPermission')) {
    function hasEditPermission($permission, $pdo) {
        if (!isset($_SESSION['user_id'])) return false;
        $perms = getUserPermissions($_SESSION['user_id'], $pdo);
        return isset($perms[$permission]['edit']) && $perms[$permission]['edit'] === true;
    }
}

// ---- Activity Log ----
if (!function_exists('logActivity')) {
    function logActivity($pdo, $user_id, $activity_type, $details = null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        try {
            $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, activity_type, details, ip_address) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$user_id, $activity_type, $details, $ip]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

// ---- Referral Functions ----
if (!function_exists('generateReferralCode')) {
    function generateReferralCode() {
        return strtoupper(substr(md5(uniqid()), 0, 8));
    }
}

if (!function_exists('getReferrerIdByCode')) {
    function getReferrerIdByCode($pdo, $code) {
        if (empty($code)) return null;
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->execute([$code]);
        $user = $stmt->fetch();
        return $user ? $user['id'] : null;
    }
}

// ---- Spin Functions ----
if (!function_exists('getCurrentSlot')) {
    function getCurrentSlot() {
        $hour = (int)date('H');
        if ($hour >= 0 && $hour < 8) return 1;
        if ($hour >= 8 && $hour < 14) return 2;
        return 3;
    }
}

if (!function_exists('getSlotTimeRange')) {
    function getSlotTimeRange($slot) {
        switch ($slot) {
            case 1: return '12 AM – 8 AM';
            case 2: return '8 AM – 2 PM';
            case 3: return '2 PM – 12 AM';
            default: return 'Unknown';
        }
    }
}

// ---- Accounting Functions ----
if (!function_exists('addAccountEntry')) {
    function addAccountEntry($pdo, $type, $amount, $description, $category, $entry_date = null) {
        if ($entry_date === null) $entry_date = date('Y-m-d');
        $sql = sprintf(
            "INSERT INTO account_entries (type, amount, description, category, entry_date) VALUES (%s, %s, %s, %s, %s)",
            $pdo->quote($type),
            (float)$amount,
            $pdo->quote($description),
            $pdo->quote($category),
            $pdo->quote($entry_date)
        );
        return $pdo->exec($sql) !== false;
    }
}

if (!function_exists('getAccountBalance')) {
    function getAccountBalance($pdo) {
        $income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'income'")->fetchColumn();
        $expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense'")->fetchColumn();
        return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
    }
}

// ---- Wallet Functions ----
if (!function_exists('getUserWalletBalance')) {
    function getUserWalletBalance($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return (float)$stmt->fetchColumn();
    }
}

if (!function_exists('creditWallet')) {
    function creditWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
        if ($amount <= 0) return false;
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'credit', ?, ?)");
        return $stmt->execute([$user_id, $amount, $description, $reference_id]);
    }
}

if (!function_exists('debitWallet')) {
    function debitWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
        if ($amount <= 0) return false;
        $balance = getUserWalletBalance($pdo, $user_id);
        if ($balance < $amount) return false;
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);
        $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'debit', ?, ?)");
        return $stmt->execute([$user_id, $amount, $description, $reference_id]);
    }
}

// ============================================================
// ✅ $pdo अब globally available है – और सभी functions भी
// ============================================================
?>
