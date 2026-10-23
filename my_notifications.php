<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
if(!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'];

if(isset($_GET['read_all'])) {
    $pdo->prepare("UPDATE exchange_notifications SET is_read=TRUE WHERE owner_user_id=?")->execute([$user_id]);
    header("Location: my_notifications.php"); exit;
}
include 'header.php';

$stmt = $pdo->prepare("
    SELECT n.*, p.title AS prop_title, u.name AS interested_name, u.email AS interested_email
    FROM exchange_notifications n
    JOIN user_properties p ON p.id = n.property_id
    JOIN users u ON u.id = n.interested_user_id
    WHERE n.owner_user_id = ? ORDER BY n.created_at DESC
");
$stmt->execute([$user_id]);
$notifs = $stmt->fetchAll();
?>
<div class="container mt-4">
    <h4><i class="fas fa-bell"></i> Exchange Notifications</h4>
    <?php if(isset($_GET['sent'])): ?><div class="alert alert-success">✅ ओनर को नोटिफिकेशन भेज दिया गया!</div><?php endif; ?>
    <a href="?read_all=1" class="btn btn-sm btn-outline-secondary mb-3">Mark all as read</a>
    
    <?php foreach($notifs as $n): ?>
        <div class="card p-3 mb-2" style="border-left:4px solid <?= $n['is_read'] ? '#ccc' : '#8b5cf6' ?>;">
            <strong><?= htmlspecialchars($n['interested_name']) ?></strong> आपकी प्रॉपर्टी <strong>"<?= htmlspecialchars($n['prop_title']) ?>"</strong> में रुचि रखते हैं।<br>
            <small class="text-muted">📧 <?= htmlspecialchars($n['interested_email']) ?> | <?= date('d M Y', strtotime($n['created_at'])) ?></small>
        </div>
    <?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
