<?php
require_once __DIR__ . '/db.php';

// 1. Meta Webhook Verification (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verify_token = "prime_property_secret_123"; // Meta me yahi daalna hai
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === $verify_token) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo "Invalid Token";
        exit;
    }
}

// 2. Receiving Messages (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['entry'][0]['changes'][0])) { exit; }
    
    $change = $data['entry'][0]['changes'][0];
    $field = $change['field'];
    $value = $change['value'];

    // 📥 A. Naya Incoming Message (Customer se CRM ko)
    if ($field === 'messages' && isset($value['messages'][0])) {
        $msg = $value['messages'][0];
        $sender_phone = preg_replace('/[^0-9]/', '', $msg['from']);
        $message_text = $msg['text']['body'] ?? '';
        
        if (!empty($sender_phone) && !empty($message_text)) {
            try {
                // Check if lead exists
                $checkLead = $pdo->prepare("SELECT id FROM sales_leads WHERE phone LIKE ?");
                $checkLead->execute(['%' . $sender_phone . '%']);
                $existing_lead = $checkLead->fetch();

                if (!$existing_lead) {
                    // Round Robin Assignment
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

                    // Insert New Lead
                    $insertLead = $pdo->prepare("
                        INSERT INTO sales_leads (phone, name, status, priority, source, assigned_to, created_at) 
                        VALUES (?, 'WhatsApp Lead', 'new', 'high', 'WhatsApp', ?, CURRENT_TIMESTAMP)
                        RETURNING id
                    ");
                    $insertLead->execute([$sender_phone, $assigned_to]);
                    $new_lead_id = $insertLead->fetchColumn();

                    // Insert Note
                    $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, 1, 'note', ?)")
                        ->execute([$new_lead_id, "📥 Customer: " . $message_text]);

                    // Send Notification to Sales User
                    if ($assigned_to && $new_lead_id) {
                        $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)");
                        $notifMsg = "New Inquiry from " . $sender_phone;
                        $notifLink = "sales_lead_view.php?id=" . $new_lead_id;
                        $notifStmt->execute([$assigned_to, $notifMsg, $notifLink]);
                    }
                } else {
                    // Update existing lead with note
                    $lead_id = $existing_lead['id'];
                    $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, 1, 'note', ?)")
                        ->execute([$lead_id, "📥 Customer: " . $message_text]);
                }
            } catch (Exception $e) {
                file_put_contents('whatsapp_webhook_error.txt', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }
    }

    // 📤 B. SME Message Echo (Jab aap Mobile App se reply karte hain)
    if ($field === 'smb_message_echoes') {
        $echo = $value['message_echoes'][0] ?? null;
        if ($echo) {
            $recipient_phone = preg_replace('/[^0-9]/', '', $echo['to']);
            $message_text = $echo['text']['body'] ?? '';
            
            if (!empty($recipient_phone) && !empty($message_text)) {
                try {
                    $lead_stmt = $pdo->prepare("SELECT id FROM sales_leads WHERE phone LIKE ?");
                    $lead_stmt->execute(['%'.$recipient_phone.'%']);
                    $lead = $lead_stmt->fetch();
                    
                    if ($lead) {
                        $pdo->prepare("INSERT INTO sales_lead_notes (lead_id, user_id, note_type, note) VALUES (?, 1, 'note', ?)")
                            ->execute([$lead['id'], "📤 You (App): " . $message_text]);
                    }
                } catch (Exception $e) {
                    file_put_contents('whatsapp_webhook_error.txt', date('Y-m-d H:i:s') . " - Echo Error: " . $e->getMessage() . "\n", FILE_APPEND);
                }
            }
        }
    }

    http_response_code(200);
    echo 'EVENT_RECEIVED';
    exit;
}
?>
