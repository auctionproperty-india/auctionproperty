// ... (बाकी फाइल वैसी ही) ...

// User View के अंदर, Welcome Banner के बाद, Buy Packages से पहले यह कोड डालें:

<?php
// Referral Link & Earnings
$referral_link = getReferralLink($user_id);
$earnings = getReferralEarnings($pdo, $user_id, 'pending'); // only pending for now
$paid_earnings = getReferralEarnings($pdo, $user_id, 'paid');
$total_pending = array_sum(array_column($earnings, 'amount'));
$total_paid = array_sum(array_column($paid_earnings, 'net_amount'));
?>

<div class="card-premium mb-4" style="border: 1px solid #10b981; background: #f0fdf4;">
    <h5><i class="fas fa-link me-2" style="color: #10b981;"></i>Your Referral Link</h5>
    <div class="input-group">
        <input type="text" class="form-control border-success" id="refLink" value="<?= $referral_link ?>" readonly>
        <button class="btn btn-success" onclick="copyRef()"><i class="fas fa-copy"></i> Copy</button>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <span class="badge bg-warning text-dark">⏳ Pending: ₹ <?= indianCurrencyFormat($total_pending) ?></span>
            <span class="badge bg-success ms-2">✅ Paid: ₹ <?= indianCurrencyFormat($total_paid) ?></span>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#referralHistory">View History</a>
        </div>
    </div>
    <div class="collapse mt-3" id="referralHistory">
        <?php if(count($earnings) > 0 || count($paid_earnings) > 0): ?>
            <table class="table table-sm table-bordered">
                <thead><tr><th>Referred User</th><th>Package</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach(array_merge($earnings, $paid_earnings) as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['referred_name']) ?></td>
                        <td><?= htmlspecialchars($e['package_name']) ?></td>
                        <td>₹<?= indianCurrencyFormat($e['amount']) ?></td>
                        <td><span class="badge bg-<?= $e['status']=='paid' ? 'success' : 'warning' ?>"><?= $e['status'] ?></span></td>
                        <td><?= date('d M Y', strtotime($e['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No referrals yet. Share your link!</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function copyRef() {
        let inp = document.getElementById('refLink');
        inp.select(); navigator.clipboard.writeText(inp.value).then(() => alert('Referral Link Copied!')).catch(() => document.execCommand('copy'));
    }
</script>
