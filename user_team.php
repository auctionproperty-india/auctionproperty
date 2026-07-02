<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- Fetch the entire referral tree using recursive CTE ----
$sql = "
WITH RECURSIVE team_tree AS (
    SELECT 
        id, 
        name, 
        email, 
        phone,
        created_at,
        referred_by,
        0 as level,
        ARRAY[id] as path
    FROM users 
    WHERE referred_by = :user_id
    
    UNION ALL
    
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.phone,
        u.created_at,
        u.referred_by,
        t.level + 1,
        t.path || u.id
    FROM users u
    INNER JOIN team_tree t ON u.referred_by = t.id
)
SELECT * FROM team_tree ORDER BY path;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$team_members = $stmt->fetchAll();

// ---- Build nested array for tree rendering ----
function buildTree($members, $parentId = null) {
    $branch = [];
    foreach ($members as $member) {
        if ($member['referred_by'] == $parentId) {
            $children = buildTree($members, $member['id']);
            if ($children) {
                $member['children'] = $children;
            }
            $branch[] = $member;
        }
    }
    return $branch;
}

$tree = buildTree($team_members, $user_id);
$total_members = count($team_members);

// ---- Function to render tree recursively ----
function renderTree($nodes, $level = 0) {
    if (empty($nodes)) return '';
    $html = '<ul style="list-style:none; padding-left:' . ($level > 0 ? '25px' : '0') . ';">';
    foreach ($nodes as $node) {
        $hasChildren = isset($node['children']) && count($node['children']) > 0;
        $icon = $hasChildren ? '📂' : '👤';
        $html .= '<li style="margin-bottom:8px; border-left:2px solid #e2e8f0; padding-left:12px;">';
        $html .= '<div style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:'.($level==0?'#f1f5f9':'transparent').'; border-radius:8px;">';
        $html .= '<span style="font-size:1.2rem;">' . $icon . '</span>';
        $html .= '<strong>' . htmlspecialchars($node['name']) . '</strong>';
        $html .= '<span style="font-size:0.8rem; color:#64748b;">(' . htmlspecialchars($node['email']) . ')</span>';
        $html .= '<span style="font-size:0.7rem; color:#94a3b8; margin-left:auto;">Joined: ' . date('d M Y', strtotime($node['created_at'])) . '</span>';
        $html .= '</div>';
        if ($hasChildren) {
            $html .= renderTree($node['children'], $level + 1);
        }
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>
<style>
    .team-container {
        background: white;
        border-radius: 24px;
        padding: 25px;
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.04);
    }
    .team-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f1f5f9;
    }
    .team-header h4 {
        font-weight: 700;
        margin: 0;
    }
    .team-tree {
        padding: 10px 0;
    }
    .team-tree ul {
        margin: 0;
    }
    .team-tree li {
        transition: all 0.2s;
    }
    .team-tree li:hover > div {
        background: #f8fafc;
    }
</style>

<div class="container-fluid">
    <div class="team-container">
        <div class="team-header">
            <h4><i class="fas fa-users me-2"></i>My Team</h4>
            <span class="badge bg-primary rounded-pill"><?= $total_members ?> Members</span>
        </div>

        <?php if($total_members > 0): ?>
            <div class="team-tree">
                <?= renderTree($tree) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-user-plus" style="font-size:2rem; opacity:0.5;"></i>
                <p class="mt-2">You haven't referred anyone yet. Share your referral link to grow your team!</p>
                <a href="user_referrals.php" class="btn btn-primary btn-sm">Go to Referrals</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
