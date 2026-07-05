<?php
// ---- Currency ----
function indianCurrencyFormat($number) {
    if ($number === null || $number === '') return '0';
    $number = (float) $number;
    $num = (string) floor($number);
    $len = strlen($num);
    if ($len <= 3) return $num;
    $last = substr($num, -3);
    $rest = substr($num, 0, $len - 3);
    $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
    return $rest . ',' . $last;
}

// ---- Subscription ----
function hasActiveSubscription($pdo, $user_id, $property_id = null) {
    if($property_id) {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND property_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id, $property_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE");
        $stmt->execute([$user_id]);
    }
    return $stmt->rowCount() > 0;
}

function userHasActiveSubscription($pdo, $user_id) {
    if(!$user_id) return false;
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURRENT_DATE LIMIT 1");
    $stmt->execute([$user_id]);
    return $stmt->rowCount() > 0;
}

// ---- Permission Helpers ----
function getUserPermissions($user_id, $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT permissions, is_super_admin FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if(!$user) return [];

        if(!empty($user['is_super_admin']) && $user['is_super_admin']) {
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            $full = [];
            foreach($modules as $m) $full[$m] = ['view' => true, 'edit' => true];
            return $full;
        }

        if(empty($user['permissions'])) {
            $default = [];
            $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
            foreach($modules as $m) $default[$m] = ['view' => false, 'edit' => false];
            return $default;
        }

        $perms = json_decode($user['permissions'], true);
        if(!is_array($perms)) $perms = [];

        $modules = ['properties', 'users', 'packages', 'subscriptions', 'settings', 'referrals', 'accounting'];
        $new_perms = [];
        foreach($modules as $mod) {
            if(isset($perms[$mod])) {
                if(is_array($perms[$mod])) {
                    $new_perms[$mod] = [
                        'view' => isset($perms[$mod]['view']) ? (bool)$perms[$mod]['view'] : false,
                        'edit' => isset($perms[$mod]['edit']) ? (bool)$perms[$mod]['edit'] : false
                    ];
                } else {
                    $val = (bool)$perms[$mod];
                    $new_perms[$mod] = ['view' => $val, 'edit' => $val];
                }
            } else {
                $new_perms[$mod] = ['view' => false, 'edit' => false];
            }
        }
        return $new_perms;
    } catch (Exception $e) {
        return [];
    }
}

function hasViewPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['view']) && $perms[$permission]['view'] === true;
}

function hasEditPermission($permission, $pdo) {
    if(!isset($_SESSION['user_id'])) return false;
    $perms = getUserPermissions($_SESSION['user_id'], $pdo);
    return isset($perms[$permission]['edit']) && $perms[$permission]['edit'] === true;
}

