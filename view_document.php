<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// 🔥 SIRF ADMIN YA SUB-ADMIN HI DEKH SAKTE HAIN
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'sub_admin')) {
    http_response_code(403);
    die("<h2 style='color:red; text-align:center; margin-top:50px;'>Access Denied</h2><p style='text-align:center;'>You do not have permission to view this document.</p>");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid Request");
}

// Fetch file path from database
$stmt = $pdo->prepare("SELECT admin_document FROM properties WHERE id = ?");
$stmt->execute([$id]);
$doc_path = $stmt->fetchColumn();

if (!$doc_path || !file_exists(__DIR__ . '/' . $doc_path)) {
    http_response_code(404);
    die("File not found on server.");
}

$full_path = __DIR__ . '/' . $doc_path;
$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Set appropriate headers based on file type
if ($ext == 'pdf') {
    header('Content-Type: application/pdf');
} elseif (in_array($ext, ['jpg', 'jpeg'])) {
    header('Content-Type: image/jpeg');
} elseif ($ext == 'png') {
    header('Content-Type: image/png');
} else {
    header('Content-Type: application/octet-stream');
}

// Display inline (browser mein khulega)
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output the file
readfile($full_path);
exit;
?>
