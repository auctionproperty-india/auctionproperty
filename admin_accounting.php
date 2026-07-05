<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') { 
    header("Location: dashboard.php"); 
    exit; 
}
if(!hasViewPermission('accounting', $pdo)) {
    die("<div class='alert alert-danger m-5'>❌ You do not have permission to view this page.</div>");
}

$message = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_entry'])) {
    if(!hasEditPermission('accounting', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to add entries.</div>");
    }
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $entry_date = $_POST['entry_date'] ?? date('Y-m-d');
    if($amount > 0 && !empty($description)) {
        if(addAccountEntry($pdo, $type, $amount, $description, $category, $entry_date)) {
            $message = "<div class='alert alert-success'>✅ Entry added successfully!</div>";
        } else { $message = "<div class='alert alert-danger'>❌ Failed to add entry.</div>"; }
    } else { $message = "<div class='alert alert-danger'>❌ Please fill all fields correctly.</div>"; }
}

if(isset($_GET['delete'])) {
    if(!hasEditPermission('accounting', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to delete entries.</div>");
    }
    $id = $_GET['delete'];
    $pdo->prepare("DELETE FROM account_entries WHERE id = ?")->execute([$id]);
    $message = "<div class='alert alert-success'>✅ Entry deleted.</div>";
}

include 'header.php'; 
$balance = getAccountBalance($pdo);
$entries = getAccountEntries($pdo, 200);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card p-3 bg-success text-white text-center rounded-4"><h5>💰 Fund In (Income)</h5><h2>₹ <?= indianCurrencyFormat($balance['income']) ?></h2></div></div>
    <div class="col-md-4"><div class="card p-3 bg-danger text-white text-center rounded-4"><h5>💸 Expense</h5><h2>₹ <?= indianCurrencyFormat($balance['expense']) ?></h2></div></div>
    <div class="col-md-4"><div class="card p-3 bg-primary text-white text-center rounded-4"><h5>💰 Available Balance</h5><h2>₹ <?= indianCurrencyFormat($balance['balance']) ?></h2></div></div>
</div>

<div class="card-premium mb-4">
    <h4><i class="fas fa-plus-circle me-2"></i>Add Account Entry</h4>
    <?= $message ?>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-2">
                <select name="type" class="form-control" required>
                    <option value="income">Fund In (Income)</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required></div>
            <div class="col-md-3">
                <select name="category" class="form-control" required>
                    <option value="Auction Subscription">Auction Subscription</option>
                    <option value="Hosting">Hosting</option>
                    <option value="Payout">Payout</option>
                    <option value="Salary">Salary</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Description / To whom" required></div>
            <div class="col-md-2"><input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <button type="submit" name="add_entry" class="btn btn-primary mt-3">Add Entry</button>
    </form>
</div>

<div class="card-premium">
    <h4><i class="fas fa-history me-2"></i>Transaction History (P&L / Expense Sheet)</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Amount</th><th>Action</th></tr></thead>
            <tbody>
            <?php if(count($entries)>0) {
                foreach($entries as $e) {
                    $type_label = ($e['type'] == 'income') ? 'Fund In' : 'Expense';
                    $badge = ($e['type'] == 'income') ? 'success' : 'danger';
                    echo "<tr>
                        <td>".date('d M Y', strtotime($e['entry_date']))."</td>
                        <td><span class='badge bg-$badge'>$type_label</span></td>
                        <td>".htmlspecialchars($e['category'])."</td>
                        <td>".htmlspecialchars($e['description'])."</td>
                        <td>₹".indianCurrencyFormat($e['amount'])."</td>
                        <td><a href='?delete=".$e['id']."' onclick='return confirm(\"Delete?\")' class='btn btn-sm btn-danger'>Del</a></td>
                    </tr>";
                }
            } else { echo "<tr><td colspan='6' class='text-center'>No entries yet.</td></tr>"; }
            ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'footer.php'; ?>
