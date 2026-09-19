<?php
// ============================================================
// 📄 Dynamic Page Viewer – Shows Content from DB
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$slug = $_GET['slug'] ?? 'home';

// Fetch Page
$page = safeFetch($pdo, "SELECT * FROM dynamic_pages WHERE slug = ? AND is_active = TRUE", [$slug]);

if (!$page) {
    // Agar page nahi mila to error page
    include 'header.php';
    echo "<div style='max-width: 800px; margin: 60px auto; padding: 40px; background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.08); text-align: center;'>
            <i class='fas fa-file-alt' style='font-size: 4rem; color: #94a3b8; margin-bottom: 20px;'></i>
            <h2 style='color: #0f172a; font-weight: 700;'>Page Not Found</h2>
            <p style='color: #64748b;'>This page is not available or has been removed.</p>
            <a href='index.php' class='btn btn-primary rounded-pill px-4 mt-3'>Go Home</a>
          </div>";
    include 'footer.php';
    exit;
}

include 'header.php';
?>

<div class="container" style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    <div style="background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 10px 40px rgba(0,0,0,0.06); border: 1px solid #e8edf4;">
        
        <!-- Breadcrumb -->
        <nav style="margin-bottom: 20px; font-size: 0.9rem; color: #64748b;">
            <a href="index.php" style="color: #2563eb; text-decoration: none;">Home</a>
            <span style="margin: 0 8px;">/</span>
            <span><?= htmlspecialchars($page['title']) ?></span>
        </nav>
        
        <!-- Page Title -->
        <h1 style="font-size: 2.2rem; font-weight: 800; color: #0f172a; margin-bottom: 8px;">
            <i class="fas <?= htmlspecialchars($page['icon']) ?>" style="color: #2563eb; margin-right: 10px;"></i>
            <?= htmlspecialchars($page['title']) ?>
        </h1>
        
        <?php if (!empty($page['meta_description'])): ?>
            <p style="color: #64748b; font-size: 1rem; margin-bottom: 25px;">
                <?= htmlspecialchars($page['meta_description']) ?>
            </p>
        <?php endif; ?>
        
        <hr style="border: none; border-top: 2px solid #eef2f6; margin: 25px 0;">
        
        <!-- Page Content -->
        <div style="font-size: 1rem; line-height: 1.8; color: #334155;">
            <?= $page['content'] ?? '<p style="color: #94a3b8; text-align: center; padding: 40px 0;">Content coming soon...</p>' ?>
        </div>
        
        <!-- Last Updated -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eef2f6; font-size: 0.8rem; color: #94a3b8; text-align: right;">
            <i class="fas fa-clock"></i> Last Updated: <?= date('d M Y, h:i A', strtotime($page['updated_at'])) ?>
        </div>
        
    </div>
</div>

<?php include 'footer.php'; ?>
