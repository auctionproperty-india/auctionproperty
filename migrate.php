<?php
require_once __DIR__ . '/db.php';
set_time_limit(300);
echo "<pre>";

// Local Images to Supabase
$rows = $pdo->query("SELECT id, image_url FROM user_properties WHERE image_url IS NOT NULL AND image_url != '' AND image_url NOT LIKE 'http%'")->fetchAll();
echo "Found " . count($rows) . " local images.\n";

foreach($rows as $r) {
    $local = __DIR__ . '/' . ltrim($r['image_url'], '/');
    if (file_exists($local)) {
        $ext = pathinfo($local, PATHINFO_EXTENSION);
        $res = uploadAnyFile(['tmp_name' => $local, 'name' => 'migrated.'.$ext, 'size' => filesize($local), 'error' => 0], 'user_props');
        
        if (isset($res['url'])) {
            $pdo->prepare("UPDATE user_properties SET image_url=? WHERE id=?")->execute([$res['url'], $r['id']]);
            echo "✅ Migrated ID {$r['id']}\n";
        }
    }
}
echo "DONE! Ab is file ko delete kar dein.";
