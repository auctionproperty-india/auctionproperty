<?php
// ============================================================
// 📹 Video Room (With Virtual Background, Smart Chat & Auto-Reply)
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) { die("Invalid meeting link."); }

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

$can_join = false;
if ($is_admin_or_sales) {
    $can_join = true; 
} elseif ($meeting['target_audience'] == 'ba_only' && $user_ba != 'active') {
    $can_join = false;
} elseif ($meeting['is_public']) {
    $can_join = true; 
} elseif ($meeting['user_id'] == $current_user_id) {
    $can_join = true; 
} else {
    $host_ids = explode(',', $meeting['host_ids']);
    if (in_array($current_user_id, $host_ids)) $can_join = true;
}

if (!$can_join) {
    die("You are not authorized to join this meeting.");
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
        
        .fake-chat-sidebar { width: 320px; background: #1e293b; color: white; display: flex; flex-direction: column; border-left: 1px solid #334155; }
        .chat-header { padding: 15px; background: #0f172a; border-bottom: 1px solid #334155; display: flex; justify-content: space-between; align-items: center; }
        .live-badge { background: #ef4444; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; font-size: 14px; display: flex; flex-direction: column; gap: 10px; }
        .chat-msg { background: #334155; padding: 8px 12px; border-radius: 8px; animation: slideIn 0.3s ease-out; }
        .chat-msg.reply { background: #1e293b; border-left: 3px solid #38bdf8; }
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
            <div class="live-badge"><i class="fas fa-circle"></i> <span id="viewerCount"><?= $meeting['min_watchers'] ?></span> Watching</div>
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
            startWithAudioMuted: true,
            startWithVideoMuted: true,
            disableDeepLinking: true,
            prejoinPageEnabled: false,
            // 🔥 Custom Background Enable
            disableVirtualBackground: false,
            enableVirtualBackground: true,
        }
    };

    <?php if ($is_admin_or_sales): ?>
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'desktop', 'chat', 'raisehand', 'tileview', 'select-background', 'hangup'];
    <?php else: ?>
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'chat', 'raisehand', 'select-background', 'hangup'];
    <?php endif; ?>

    const api = new JitsiMeetExternalAPI(domain, jitsiOptions);

    // 🔥 Smart Chat Logic based on Audience
    const targetAudience = '<?= $meeting['target_audience'] ?>';
    
    const names = ["Rahul S.", "Priya M.", "Amit K.", "Neha T.", "Vikram R.", "Sneha P.", "Arjun D.", "Kiran B."];
    
    // Bank Auction & Property related Q&A
    const bankAuctionQA = [
        { q: "Is this property ready for possession?", a: "Yes, the bank has confirmed physical possession." },
        { q: "What is the reserve price?", a: "The reserve price is mentioned in the property details." },
        { q: "Are the documents clear?", a: "Yes, all bank auction documents are verified and clear." },
        { q: "Can we visit the property before bidding?", a: "Yes, inspection dates are available on the portal." },
        { q: "What about the EMD amount?", a: "EMD details are updated in the property list." }
    ];

    // General / All India related Q&A
    const generalQA = [
        { q: "Is this location good for investment?", a: "Yes, the area is developing rapidly." },
        { q: "Any negotiation possible?", a: "For bank auctions, price is fixed. For customer properties, we can try." },
        { q: "Can you share the brochure?", a: "Please check the property details page for full info." },
        { q: "What is the EMI plan?", a: "We can connect you with our banking partner." }
    ];

    const activeQA = (targetAudience === 'ba_only') ? bankAuctionQA : generalQA;

    const chatBox = document.getElementById('chatMessages');
    const viewerCountEl = document.getElementById('viewerCount');
    
    let minW = <?= $meeting['min_watchers'] ?: 10 ?>;
    let maxW = <?= $meeting['max_watchers'] ?: 100 ?>;
    let currentW = minW;

    function getRandom(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

    function addMessage(name, text, isReply = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-msg ' + (isReply ? 'reply' : '');
        msgDiv.innerHTML = `<span class="name">${name}</span>${text}`;
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight; 
        if (chatBox.children.length > 30) chatBox.removeChild(chatBox.firstChild);
    }

    // 🔥 Auto Q&A Logic
    function triggerChat() {
        // 1. पहले एक रैंडम सवाल पूछें
        const randomQA = activeQA[getRandom(0, activeQA.length - 1)];
        const askerName = names[getRandom(0, names.length - 1)];
        addMessage(askerName, randomQA.q);

        // 2. कुछ सेकंड बाद उसका ऑटो-रिप्लाई दें (जैसे होस्ट या कोई और)
        setTimeout(() => {
            const replierName = names[getRandom(0, names.length - 1)]; 
            addMessage(replierName, randomQA.a, true);
        }, getRandom(3000, 6000)); // 3 से 6 सेकंड बाद रिप्लाई
    }

    // हर 10-15 सेकंड में नया सवाल-जवाब चलेगा
    setInterval(triggerChat, getRandom(10000, 15000));
    setTimeout(triggerChat, 2000);

    // 🔥 Counter Logic (Min से Max तक बढ़ेगा)
    setInterval(() => {
        let change = getRandom(1, 4); 
        currentW += change;
        if (currentW > maxW) currentW = minW; // जब मैक्स पार हो जाए, तो वापस मिनट पर आ जाए
        viewerCountEl.innerText = currentW;
    }, 5000);
</script>

</body>
</html>
