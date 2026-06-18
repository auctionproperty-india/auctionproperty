<?php
// Indian Number Format (e.g., 50,00,000)
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

// Check if user has active subscription for a property
function hasActiveSubscription($pdo, $user_id, $property_id) {
    $stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE user_id = ? AND property_id = ? AND status = 'active' AND end_date >= CURDATE()");
    $stmt->execute([$user_id, $property_id]);
    return $stmt->rowCount() > 0;
}
?>
