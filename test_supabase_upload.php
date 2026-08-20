<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

echo "<h1>🧪 Supabase Storage Upload Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    $file = $_FILES['test_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo "<p style='color:red;'>❌ Upload error: " . $file['error'] . "</p>";
    } else {
        echo "<p>📄 File: " . $file['name'] . " (" . round($file['size']/1024, 2) . " KB)</p>";
        
        $result = uploadToSupabase($file, 'test');
        
        if ($result) {
            echo "<p style='color:green;'>✅ Upload successful!</p>";
            echo "<p>🔗 URL: <a href='$result' target='_blank'>$result</a></p>";
            echo "<p><img src='$result' style='max-height:300px; border-radius:10px;'></p>";
        } else {
            echo "<p style='color:red;'>❌ Upload failed! Check Supabase credentials.</p>";
        }
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <p>Select a file to upload to Supabase Storage:</p>
    <input type="file" name="test_file" required>
    <br><br>
    <button type="submit">Upload Test</button>
</form>

<hr>
<p><strong>Environment Variables:</strong></p>
<pre>
SUPABASE_URL: <?= getenv('SUPABASE_URL') ?: '❌ NOT SET' ?>
SUPABASE_ANON_KEY: <?= getenv('SUPABASE_ANON_KEY') ? '✅ SET (hidden)' : '❌ NOT SET' ?>
</pre>
