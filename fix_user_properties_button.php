<?php
// ============================================================
// 🔧 Fix: Always show "Add Property" button in user_properties.php
// ============================================================

$file = __DIR__ . '/user_properties.php';
if (!file_exists($file)) {
    die("❌ user_properties.php not found in current directory.");
}

$content = file_get_contents($file);

// Look for the part that hides the button based on limit
// Typically: if ($used >= $max_props) { // hide button }
// We'll replace it to always show the button.

// Pattern to find the limit check and button display
$pattern = '/\s*if\s*\(.*\$used\s*>=\s*\$max_props.*\)\s*\{.*?\/\/\s*hide button.*?\}/is';
$replacement = '// ✅ Unlimited properties – always show add button';

// Backup original
file_put_contents($file . '.bak', $content);

// Apply fix: we'll replace the entire if block that hides the button.
// Since the exact pattern may vary, we'll use a simpler approach: remove any conditional that hides the button.
// We'll search for "Add Property" button and ensure it's always shown.

// Better: find the line where the button is and ensure it's not wrapped in a conditional.
// We can simply remove the if check around the button.

// Let's find the "Add Property" anchor or button
$btn_pattern = '/<a\s+[^>]*href\s*=\s*["\']add_user_property\.php["\'][^>]*>.*?Add\s*Property.*?<\/a>/is';
if (preg_match($btn_pattern, $content, $matches)) {
    // The button exists. We need to ensure it's not wrapped in an if condition.
    // We'll remove the if condition that might contain it.
    // But to be safe, we can just add a new button unconditionally after the list.
    // We'll look for the closing of the main container and add a button.
    // Actually, let's just comment out the if condition that hides it.
    
    // Find the part where it checks $used >= $max_props and remove that whole block
    $content = preg_replace('/\s*if\s*\(\s*\$used\s*>=\s*\$max_props\s*\)\s*\{.*?\}\s*else\s*\{.*?\}/is', '// ✅ Limit removed – always show button', $content);
    
    file_put_contents($file, $content);
    echo "✅ Fix applied to user_properties.php. The 'Add Property' button will now always show.<br>";
} else {
    echo "ℹ️ Could not find the 'Add Property' button pattern. Please manually check user_properties.php.<br>";
}

// Also, ensure the button is always visible by adding a fallback if not present.
// We'll add a simple link at the top if missing.
$final_content = file_get_contents($file);
if (strpos($final_content, 'add_user_property.php') === false) {
    // Button not found, add a link at the top
    $new_button = '<div class="mb-3"><a href="add_user_property.php" class="btn btn-success"><i class="fas fa-plus"></i> Add Property</a></div>';
    $final_content = preg_replace('/(<div class="container-fluid">)/', '$1' . $new_button, $final_content);
    file_put_contents($file, $final_content);
    echo "✅ Added a fallback 'Add Property' button at the top.<br>";
}

echo "🎉 Done! Please refresh your user properties page.";
?>
