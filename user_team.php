<?php
// ============================================================
// 👥 My Team – City Dashboard + City Filter
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') { 
    header("Location: login.php"); 
    exit; 
}

$user_id = $_SESSION['user_id'];
include 'header.php'; 

// ---- Fetch the entire referral tree with city ----
$sql = "
WITH RECURSIVE team_tree AS (
    SELECT 
        u.id, 
        u.name, 
        u.email, 
        u.phone,
        u.created_at,
        u.referred_by,
        u.city,
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
        u.city,
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
    usort($branch, function($a, $b) {
        if ($a['is_active_sub'] == $b['is_active_sub']) {
            return strcmp($a['name'], $b['name']);
        }
        return $b['is_active_sub'] - $a['is_active_sub'];
    });
    return $branch;
}

$tree = buildTree($team_members, $user_id);
$total_members = count($team_members);

// ---- Compute city counts ----
$city_counts = [];
foreach ($team_members as $m) {
    $city = trim($m['city'] ?? '');
    if (!empty($city)) {
        $city_counts[$city] = ($city_counts[$city] ?? 0) + 1;
    }
}
// Sort by count descending
arsort($city_counts);

// ---- Helper functions ----
function countSubtree($node) {
    $count = 1;
    if (isset($node['children']) && count($node['children']) > 0) {
        foreach ($node['children'] as $child) {
            $count += countSubtree($child);
        }
    }
    return $count;
}

function safeDateFormat($dateStr) {
    if (empty($dateStr) || strtotime($dateStr) === false) {
        return 'Not Active';
    }
    return date('d M Y', strtotime($dateStr));
}

function renderTree($nodes, $level = 0) {
    if (empty($nodes)) return '';
    $html = '<ul style="list-style:none; padding-left:' . ($level > 0 ? '25px' : '0') . ';">';
    foreach ($nodes as $node) {
        $hasChildren = isset($node['children']) && count($node['children']) > 0;
        $totalInSubtree = countSubtree($node);
        $packageBadge = '';
        
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
        
        $activeIcon = $node['is_active_sub'] ? ' <i class="fas fa-check-circle" style="color:#10b981;" title="Active Subscriber"></i>' : '';
        $icon = $hasChildren ? '📂' : '👤';
        $cityDisplay = !empty($node['city']) ? htmlspecialchars($node['city']) : '—';
        
        $searchData = 'data-name="' . strtolower(htmlspecialchars($node['name'])) . '" 
                        data-email="' . strtolower(htmlspecialchars($node['email'])) . '" 
                        data-city="' . strtolower(htmlspecialchars($node['city'] ?? '')) . '"';
        
        $html .= '<li style="margin-bottom:8px; border-left:2px solid #e2e8f0; padding-left:12px;" ' . $searchData . '>';
        $html .= '<div style="display:flex; align-items:center; gap:8px; padding:6px 10px; background:'.($level==0?'#f1f5f9':'transparent').'; border-radius:8px; flex-wrap:wrap;">';
        $html .= '<span style="font-size:1.2rem;">' . $icon . '</span>';
        $html .= '<strong>' . htmlspecialchars($node['name']) . $activeIcon . '</strong>';
        $html .= '<span style="font-size:0.8rem; color:#64748b;">(' . htmlspecialchars($node['email']) . ')</span>';
        $html .= $packageBadge;
        if ($level == 0 && $totalInSubtree > 0) {
            $html .= ' <span class="badge bg-light text-dark border" style="font-weight:600;">👥 ' . ($totalInSubtree - 1) . ' members</span>';
        }
        $html .= ' <span class="badge bg-light text-dark" style="font-weight:400;"><i class="fas fa-map-pin"></i> ' . $cityDisplay . '</span>';
        $html .= '<span style="font-size:0.7rem; color:#94a3b8; margin-left:auto;">Joined: ' . safeDateFormat($node['created_at']) . '</span>';
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
    .team-tree li.hidden-item {
        display: none !important;
    }

    /* City Dashboard Styles */
    .city-dashboard {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 20px;
        padding: 10px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .city-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 10px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        transition: 0.2s;
        cursor: default;
    }
    .city-card:hover {
        background: #eef2ff;
        border-color: #2563eb;
    }
    .city-card .city-name {
        font-weight: 600;
        color: #0f172a;
    }
    .city-card .city-count {
        background: #2563eb;
        color: white;
        border-radius: 30px;
        padding: 2px 10px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    .city-card .view-all-btn {
        background: transparent;
        border: none;
        color: #2563eb;
        font-weight: 600;
        font-size: 0.8rem;
        cursor: pointer;
        padding: 2px 8px;
        border-radius: 20px;
        transition: 0.2s;
    }
    .city-card .view-all-btn:hover {
        background: #2563eb;
        color: white;
    }
    .clear-filter-btn {
        background: #e2e8f0;
        border: none;
        border-radius: 30px;
        padding: 4px 16px;
        font-size: 0.8rem;
        cursor: pointer;
        color: #475569;
        font-weight: 600;
        display: none;
    }
    .clear-filter-btn:hover {
        background: #cbd5e1;
    }
    .clear-filter-btn.show {
        display: inline-block;
    }
</style>

<div class="container-fluid">
    <div class="team-container">
        <div class="team-header">
            <h4><i class="fas fa-users me-2"></i>My Team</h4>
            <div class="team-search">
                <input type="text" id="teamSearch" placeholder="🔍 Search by name, email, or city..." onkeyup="filterTeam(this.value)">
                <span class="badge bg-primary rounded-pill"><?= $total_members ?> Members</span>
                <button class="clear-filter-btn" id="clearFilterBtn" onclick="clearFilter()"><i class="fas fa-times"></i> Clear Filter</button>
            </div>
        </div>

        <!-- City Dashboard -->
        <?php if (!empty($city_counts)): ?>
        <div class="city-dashboard">
            <?php foreach ($city_counts as $city => $count): ?>
                <div class="city-card">
                    <span class="city-name"><i class="fas fa-map-pin" style="color:#2563eb;"></i> <?= htmlspecialchars($city) ?></span>
                    <span class="city-count"><?= $count ?></span>
                    <button class="view-all-btn" data-city="<?= htmlspecialchars($city) ?>" onclick="filterByCity(this)">View All</button>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
    const clearBtn = document.getElementById('clearFilterBtn');
    if (!query) {
        items.forEach(el => el.classList.remove('hidden-item'));
        clearBtn.classList.remove('show');
        return;
    }
    let matched = false;
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const email = el.getAttribute('data-email') || '';
        const city = el.getAttribute('data-city') || '';
        if (name.includes(query) || email.includes(query) || city.includes(query)) {
            el.classList.remove('hidden-item');
            matched = true;
        } else {
            el.classList.add('hidden-item');
        }
    });
    if (matched) {
        clearBtn.classList.add('show');
    } else {
        clearBtn.classList.remove('show');
    }
}

function filterByCity(btn) {
    const city = btn.getAttribute('data-city');
    const searchInput = document.getElementById('teamSearch');
    searchInput.value = city;
    filterTeam(city);
}

function clearFilter() {
    const searchInput = document.getElementById('teamSearch');
    searchInput.value = '';
    filterTeam('');
}
</script>

<?php include 'footer.php'; ?>
