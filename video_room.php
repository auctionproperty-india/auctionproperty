<?php
// ============================================================
// 📹 Video Room (Audio Interaction & Screen Share)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) { die("Invalid meeting link."); }

$fake_viewers = isset($_GET['fakes']) ? (int)$_GET['fakes'] : 120; 

$stmt = $pdo->prepare("SELECT * FROM video_meetings WHERE meeting_code = ?");
$stmt->execute([$code]);
$meeting = $stmt->fetch();

if (!$meeting) { die("Meeting not found."); }

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'] ?? 'user';
$display_name = $_SESSION['name'] ?? 'User';

$user_ba_stmt = $pdo->prepare("SELECT ba_status FROM users WHERE id = ?");
$user_ba_stmt->execute([$current_user_id]);
$user_ba = $user_ba_stmt->fetchColumn() ?: 'none';

$is_admin_or_sales = in_array($current_role, ['admin', 'sales']);

// 🔥 Authorization
$can_join = false;
if ($is_admin_or_sales) {
    $can_join = true; 
} elseif ($meeting['target_audience'] == 'ba_only' && $user_ba != 'active') {
    $can_join = false; // BA अप्रूव नहीं है तो नहीं आ सकता
} elseif ($meeting['is_public']) {
    $can_join = true; 
} elseif ($meeting['user_id'] == $current_user_id) {
    $can_join = true; 
} else {
    $host_ids = explode(',', $meeting['host_ids']);
    if (in_array($current_user_id, $host_ids)) $can_join = true;
}

if (!$can_join) {
    die("You are not authorized to join this meeting (BA status required).");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Meeting - Prime Property</title>
    <script src='https://meet.jit.si/external_api.js'></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; background: #0f172a; font-family: Arial, sans-serif; }
        .layout { display: flex; height: 100vh; }
        #meet-container { flex: 1; height: 100%; }
        
        .fake-chat-sidebar { width: 300px; background: #1e293b; color: white; display: flex; flex-direction: column; border-left: 1px solid #334155; }
        .chat-header { padding: 15px; background: #0f172a; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .live-badge { background: #ef4444; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; font-size: 14px; display: flex; flex-direction: column; gap: 10px; }
        .chat-msg { background: #334155; padding: 8px 12px; border-radius: 8px; animation: slideIn 0.3s ease-out; }
        .chat-msg .name { color: #38bdf8; font-weight: bold; font-size: 12px; display: block; margin-bottom: 2px; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>

<div class="layout">
    <div id="meet-container"></div>
    <div class="fake-chat-sidebar">
        <div class="chat-header">
            <div class="fw-bold"><i class="fas fa-comments"></i> Live Chat</div>
            <div class="live-badge"><i class="fas fa-circle"></i> <span id="viewerCount"><?= $fake_viewers ?></span> Watching</div>
        </div>
        <div class="chat-messages" id="chatMessages"></div>
    </div>
</div>

<script>
    const domain = 'meet.jit.si';
    
    let jitsiOptions = {
        roomName: 'PrimePropertyMeeting_<?= htmlspecialchars($code) ?>',
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#meet-container'),
        userInfo: { displayName: '<?= htmlspecialchars($display_name) ?>' },
        configOverwrite: {
            startWithAudioMuted: true, // शुरुआत में माइक बंद रहेगा ताकि शोर न हो
            startWithVideoMuted: true, // कैमरा बंद रहेगा
            disableDeepLinking: true,
            prejoinPageEnabled: false,
        }
    };

    <?php if ($is_admin_or_sales): ?>
        // 🔥 एडमिन के पास स्क्रीन शेयर और सारे कंट्रोल होंगे
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'desktop', 'chat', 'raisehand', 'tileview', 'hangup'];
    <?php else: ?>
        // 🔥 यूजर्स के पास माइक, कैमरा, चैट और हाथ उठाने का बटन होगा
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'chat', 'raisehand', 'hangup'];
        jitsiOptions.interfaceConfigOverwrite = {
            SHOW_JITSI_WATERMARK: false,
            TOOLBAR_ALWAYS_VISIBLE: true, 
        };
    <?php endif; ?>

    const api = new JitsiMeetExternalAPI(domain, jitsiOptions);

    // Fake Chat & Viewer Count
    const fakeNames = ["Rahul S.", "Priya M.", "Amit K.", "Neha T.", "Vikram R.", "Sneha P.", "Arjun D.", "Kiran B.", "Rohit V.", "Anjali D."];
    const fakeMessages = ["Hello everyone! 👋", "Is this property still available?", "Location is really good.", "Price kya final hai?", "Can you share the brochure?", "Very interested, please share details.", "Nice property!", "What is the EMI plan?", "Documents clear hain kya?", "Brokerage charges kya hain?"];

    const chatBox = document.getElementById('chatMessages');
    const viewerCountEl = document.getElementById('viewerCount');
    let baseFakes = <?= $fake_viewers ?>; 

    function getRandom(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

    function addFakeMessage() {
        const name = fakeNames[getRandom(0, fakeNames.length - 1)];
        const msg = fakeMessages[getRandom(0, fakeMessages.length - 1)];
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-msg';
        msgDiv.innerHTML = `<span class="name">${name}</span>${msg}`;
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight; 
        if (chatBox.children.length > 20) chatBox.removeChild(chatBox.firstChild);
    }

    setInterval(addFakeMessage, getRandom(4000, 8000));
    setTimeout(addFakeMessage, 1000);

    setInterval(() => {
        let currentCount = parseInt(viewerCountEl.innerText);
        let change = getRandom(-2, 4); 
        let newCount = currentCount + change;
        if (newCount < baseFakes - 10) newCount = baseFakes - 10; 
        if (newCount > baseFakes + 20) newCount = baseFakes + 20; 
        viewerCountEl.innerText = newCount;
    }, 8000);
</script>

</body>
</html>
