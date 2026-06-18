<?php require_once 'db.php'; include 'header.php'; 
$total_props = $pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<div class="row">
    <div class="col-md-4"><div class="card p-4 shadow-sm"><h5>🏢 Properties</h5><h2><?= $total_props ?></h2></div></div>
    <div class="col-md-4"><div class="card p-4 shadow-sm"><h5>👥 Users</h5><h2><?= $total_users ?></h2></div></div>
</div>
<?php include 'footer.php'; ?>
