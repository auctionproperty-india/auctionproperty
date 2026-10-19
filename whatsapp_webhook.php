<?php
// ============================================================
// 📥 WhatsApp Cloud API Webhook (Auto Lead + Round Robin)
// ============================================================
require_once __DIR__ . '/db.php';

// 1. Meta Webhook Verification (GET Request)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verify_token = "prime_property_secret_123"; // Meta dashboard me yahi dalna hai
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === $verify_token) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo "Invalid Token";
        exit;
    }
}

// 2. Receiving Messages (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
        $messageData = $data['entry'][0]['changes'][0]['value']['messages'][0];
        
        // Number clean karein (sirf digits rakhein)
        $sender_phone = preg_replace('/[^0-9]/', '', $messageData['from']); 
        $message_text = $messageData['text']['body'] ?? '';
        
        if (!empty($sender_phone) && !empty($message_text)) {
            try {
                // Check if lead exists
                $checkLead = $pdo->prepare("SELECT id FROM sales_leads WHERE phone LIKE ?");
                $checkLead->execute(['%' . $sender_phone . '%']);
                $existing_lead = $checkLead->fetch();

                if (!$existing_lead) {
                    // 🔥 ROUND ROBIN LOGIC START
                    $salesUsers = $pdo->query("SELECT id FROM users WHERE role = 'sales' ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
                    $assigned_to = null;
                    
                    if (count($salesUsers) > 0) {
                        $lastAssigned = $pdo->query("SELECT assigned_to FROM sales_leads WHERE assigned_to IS NOT NULL ORDER BY id DESC LIMIT 1")->fetchColumn();
                        
                        if ($lastAssigned) {
                            $currentIndex = array_search($lastAssigned, $salesUsers);
                            if ($currentIndex !== false) {
                                $nextIndex = ($currentIndex + 1) % count($salesUsers); 
                                $assigned_to = $salesUsers[$nextIndex];
                            } else {
                                $assigned_to = $salesUsers[0];
                            }
                        } else {
                            $assigned_to = $salesUsers[0];
                        }
                    }
                    // 🔥 ROUND ROBIN LOGIC END

                    // Lead Insert Karein
                    $insertLead = $pdo->prepare("
                        INSERT INTO sales_leads (phone, name, status, priority, source, assigned_to, created_at) 
                        VALUES (?, 'WhatsApp Lead', 'new', 'high', 'WhatsApp', ?, CURRENT_TIMESTAMP)
                        RETURNING id
                    ");
                    $insertLead->execute([$sender_phone, $assigned_to]);
                    $new_lead_id = $insertLead->fetchColumn();

                    // Notification Bhejein
                    if ($assigned_to && $new_lead_id) {
                        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                        $notifMsg = "New Inquiry from " . $sender_phone;
                        $notifLink = "sales_lead_view.php?id=" . $new_lead_id;
                        $notifStmt->execute([$assigned_to, $notifMsg, $notifLink]);
                    }

                } else {
                    // Lead pehle se hai, toh Notes me save karein
                    $lead_id = $existing_lead['id'];
                    $insertNote = $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, 1, 'note', ?)");
                    $insertNote->execute([$lead_id, "📥 WhatsApp: " . $message_text]);
                }
            } catch (Exception $e) {
                file_put_contents('whatsapp_webhook_error.txt', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }
    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}
?>
