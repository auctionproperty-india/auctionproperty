<?php
// ============================================================
// 📄 View Admin Document – With Auto-Cleanup of Missing Files
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div style='font-family:sans-serif;padding:20px;color:#dc2626;'>❌ Invalid Property ID</div>");
}

$stmt = $pdo->prepare("SELECT admin_document, title FROM properties WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    die("<div style='font-family:sans-serif;padding:20px;color:#dc2626;'>❌ Property not found</div>");
}

$doc = trim($row['admin_document'] ?? '');

if (empty($doc)) {
    die("<div style='font-family:sans-serif;padding:20px;color:#dc2626;'>❌ No document uploaded.</div>");
}

// Try multiple paths
$clean_doc = ltrim($doc, '/');
$possible_paths = [
    __DIR__ . '/' . $clean_doc,
    __DIR__ . '/' . $doc,
    dirname(__DIR__) . '/' . $clean_doc,
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . $clean_doc,
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . $doc,
];

$found_path = null;
foreach ($possible_paths as $p) {
    if (!empty($p) && file_exists($p) && is_file($p)) {
        $found_path = $p;
        break;
    }
}

// ============================================================
// 🔥 FILE MISSING → Auto-cleanup DB entry + friendly message
// ============================================================
if (!$found_path) {
    // Auto-clean: DB entry clear कर दें ताकि भविष्य में यह error न आए
    try {
        $pdo->prepare("UPDATE properties SET admin_document = NULL WHERE id = ?")->execute([$id]);
    } catch (Exception $e) { /* silent */ }

    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Document Missing</title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    </head>
    <body style='background:#f4f6fa; font-family:sans-serif; padding:30px;'>
        <div style='max-width:600px; margin:60px auto; background:#fff; border-radius:20px; padding:40px 30px; box-shadow:0 10px 40px rgba(0,0,0,0.08); text-align:center;'>

            <div style='width:80px; height:80px; background:#fef2f2; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px;'>
                <i class='fas fa-file-circle-xmark' style='font-size:2rem; color:#dc2626;'></i>
            </div>

            <h3 style='color:#1e293b; font-weight:800; margin-bottom:10px;'>Document Missing</h3>
            <p style='color:#64748b; font-size:0.95rem; margin-bottom:25px;'>
                This property's document was not found on the server.<br>
                The database entry has been cleaned up automatically.
            </p>

            <div style='background:#f8fafc; border-radius:12px; padding:16px; margin-bottom:25px; text-align:left;'>
                <div style='font-size:0.8rem; color:#64748b; margin-bottom:6px;'>
                    <strong>Property:</strong> #" . htmlspecialchars($id) . " – " . htmlspecialchars($row['title']) . "
                </div>
                <div style='font-size:0.8rem; color:#64748b; word-break:break-all;'>
                    <strong>Missing file:</strong> <code style='background:#fff; padding:2px 6px; border-radius:4px;'>" . htmlspecialchars($doc) . "</code>
                </div>
            </div>

            <p style='background:#fffbeb; border-left:4px solid #f59e0b; padding:14px 18px; border-radius:8px; font-size:0.88rem; color:#78350f; text-align:left; margin-bottom:25px;'>
                <strong>💡 What to do:</strong><br>
                Property को edit करके admin document दोबारा upload करें।
            </p>

            <div style='display:flex; gap:10px; justify-content:center; flex-wrap:wrap;'>
                <a href='properties.php?edit=" . $id . "' style='padding:12px 28px; background:#2563eb; color:#fff; text-decoration:none; border-radius:30px; font-weight:600; font-size:0.92rem;'>
                    <i class='fas fa-upload me-1'></i> Re-upload Document
                </a>
                <a href='properties.php' style='padding:12px 28px; background:#e2e8f0; color:#1e293b; text-decoration:none; border-radius:30px; font-weight:600; font-size:0.92rem;'>
                    <i class='fas fa-arrow-left me-1'></i> Back to Properties
                </a>
            </div>
        </div>
    </body>
    </html>
    ");
}

// ============================================================
// STREAM FILE
// ============================================================
$ext = strtolower(pathinfo($found_path, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$mime = $mime_types[$ext] ?? 'application/octet-stream';

if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($found_path));
header('Content-Disposition: inline; filename="' . basename($found_path) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($found_path);
exit;