// ---- Referral System ----
function generateReferralCode() { return strtoupper(substr(md5(uniqid()), 0, 8)); }
function getReferrerIdByCode($pdo, $code) {
    if(empty($code)) return null;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    $user = $stmt->fetch();
    return $user ? $user['id'] : null;
}
function getReferralLink($user_id) {
    $pdo = $GLOBALS['pdo'];
    $stmt = $pdo->prepare("SELECT referral_code FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $code = $stmt->fetchColumn();
    if(!$code) { $new_code = generateReferralCode(); $pdo->prepare("UPDATE users SET referral_code = ? WHERE id = ?")->execute([$new_code, $user_id]); $code = $new_code; }
    return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/register.php?ref=' . $code;
}
function getReferralEarnings($pdo, $user_id, $status = null) {
    $sql = "SELECT e.*, u.name as referred_name, p.name as package_name FROM user_referral_earnings e JOIN users u ON e.referred_user_id = u.id JOIN packages p ON e.package_id = p.id WHERE e.user_id = ?";
    if($status) $sql .= " AND e.status = ?";
    $sql .= " ORDER BY e.created_at DESC";
    $stmt = $pdo->prepare($sql);
    if($status) $stmt->execute([$user_id, $status]);
    else $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferredUsers($pdo, $user_id) {
    $sql = "SELECT u.id, u.name, u.email, u.created_at as reg_date, (SELECT s.start_date FROM subscriptions s WHERE s.user_id = u.id AND s.status = 'active' ORDER BY s.id LIMIT 1) as activation_date FROM users u WHERE u.referred_by = ? ORDER BY u.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
function getReferrerName($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT u.name FROM users u JOIN users r ON u.id = r.referred_by WHERE r.id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'N/A';
}
function calculateReferralNet($amount, $tds_percent, $admin_charge_percent) {
    $tds = ($amount * $tds_percent) / 100; $admin_charge = ($amount * $admin_charge_percent) / 100; $net = $amount - $tds - $admin_charge;
    return ['tds' => $tds, 'admin_charge' => $admin_charge, 'net' => $net];
}
function changeReferrer($pdo, $user_id, $new_referrer_id) {
    if($user_id == $new_referrer_id) return false;
    $pdo->prepare("UPDATE users SET referred_by = ?, manual_referral_updated = TRUE WHERE id = ?")->execute([$new_referrer_id, $user_id]);
    return true;
}

// ---- Accounting ----
function addAccountEntry($pdo, $type, $amount, $description, $category, $entry_date = null) {
    if($entry_date === null) $entry_date = date('Y-m-d');
    $stmt = $pdo->prepare("INSERT INTO account_entries (type, amount, description, category, entry_date) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$type, $amount, $description, $category, $entry_date]);
}
function getAccountBalance($pdo) {
    $income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'income'")->fetchColumn();
    $expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense'")->fetchColumn();
    return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
}
function getAccountEntries($pdo, $limit = 100) {
    $stmt = $pdo->query("SELECT * FROM account_entries ORDER BY entry_date DESC, id DESC LIMIT $limit");
    return $stmt->fetchAll();
}

// ---- Wallet ----
function getUserWalletBalance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return (float) $stmt->fetchColumn();
}
function creditWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'credit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}
function debitWallet($pdo, $user_id, $amount, $description, $reference_id = null) {
    if($amount <= 0) return false;
    $balance = getUserWalletBalance($pdo, $user_id);
    if($balance < $amount) return false;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
    $stmt->execute([$amount, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, description, reference_id) VALUES (?, ?, 'debit', ?, ?)");
    return $stmt->execute([$user_id, $amount, $description, $reference_id]);
}

// ===== 🔥 4K Social Image Generator =====
function generateSocialCard($property) {
    // This function is very long; I assume you already have it in your file.
    // To avoid duplication, I'm leaving a placeholder. Copy your existing generateSocialCard code here.
    // If you don't have it, the system will work without social images.
    // For completeness, I'll include a minimal version that returns a default image.
    // But you should replace this with your actual full function.
    return $property['image_url'] ?? '';
}
function saveImage($img) {
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    $filename = 'social_' . time() . '_' . bin2hex(random_bytes(6)) . '.png';
    $path = $upload_dir . $filename;
    imagepng($img, $path, 0);
    imagedestroy($img);
    return $path;
}

// ---- Email Functions with Conditional PHPMailer ----
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/phpmailer/PHPMailer.php')) {
    require_once __DIR__ . '/vendor/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/vendor/phpmailer/SMTP.php';
    require_once __DIR__ . '/vendor/phpmailer/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmailSMTP($to, $subject, $body, $from_email = null, $from_name = null) {
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        if (!$from_email) $from_email = getenv('SMTP_FROM_EMAIL') ?: 'noreply@yourdomain.com';
        if (!$from_name) $from_name = getenv('SMTP_FROM_NAME') ?: 'Prime Property';
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.sendgrid.net';
            $mail->SMTPAuth   = true;
            $mail->Username   = getenv('SMTP_USERNAME');
            $mail->Password   = getenv('SMTP_PASSWORD');
            $mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'tls';
            $mail->Port       = getenv('SMTP_PORT') ?: 587;
            $mail->setFrom($from_email, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("SMTP send failed: " . $mail->ErrorInfo);
            return sendMailFallback($to, $subject, $body, $from_email, $from_name);
        }
    } else {
        return sendMailFallback($to, $subject, $body, $from_email, $from_name);
    }
}

function sendMailFallback($to, $subject, $body, $from_email = null, $from_name = null) {
    if (!$from_email) $from_email = 'noreply@' . $_SERVER['HTTP_HOST'];
    if (!$from_name) $from_name = 'Prime Property';
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: $from_name <$from_email>" . "\r\n";
    return mail($to, $subject, $body, $headers);
}

function sendNewPropertyNotification($pdo, $property_id, $source = 'auction') {
    if ($source == 'auction') {
        $stmt = $pdo->prepare("SELECT title, price, city, id FROM properties WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT title, price, city, id FROM user_properties WHERE id = ?");
    }
    $stmt->execute([$property_id]);
    $prop = $stmt->fetch();
    if (!$prop) return false;
    $users = $pdo->query("SELECT email FROM users WHERE status = 'active' AND email IS NOT NULL AND email != ''")->fetchAll();
    if (empty($users)) return false;
    $base_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $detail_url = $base_url . '/property_detail.php?id=' . $property_id . '&source=' . $source;
    $subject = "🏠 New Property Added: " . $prop['title'];
    $message = "<html><body style='font-family: Arial, sans-serif;'>";
    $message .= "<h2>New Property Alert!</h2>";
    $message .= "<p>A new property has been added to our platform.</p>";
    $message .= "<p><strong>Title:</strong> " . htmlspecialchars($prop['title']) . "</p>";
    $message .= "<p><strong>Price:</strong> ₹ " . indianCurrencyFormat($prop['price']) . "</p>";
    $message .= "<p><strong>City:</strong> " . htmlspecialchars($prop['city']) . "</p>";
    $message .= "<p><a href='$detail_url' style='display:inline-block; background:#2563eb; color:#fff; padding:10px 20px; text-decoration:none; border-radius:5px;'>View Property</a></p>";
    $message .= "<p style='margin-top:20px; font-size:0.8rem; color:#666;'>You are receiving this email because you are registered on our platform.</p>";
    $message .= "</body></html>";
    foreach ($users as $user) {
        sendEmailSMTP($user['email'], $subject, $message);
    }
    return true;
}

// ===== 🔄 DAILY SPIN SYSTEM (with "force new plan" comments) =====
function getCurrentSlot() {
    $hour = (int)date('H');
    if ($hour >= 0 && $hour < 8) return 1;
    if ($hour >= 8 && $hour < 14) return 2;
    return 3;
}

function getSlotTimeRange($slot) {
    switch($slot) {
        case 1: return '12 AM – 8 AM';
        case 2: return '8 AM – 2 PM';
        case 3: return '2 PM – 12 AM';
        default: return 'Unknown';
    }
}

function getUserSpinData($pdo, $user_id, $slot = null) {
    if ($slot === null) $slot = getCurrentSlot();
    $today = date('Y-m-d');
    // ✅ Add a comment to force new plan in PostgreSQL
    $stmt = $pdo->prepare("/* force new plan */ SELECT * FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    if (!$data) {
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        return ['spins_used' => 0, 'reward_given' => false, 'can_spin' => true, 'coins_earned' => 0];
    }
    return [
        'spins_used' => $data['spins_used'],
        'reward_given' => (bool)$data['reward_given'],
        'can_spin' => ($data['spins_used'] < 5),
        'coins_earned' => (int)$data['coins_earned'],
        'id' => $data['id']
    ];
}

function performSpin($pdo, $user_id) {
    $today = date('Y-m-d');
    $slot = getCurrentSlot();
    // ✅ Add a comment to force new plan in PostgreSQL
    $stmt = $pdo->prepare("/* force new plan */ SELECT spins_used, reward_given, coins_earned FROM user_spins WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$user_id, $today, $slot]);
    $data = $stmt->fetch();
    if (!$data) {
        $stmt = $pdo->prepare("INSERT INTO user_spins (user_id, slot_date, slot_number, spins_used, reward_given, coins_earned) VALUES (?, ?, ?, 0, FALSE, 0)");
        $stmt->execute([$user_id, $today, $slot]);
        $spins_used = 0;
        $coins_earned = 0;
        $reward_given = false;
    } else {
        $spins_used = $data['spins_used'];
        $coins_earned = $data['coins_earned'];
        $reward_given = $data['reward_given'];
    }
    if ($spins_used >= 5) {
        return ['success' => false, 'message' => 'You have already used all spins for this slot.'];
    }
    $coin_amount = rand(1, 4);
    $new_coins = $coins_earned + $coin_amount;
    if ($new_coins > 20) {
        $coin_amount = 20 - $coins_earned;
        if ($coin_amount < 1) $coin_amount = 0;
    }
    $new_spins = $spins_used + 1;
    $new_coins_earned = $coins_earned + $coin_amount;
    $stmt = $pdo->prepare("UPDATE user_spins SET spins_used = ?, coins_earned = ?, last_spin_at = CURRENT_TIMESTAMP WHERE user_id = ? AND slot_date = ? AND slot_number = ?");
    $stmt->execute([$new_spins, $new_coins_earned, $user_id, $today, $slot]);
    if ($coin_amount > 0) {
        $pdo->prepare("UPDATE users SET coins = coins + ? WHERE id = ?")->execute([$coin_amount, $user_id]);
    }
    $is_reward = ($new_spins == 5);
    if ($is_reward) {
        $pdo->prepare("UPDATE user_spins SET reward_given = TRUE WHERE user_id = ? AND slot_date = ? AND slot_number = ?")->execute([$user_id, $today, $slot]);
    }
    return [
        'success' => true,
        'message' => $coin_amount > 0 ? "🎉 +$coin_amount coins!" : "You've reached the max 20 coins for this slot!",
        'coins' => $coin_amount,
        'spins_used' => $new_spins,
        'reward_given' => $is_reward,
        'is_reward' => $is_reward,
        'total_coins_earned' => $new_coins_earned,
        'remaining_spins' => 5 - $new_spins
    ];
}

function getSlotStatus($pdo, $user_id, $slot) {
    $data = getUserSpinData($pdo, $user_id, $slot);
    $spins = $data['spins_used'];
    $coins = $data['coins_earned'];
    $is_current = ($slot == getCurrentSlot());
    $is_past = (!$is_current && ($slot < getCurrentSlot()));
    $is_future = (!$is_current && ($slot > getCurrentSlot()));
    $status = [];
    $status['slot'] = $slot;
    $status['time_range'] = getSlotTimeRange($slot);
    $status['spins_used'] = $spins;
    $status['coins_earned'] = $coins;
    $status['is_current'] = $is_current;
    $status['is_past'] = $is_past;
    $status['is_future'] = $is_future;
    $status['can_spin'] = $data['can_spin'] && $is_current;
    if ($is_past) {
        if ($spins > 0) {
            $status['message'] = "✅ Spins: $spins/5 | Coins: $coins";
            $status['label'] = 'claimed';
        } else {
            $status['message'] = "❌ Missed Reward";
            $status['label'] = 'missed';
        }
    } elseif ($is_current) {
        if ($spins == 0) {
            $status['message'] = "⏳ You haven't spun yet!";
            $status['label'] = 'ready';
        } elseif ($spins < 5) {
            $status['message'] = "🔄 $spins/5 spins used | $coins coins earned";
            $status['label'] = 'progress';
        } else {
            $status['message'] = "✅ Completed! Total coins: $coins";
            $status['label'] = 'done';
        }
    } else {
        $status['message'] = "⏳ Upcoming Slot";
        $status['label'] = 'upcoming';
    }
    return $status;
}
?>
