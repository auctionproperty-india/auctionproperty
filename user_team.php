<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- Fetch the entire referral tree with package info and active flag ----
$sql = "
WITH RECURSIVE team_tree AS (
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.phone,
        u.created_at,
        u.referred_by,
        0 as level,
        ARRAY[u.id] as path,
        p.name as package_name,
        s.status as sub_status,
        CASE WHEN s.status = 'active' AND s.end_date >= CURRENT_DATE THEN 1 ELSE 0 END as is_active_sub
    FROM users u 
    LEFT JOIN (
        SELECT DISTINCT ON (user_id) user_id, package_id, status, end_date
        FROM subscriptions
        WHERE status = 'active' AND end_date >= CURRENT_DATE
        ORDER BY user_id, id DESC
    ) s ON u.id = s.user_id
    LEFT JOIN packages p ON s.package_id = p.id
    WHERE u.referred_by = :user_id
    
    UNION ALL
    
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.phone,
        u.created_at,
        u.referred_by,
        t.level + 1,
        t.path || u.id,
        p.name as package_name,
        s.status as sub_status,
        CASE WHEN s.status = 'active' AND s.end_date >= CURRENT_DATE THEN 1 ELSE 0 END as is_active_sub
    FROM users u
    INNER JOIN team_tree t ON u.referred_by = t.id
    LEFT JOIN (
        SELECT DISTINCT ON (user_id) user_id, package_id, status, end_date
        FROM subscriptions
        WHERE status = 'active' AND end_date >= CURRENT_DATE
        ORDER BY user_id, id DESC
    ) s ON u.id = s.user_id
    LEFT JOIN packages p ON s.package_id = p.id
)
SELECT * FROM team_tree ORDER BY path;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$team_members = $stmt->fetchAll();

// ---- Build nested array for tree rendering (sorted by active status) ----
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
    // Sort children: active first (is_active_sub = 1), then free
    usort($branch, function($a, $b) {
        if ($a['is_active_sub'] == $b['is_active_sub']) {
            return strcmp($a['name'], $b['name']);
        }
        return $b['is_active_sub'] - $a['is_active_sub']; // active first
    });
    return $branch;
}

$tree = buildTree($team_members, $user_id);
$total_members = count($team_members);

// ---- Function to count total members in a subtree ----
function countSubtree($node) {
    $count = 1;
    if (isset($node['children']) && count($node['children']) > 0) {
        foreach ($node['children'] as $child) {
            $count += countSubtree($child);
        }
    }
    return $count;
}

// ---- Function to render tree recursively (with search filter support via JS) ----
function renderTree($nodes, $level = 0) {
    if (empty($nodes)) return '';
    $html = '<ul style="list-style:none; padding-left:' . ($level > 0 ? '25px' : '0') . ';">';
    foreach ($nodes as $node) {
        $hasChildren = isset($node['children']) && count($node['children']) > 0;
        $totalInSubtree = countSubtree($node);
        $packageBadge = '';
        
        // Package Badge
        if (!empty($node['package_name']) && $node['sub_status'] == 'active') {
            $pkgColors = [
                'Silver' => 'bg-secondary',
                'Gold' => 'bg-warning text-dark',
                'Platinum' => 'bg-primary',
                'Diamond' => 'bg-info'
            ];
            $colorClass = $pkgColors[$node['package_name']] ?? 'bg-success';
            $packageBadge = ' <span class="badge ' . $colorClass . '">' . htmlspecialchars($node['package_name']) . '</span>';
        } else {
            $packageBadge = ' <span class="badge bg-secondary">Free</span>';
        }
        
        // Active green tick
        $activeIcon = $node['is_active_sub'] ? ' <i class="fas fa-check-circle" style="color:#10b981;" title="Active Subscriber"></i>' : '';
        
        $icon = $hasChildren ? '📂' : '👤';
        
        // Add data attributes for search filtering
        $searchData = 'data-name="' . strtolower(htmlspecialchars($node['name'])) . '" data-email="' . strtolower(htmlspecialchars($node['email'])) . '"';
        $html .= '<li style="margin-bottom:8px; border-left:2px solid #e2e8f0; padding-left:12px;" ' . $searchData . '>';
        $html .= '<div style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:'.($level==0?'#f1f5f9':'transparent').'; border-radius:8px; flex-wrap:wrap;">';
        $html .= '<span style="font-size:1.2rem;">' . $icon . '</span>';
        $html .= '<strong>' . htmlspecialchars($node['name']) . $activeIcon . '</strong>';
        $html .= '<span style="font-size:0.8rem; color:#64748b;">(' . htmlspecialchars($node['email']) . ')</span>';
        $html .= $packageBadge;
        
        // Show total members in this subtree (only for direct referrals)
        if ($level == 0 && $totalInSubtree > 0) {
            $html .= ' <span class="badge bg-light text-dark border" style="font-weight:600;">👥 ' . ($totalInSubtree - 1) . ' members</span>';
        }
        
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
        flex-wrap: wrap;
        gap: 10px;
    }
    .team-header h4 {
        font-weight: 700;
        margin: 0;
    }
    .team-search {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .team-search input {
        border-radius: 30px;
        padding: 6px 16px;
        border: 1px solid #e2e8f0;
        font-size: 0.9rem;
        min-width: 200px;
    }
    .team-search input:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    .team-tree {
        padding: 10px 0;
    }
    .team-tree ul {
        margin: 0;
    }
    .team-tree li {
        transition: all 0.2s;
        cursor: default;
    }
    .team-tree li:hover > div {
        background: #f8fafc;
    }
    .badge-secondary { background-color: #6c757d; color: white; }
    .badge-warning { background-color: #ffc107; color: #212529; }
    .badge-primary { background-color: #0d6efd; color: white; }
    .badge-info { background-color: #0dcaf0; color: #212529; }
    .badge-success { background-color: #198754; color: white; }
    .team-tree li.hidden-item {
        display: none !important;
    }
</style>

<div class="container-fluid">
    <div class="team-container">
        <div class="team-header">
            <h4><i class="fas fa-users me-2"></i>My Team</h4>
            <div class="team-search">
                <input type="text" id="teamSearch" placeholder="🔍 Search by name or email..." onkeyup="filterTeam(this.value)">
                <span class="badge bg-primary rounded-pill"><?= $total_members ?> Members</span>
            </div>
        </div>

        <?php if($total_members > 0): ?>
            <div class="team-tree" id="teamTree">
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

<script>
function filterTeam(searchText) {
    const query = searchText.toLowerCase().trim();
    const items = document.querySelectorAll('#teamTree li');
    if (!query) {
        items.forEach(el => el.classList.remove('hidden-item'));
        return;
    }
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const email = el.getAttribute('data-email') || '';
        if (name.includes(query) || email.includes(query)) {
            el.classList.remove('hidden-item');
        } else {
            el.classList.add('hidden-item');
        }
    });
}
</script>

<?php include 'footer.php'; ?>
