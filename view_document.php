<?php
// ============================================================
// 📄 View Admin Document – Robust Path Resolver
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
    die("<div style='font-family:sans-serif;padding:20px;color:#dc2626;'>❌ No document uploaded for this property.</div>");
}

// ============================================================
// 🔥 TRY MULTIPLE POSSIBLE PATHS
// ============================================================
$clean_doc = ltrim($doc, '/');

$possible_paths = [
    __DIR__ . '/' . $clean_doc,                       // /var/www/html/uploads/...
    __DIR__ . '/' . $doc,                             // with leading slash
    dirname(__DIR__) . '/' . $clean_doc,              // parent dir
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . $clean_doc,
    ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/' . $doc,
    __DIR__ . '/uploads/admin_documents/' . basename($clean_doc),
];

$found_path = null;
foreach ($possible_paths as $p) {
    if (!empty($p) && file_exists($p) && is_file($p)) {
        $found_path = $p;
        break;
    }
}

// ============================================================
// 🔥 IF NOT FOUND – Detailed debug info
// ============================================================
if (!$found_path) {
    $debug_paths = implode('<br>', array_map('htmlspecialchars', array_filter($possible_paths)));
    die("
    <div style='font-family:sans-serif;padding:30px;max-width:800px;margin:auto;background:#fef2f2;border:2px solid #fecaca;border-radius:12px;'>
        <h2 style='color:#dc2626;margin-top:0;'>❌ File not found on server</h2>
        <p><strong>Property:</strong> #" . htmlspecialchars($id) . " – " . htmlspecialchars($row['title']) . "</p>
        <p><strong>Stored path in DB:</strong><br><code style='background:#fff;padding:6px 10px;border-radius:6px;display:inline-block;'>" . htmlspecialchars($doc) . "</code></p>
        <p><strong>Paths tried:</strong></p>
        <ul style='background:#fff;padding:12px 24px;border-radius:8px;'>
            " . implode('', array_map(function($p) { return '<li><code>' . htmlspecialchars($p) . '</code></li>'; }, array_filter($possible_paths))) . "
        </ul>
        <p style='background:#fffbeb;border-left:4px solid #f59e0b;padding:12px;border-radius:8px;'>
            <strong>💡 Fix:</strong> Document file server पर मौजूद नहीं है। Property edit करके दोबारा upload करें।
        </p>
        <a href='properties.php?edit=" . $id . "' style='display:inline-block;margin-top:10px;padding:10px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:20px;font-weight:600;'>Edit Property</a>
        <a href='properties.php' style='display:inline-block;margin-top:10px;padding:10px 24px;background:#64748b;color:#fff;text-decoration:none;border-radius:20px;font-weight:600;margin-left:10px;'>Back</a>
    </div>
    ");
}

// ============================================================
// 🔥 STREAM FILE
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

// Clear any previous output
if (ob_get_level()) ob_end_clean();

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($found_path));
header('Content-Disposition: inline; filename="' . basename($found_path) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($found_path);
exit;
