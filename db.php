<?php
// ============================================================
// 🗄️ Database Connection – Supabase Safe (Pooler Friendly)
// + Session Handler (Custom) + Impersonation Support
// ============================================================

$host = getenv('DB_HOST') ?: 'aws-0-ap-northeast-2.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '6543';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.bqspzgwpqimjyhispwtp';
$password = getenv('DB_PASSWORD') ?: 'Primeaug2026';

date_default_timezone_set('Asia/Kolkata');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    // 🔥 FIX: Supabase Pooler के लिए ज़रूरी Options
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => false,
        
        // 🔥 MOST IMPORTANT: Prepared Statement Error Fix
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];
    
    $pdo = new PDO($dsn, $user, $password, $options);
    
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("❌ Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}

// ============================================================
// 🔥 SAFE QUERY HELPERS (Auto-Retry on Statement Errors)
// ============================================================

if (!function_exists('safeQuery')) {
    function safeQuery($pdo, $sql, $params = []) {
        $maxRetries = 3;
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                if (empty($params)) {
                    return $pdo->query($sql);
                } else {
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    return $stmt;
                }
            } catch (PDOException $e) {
                $attempt++;
                $msg = $e->getMessage();
                if (strpos($msg, 'does not exist') !== false || strpos($msg, '26000') !== false || strpos($msg, 'server closed the connection') !== false) {
                    error_log("SafeQuery Retry #{$attempt}: " . $msg);
                    usleep(150000);
                    continue;
                }
                throw $e;
            }
        }
        throw new Exception("Query failed after {$maxRetries} attempts");
    }
}

if (!function_exists('safeFetchAll')) {
    function safeFetchAll($pdo, $sql, $params = []) {
        try {
            $stmt = safeQuery($pdo, $sql, $params);
            return $stmt ? $stmt->fetchAll() : [];
        } catch (Exception $e) {
            error_log("safeFetchAll Error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('safeFetch')) {
    function safeFetch($pdo, $sql, $params = []) {
        try {
            $stmt = safeQuery($pdo, $sql, $params);
            return $stmt ? $stmt->fetch() : null;
        } catch (Exception $e) {
            error_log("safeFetch Error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('safeFetchColumn')) {
    function safeFetchColumn($pdo, $sql, $params = []) {
        try {
            $stmt = safeQuery($pdo, $sql, $params);
            return $stmt ? $stmt->fetchColumn() : null;
        } catch (Exception $e) {
            error_log("safeFetchColumn Error: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('safeExecute')) {
    function safeExecute($pdo, $sql, $params = []) {
        $maxRetries = 3;
        $attempt = 0;
        while ($attempt < $maxRetries) {
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                $attempt++;
                $msg = $e->getMessage();
                if (strpos($msg, 'does not exist') !== false || strpos($msg, '26000') !== false || strpos($msg, 'server closed the connection') !== false) {
                    error_log("SafeExecute Retry #{$attempt}: " . $msg);
                    usleep(150000);
                    continue;
                }
                throw $e;
            }
        }
        throw new Exception("Execute failed after {$maxRetries} attempts");
    }
}

// ============================================================
// 🔥 IMPERSONATION SUPPORT – Detect "Login as User" Mode
// ============================================================
// When admin clicks "Login as User", the popup window uses a 
// different session name ("IMPERSONATE") so it doesn't affect
// the main admin session. We detect this via ?imp=1 URL param.

$is_impersonate_mode = (isset($_GET['imp']) && $_GET['imp'] == '1') 
                    || (isset($_POST['imp']) && $_POST['imp'] == '1');

// ============================================================
// 🔥 SESSION HANDLER INTEGRATION
// ============================================================

$sessionHandlerFile = __DIR__ . '/session_handler.php';

if (file_exists($sessionHandlerFile)) {
    require_once $sessionHandlerFile;
    
    if (class_exists('DatabaseSessionHandler')) {
        try {
            $handler = new DatabaseSessionHandler($pdo);
            
            if (session_status() == PHP_SESSION_NONE) {
                // Use different session name for impersonation
                if ($is_impersonate_mode) {
                    session_name('IMPERSONATE');
                } else {
                    session_name('PHPSESSID');
                }
                
                session_set_save_handler($handler, true);
                session_set_cookie_params([
                    'lifetime' => 86400 * 30, // 30 Days
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                session_start();
            }
        } catch (Exception $e) {
            error_log("Session Handler Error: " . $e->getMessage());
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
        }
    }
} else {
    // Fallback agar session_handler.php na ho
    if (session_status() == PHP_SESSION_NONE) {
        if ($is_impersonate_mode) {
            session_name('IMPERSONATE');
        }
        session_start();
    }
}
?>
