<?php
// ============================================================
// 🗄️ Database Connection – Supabase Safe (Pooler Friendly)
// + URL-based Impersonation Session Handler
// ============================================================

$host = getenv('DB_HOST') ?: 'aws-0-ap-northeast-2.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '6543';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.bqspzgwpqimjyhispwtp';
$password = getenv('DB_PASSWORD') ?: 'Primeaug2026';

date_default_timezone_set('Asia/Kolkata');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => false,
        PDO::ATTR_EMULATE_PREPARES   => true,
    ];
    
    $pdo = new PDO($dsn, $user, $password, $options);
    
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("❌ Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}

// ============================================================
// 🔥 SAFE QUERY HELPERS
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
// 🔥 SESSION HANDLER INTEGRATION (URL-based Impersonation Only)
// ============================================================

$sessionHandlerFile = __DIR__ . '/session_handler.php';

if (file_exists($sessionHandlerFile)) {
    require_once $sessionHandlerFile;
    
    if (class_exists('DatabaseSessionHandler')) {
        try {
            if (session_status() == PHP_SESSION_NONE) {
                
                // 🔥 Check ONLY URL and POST for imp_session
                // NEVER check cookies - that caused the stuck issue
                $imp_session_id = null;
                
                if (isset($_GET['imp_session']) && !empty($_GET['imp_session'])) {
                    $imp_session_id = preg_replace('/[^a-f0-9]/', '', $_GET['imp_session']);
                } elseif (isset($_POST['imp_session']) && !empty($_POST['imp_session'])) {
                    $imp_session_id = preg_replace('/[^a-f0-9]/', '', $_POST['imp_session']);
                }
                
                if ($imp_session_id && strlen($imp_session_id) >= 32) {
                    // 🔥 IMPERSONATION MODE: Use URL session ID, NO cookie set
                    ini_set('session.use_cookies', 0);
                    ini_set('session.use_only_cookies', 0);
                    ini_set('session.use_trans_sid', 0);
                    session_name('IMPERSONATE');
                    session_id($imp_session_id);
                    define('IMPERSONATION_MODE', true);
                } else {
                    // 🔥 NORMAL MODE: Always use the same cookie
                    session_name('PRIMEPROP_SESS');
                    define('IMPERSONATION_MODE', false);
                }
                
                $handler = new DatabaseSessionHandler($pdo);
                session_set_save_handler($handler, true);
                
                if (!IMPERSONATION_MODE) {
                    $is_https = (
                        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') 
                        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                    );
                    
                    session_set_cookie_params([
                        'lifetime' => 86400 * 90,
                        'path' => '/',
                        'domain' => '',
                        'secure' => $is_https,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
                
                session_start();
            }
        } catch (Exception $e) {
            error_log("Session Handler Error: " . $e->getMessage());
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
        }
    } else {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
} else {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}
?>
