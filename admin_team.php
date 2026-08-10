<?php
// ============================================================
// 👥 View User Team (Downline) – Admin Only
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    // If no user selected, show a dropdown to select a user
    $all_users = $pdo->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();
    include 'header.php';
    ?>
    <div class="container-fluid">
        <div class="card-premium">
            <h4><i class="fas fa-users me-2"></i>View Team</h4>
            <p class="text-muted">Select a user to view their entire downline team.</p>
            <form method="GET">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Select User</label>
                        <select name="id" class="form-control" required>
                            <option value="">— Choose a user —</option>
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">View Team</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php include 'footer.php'; exit;
}

// ---- Fetch user info ----
$stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    header("Location: admin_team.php?error=user_not_found");
    exit;
}

// ---- Fetch entire team tree (downline) ----
$tree_sql = "
    WITH RECURSIVE team_tree AS (
        SELECT id, name, email, referred_by, 0 as level
        FROM users
        WHERE id = ?
        UNION ALL
        SELECT u.id, u.name, u.email, u.referred_by, t.level + 1
        FROM users u
        INNER JOIN team_tree t ON u.referred_by = t.id
    )
    SELECT * FROM team_tree ORDER BY level, name
";
$stmt = $pdo->prepare($tree_sql);
$stmt->execute([$user_id]);
$team = $stmt->fetchAll();

// ---- Count total members (excluding the root user) ----
$total_members = count($team) - 1;

include 'header.php';
?>

<style>
    .team-tree { padding-left: 0; list-style: none; }
    .team-tree li { padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
    .team-tree .level-0 { font-weight: 700; font-size: 1.1rem; background: #eef2ff; padding: 8px 12px; border-radius: 8px; }
    .team-tree .level-1 { padding-left: 30px; }
    .team-tree .level-2 { padding-left: 60px; }
    .team-tree .level-3 { padding-left: 90px; }
    .team-tree .level-4 { padding-left: 120px; }
    .team-tree .level-5 { padding-left: 150px; }
    .level-badge { background: #64748b; color: #fff; padding: 2px 8px; border-radius: 30px; font-size: 0.7rem; margin-left: 10px; }
    .member-badge { background: #2563eb; color: #fff; padding: 2px 12px; border-radius: 30px; font-size: 0.8rem; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="fas fa-sitemap me-2"></i>Team of: <?= htmlspecialchars($user['name']) ?></h4>
        <div>
            <span class="badge bg-primary">Total Members: <?= $total_members ?></span>
            <a href="admin_team.php" class="btn btn-secondary btn-sm ms-2"><i class="fas fa-arrow-left"></i> Select Another</a>
            <a href="users.php" class="btn btn-secondary btn-sm"><i class="fas fa-users"></i> Back to Users</a>
        </div>
    </div>

    <?php if ($total_members == 0): ?>
        <div class="alert alert-info">This user has no downline members yet.</div>
    <?php else: ?>
        <div class="card-premium">
            <ul class="team-tree">
                <?php foreach ($team as $member): ?>
                    <li class="level-<?= $member['level'] ?>">
                        <?php if ($member['level'] == 0): ?>
                            <i class="fas fa-user-circle" style="color: #1e3a8a;"></i>
                        <?php else: ?>
                            <i class="fas fa-user" style="color: #64748b;"></i>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($member['name']) ?></strong>
                        <span class="text-muted">(<?= htmlspecialchars($member['email']) ?>)</span>
                        <?php if ($member['level'] == 0): ?>
                            <span class="badge bg-primary">Root</span>
                        <?php else: ?>
                            <span class="level-badge">Level <?= $member['level'] ?></span>
                        <?php endif; ?>
                        <?php if ($member['referred_by'] && $member['level'] > 0): ?>
                            <small class="text-muted">(referred by ID: <?= $member['referred_by'] ?>)</small>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-3">
                <span class="member-badge">Total: <?= $total_members ?> members</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>
