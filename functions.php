<?php
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
?>
