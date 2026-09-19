<?php
// ============================================================
// 🗄️ Database Connection – Supabase Safe (Pooler Friendly)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- Database Credentials ----
$DB_HOST = getenv('DB_HOST') ?: 'aws-0-ap-south-1.pooler.supabase.com';
$DB_PORT = getenv('DB_PORT') ?: '6543';
$DB_NAME = getenv('DB_NAME') ?: 'postgres';
$DB_USER = getenv('DB_USER') ?: 'postgres.YOUR_PROJECT_ID';
$DB_PASS = getenv('DB_PASS') ?: 'YOUR_PASSWORD';

try {
    $dsn = "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};sslmode=require";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT         => false,
        
        // 🔥 MOST IMPORTANT: Supabase Pooler के लिए
        PDO::ATTR_EMULATE_PREPARES   => true,
        // इससे PDO Client-Side पर Prepare करता है, Server पर नहीं
        // जिससे "prepared statement does not exist" Error खत्म हो जाता है
    ];
    
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
    
    // 🔥 Extra: Server-Side Prepared Statements Disable
    $pdo->exec("SET SESSION STATEMENT_TIMEOUT = '30s'");
    
} catch (PDOException $e) {
    error_log("DB Connection Failed: " . $e->getMessage());
    die("<div style='font-family: sans-serif; padding: 20px; background: #fef2f2; color: #991b1b; border-left: 5px solid #dc2626;'>
            <h3>⚠️ Database Connection Error</h3>
            <p>कृपया कुछ देर बाद दोबारा प्रयास करें।</p>
         </div>");
}

// ============================================================
// 🔥 SAFE QUERY HELPERS (Auto-Retry on Statement Errors)
// ============================================================

/**
 * Safe Query with Auto-Retry
 */
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
            
            // अगर Statement नाम का Error है तो Retry करें
            if (strpos($e->getMessage(), 'does not exist') !== false || 
                strpos($e->getMessage(), '26000') !== false) {
                error_log("Retry #{$attempt} for: " . $sql);
                usleep(100000); // 0.1 sec wait
                continue;
            }
            // दूसरे Errors तो फेंक दें
            throw $e;
        }
    }
    
    throw new Exception("Query failed after {$maxRetries} attempts: " . $sql);
}

/**
 * Safe Fetch All
 */
function safeFetchAll($pdo, $sql, $params = []) {
    try {
        $stmt = safeQuery($pdo, $sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    } catch (Exception $e) {
        error_log("safeFetchAll Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Safe Fetch One Row
 */
function safeFetch($pdo, $sql, $params = []) {
    try {
        $stmt = safeQuery($pdo, $sql, $params);
        return $stmt ? $stmt->fetch() : null;
    } catch (Exception $e) {
        error_log("safeFetch Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Safe Fetch Single Value
 */
function safeFetchColumn($pdo, $sql, $params = []) {
    try {
        $stmt = safeQuery($pdo, $sql, $params);
        return $stmt ? $stmt->fetchColumn() : null;
    } catch (Exception $e) {
        error_log("safeFetchColumn Error: " . $e->getMessage());
        return 0;
    }
}
?>
