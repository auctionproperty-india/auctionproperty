<?php
// ============================================================
// 📹 Video Room (Custom Chat with Real Messaging & Auto-Reply)
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
        
        /* 🔥 Custom Chat Sidebar */
        .custom-chat-sidebar {
            width: 320px;
            background: #1e293b;
            color: white;
            display: flex;
            flex-direction: column;
            border-left: 1px solid #334155;
        }
        .chat-header {
            padding: 15px;
            background: #0f172a;
            border-bottom: 1px solid #334155;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .live-badge {
            background: #ef4444;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .chat-msg {
            background: #334155;
            padding: 8px 12px;
            border-radius: 8px;
            animation: slideIn 0.3s ease-out;
            position: relative;
        }
        .chat-msg .name { color: #38bdf8; font-weight: bold; font-size: 12px; display: block; margin-bottom: 2px; }
        
        /* 🔥 Real User Message */
        .chat-msg.real-user {
            background: #1e40af; /* Blueish */
            color: white;
        }
        .chat-msg.real-user .name { color: #fbbf24; }
        
        /* 🔥 Reply Message Style */
        .chat-msg.bot-reply {
            background: #1e293b;
            border-left: 3px solid #10b981;
            margin-left: 15px;
        }
        .chat-msg.bot-reply .quote {
            background: rgba(0,0,0,0.2);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #cbd5e1;
            margin-bottom: 6px;
            border-left: 2px solid #94a3b8;
        }
        
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        /* 🔥 Chat Input Area */
        .chat-input-area {
            padding: 10px;
            background: #0f172a;
            display: flex;
            gap: 10px;
            border-top: 1px solid #334155;
        }
        .chat-input-area input {
            flex: 1;
            padding: 8px 12px;
            border-radius: 20px;
            border: none;
            background: #334155;
            color: white;
            outline: none;
        }
        .chat-input-area button {
            background: #3b82f6;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body>

<div class="layout">
    <!-- Jitsi Video Area -->
    <div id="meet-container"></div>

    <!-- Custom Chat Area -->
    <div class="custom-chat-sidebar">
        <div class="chat-header">
            <div class="fw-bold"><i class="fas fa-comments"></i> Live Chat</div>
            <div class="live-badge"><i class="fas fa-circle"></i> <span id="viewerCount"><?= $meeting['min_watchers'] ?></span> Watching</div>
        </div>
        
        <div class="chat-messages" id="chatMessages"></div>
        
        <div class="chat-input-area">
            <input type="text" id="customMsgInput" placeholder="Type a message..." onkeypress="handleKeyPress(event)">
            <button onclick="sendCustomMessage()"><i class="fas fa-paper-plane"></i></button>
        </div>
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
            disableVirtualBackground: false,
            enableVirtualBackground: true,
        }
    };

    // 🔥 REMOVED 'chat' from toolbar. The custom sidebar will handle chat.
    <?php if ($is_admin_or_sales): ?>
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'desktop', 'raisehand', 'tileview', 'select-background', 'hangup'];
    <?php else: ?>
        jitsiOptions.configOverwrite.toolbarButtons = ['microphone', 'camera', 'raisehand', 'select-background', 'hangup'];
    <?php endif; ?>

    const api = new JitsiMeetExternalAPI(domain, jitsiOptions);

    // 🔥 Chat Logic Variables
    const chatBox = document.getElementById('chatMessages');
    const viewerCountEl = document.getElementById('viewerCount');
    let myName = '<?= htmlspecialchars($display_name) ?>';
    
    // For Fake Auto Chat
    const targetAudience = '<?= $meeting['target_audience'] ?>';
    const botNames = ["Rahul S.", "Priya M.", "Amit K.", "Neha T.", "Vikram R.", "Sneha P.", "Arjun D.", "Kiran B."];
    
    const bankAuctionQA = [
        { q: "Is this property ready for possession?", a: "Yes, the bank has confirmed physical possession." },
        { q: "Are the documents clear?", a: "Yes, all bank auction documents are verified and clear." },
        { q: "What about the EMD amount?", a: "EMD details are updated in the property list." }
    ];
    const generalQA = [
        { q: "Is this location good for investment?", a: "Yes, the area is developing rapidly." },
        { q: "Any negotiation possible?", a: "For bank auctions, price is fixed. For customer properties, we can try." },
        { q: "Can you share the brochure?", a: "Please check the property details page for full info." }
    ];
    const activeQA = (targetAudience === 'ba_only') ? bankAuctionQA : generalQA;

    let minW = <?= $meeting['min_watchers'] ?: 10 ?>;
    let maxW = <?= $meeting['max_watchers'] ?: 100 ?>;
    let currentW = minW;

    function getRandom(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

    // 🔥 Core Function to add any message to the UI
    function addMessageToUI(name, text, type = 'fake', quoteText = null) {
        const msgDiv = document.createElement('div');
        
        if (type === 'real') {
            msgDiv.className = 'chat-msg real-user';
        } else if (type === 'reply') {
            msgDiv.className = 'chat-msg bot-reply';
        } else {
            msgDiv.className = 'chat-msg';
        }

        let html = `<span class="name">${name}</span>`;
        if (quoteText) {
            html += `<div class="quote">${quoteText}</div>`;
        }
        html += text;
        
        msgDiv.innerHTML = html;
        chatBox.appendChild(msgDiv);
        chatBox.scrollTop = chatBox.scrollHeight; 
        
        if (chatBox.children.length > 50) {
            chatBox.removeChild(chatBox.firstChild);
        }
    }

    // 🔥 1. SEND Real Message from Custom Input to Jitsi
    function sendCustomMessage() {
        const input = document.getElementById('customMsgInput');
        const text = input.value.trim();
        
        if (text) {
            // Send to Jitsi API (this broadcasts to everyone in the meeting)
            api.executeCommand('sendChatMessage', text);
            
            // Show in our own UI immediately
            addMessageToUI(myName, text, 'real');
            
            // Clear input
            input.value = '';
            
            // Trigger a bot reply to this specific real message
            triggerBotReply(text);
        }
    }

    function handleKeyPress(e) {
        if (e.key === 'Enter') {
            sendCustomMessage();
        }
    }

    // 🔥 2. LISTEN to Real Messages from Jitsi
    api.addEventListener('incomingMessage', (event) => {
        // event has: { nick, message, private }
        // 'nick' is the sender's name. If it's our own message, ignore it (we already added it).
        if (event.nick !== myName) {
            // Add the real user's message to our custom sidebar
            addMessageToUI(event.nick, event.message, 'real');
            
            // Trigger a bot reply to what they said
            triggerBotReply(event.message);
        }
    });

    // 🔥 3. AUTO-REPLY to Real Messages
    function triggerBotReply(userMessage) {
        // Wait 3-5 seconds so it feels natural
        setTimeout(() => {
            const botName = botNames[getRandom(0, botNames.length - 1)];
            let replyText = "Yes, we are discussing that right now. Please check the details.";
            
            // Simple keyword matching for smarter replies
            const msgLower = userMessage.toLowerCase();
            if (msgLower.includes('price') || msgLower.includes('rate')) {
                replyText = "The reserve price is fixed by the bank. You can view it in the details.";
            } else if (msgLower.includes('location') || msgLower.includes('city')) {
                replyText = "The location is prime and well-connected. You can check the map link.";
            } else if (msgLower.includes('document')) {
                replyText = "Yes, all documents are legally verified by the bank.";
            }

            // Show it as a reply with a quote
            addMessageToUI(botName, replyText, 'reply', `Replying to ${myName}: "${userMessage}"`);
            
        }, getRandom(3000, 5000));
    }

    // 🔥 4. Automatic Fake Chat Generator (General Q&A)
    function triggerAutoChat() {
        const randomQA = activeQA[getRandom(0, activeQA.length - 1)];
        const askerName = botNames[getRandom(0, botNames.length - 1)];
        
        // Post the question
        addMessageToUI(askerName, randomQA.q, 'fake');

        // Auto-Reply from another bot
        setTimeout(() => {
            const replierName = botNames[getRandom(0, botNames.length - 1)];
            addMessageToUI(replierName, randomQA.a, 'reply', `Replying to ${askerName}: "${randomQA.q}"`);
        }, getRandom(3000, 6000)); 
    }

    setInterval(triggerAutoChat, getRandom(15000, 25000)); // Every 15-25 secs

    // 🔥 5. Viewer Count Logic
    setInterval(() => {
        let change = getRandom(1, 4); 
        currentW += change;
        if (currentW > maxW) currentW = minW; 
        viewerCountEl.innerText = currentW;
    }, 5000);

</script>

</body>
</html>
