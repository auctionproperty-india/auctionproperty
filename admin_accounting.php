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

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_entry'])) {
    if(!hasEditPermission('accounting', $pdo)) {
        die("<div class='alert alert-danger m-5'>❌ You do not have permission to edit entries.</div>");
    }
    $id = (int)$_POST['entry_id'];
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $entry_date = $_POST['entry_date'];
    if($amount > 0 && !empty($description)) {
        $stmt = $pdo->prepare("UPDATE account_entries SET type = ?, amount = ?, description = ?, category = ?, entry_date = ? WHERE id = ?");
        $stmt->execute([$type, $amount, $description, $category, $entry_date, $id]);
        $message = "<div class='alert alert-success'>✅ Entry updated successfully!</div>";
    } else { $message = "<div class='alert alert-danger'>❌ Please fill all fields correctly.</div>"; }
}

include 'header.php';

// ---- Custom Totals ----
$income = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'income'")->fetchColumn();

// Other expenses (excluding referral payout)
$other_expense = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense' AND category != 'Referral Payout'")->fetchColumn();

// Referral payout expenses
$referral_payout = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM account_entries WHERE type = 'expense' AND category = 'Referral Payout'")->fetchColumn();

$total_expense = $other_expense + $referral_payout;
$balance = $income - $total_expense;

$entries = getAccountEntries($pdo, 200);
?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card p-3 bg-success text-white text-center rounded-4">
            <h5>💰 Fund In (Income)</h5>
            <h2>₹ <?= indianCurrencyFormat($income) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 bg-danger text-white text-center rounded-4">
            <h5>💸 Other Expenses</h5>
            <h2>₹ <?= indianCurrencyFormat($other_expense) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 bg-warning text-dark text-center rounded-4">
            <h5>🔄 Referral Payout</h5>
            <h2>₹ <?= indianCurrencyFormat($referral_payout) ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card p-3 bg-primary text-white text-center rounded-4">
            <h5>💰 Available Balance</h5>
            <h2>₹ <?= indianCurrencyFormat($balance) ?></h2>
        </div>
    </div>
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
                    <option value="Referral Payout">Referral Payout</option>
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
            <thead><tr>
                <th>ID</th>
                <th>Date</th>
                <th>Type</th>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if(count($entries)>0) {
                foreach($entries as $e) {
                    $type_label = ($e['type'] == 'income') ? 'Fund In' : 'Expense';
                    $badge = ($e['type'] == 'income') ? 'success' : 'danger';
                    // Special badge for referral payout
                    if($e['category'] == 'Referral Payout') $badge = 'warning text-dark';
                    echo "<tr>
                        <td>{$e['id']}</td>
                        <td>".date('d M Y', strtotime($e['entry_date']))."</td>
                        <td><span class='badge bg-$badge'>$type_label</span></td>
                        <td>".htmlspecialchars($e['category'])."</td>
                        <td>".htmlspecialchars($e['description'])."</td>
                        <td>₹".indianCurrencyFormat($e['amount'])."</td>
                        <td>
                            <button class='btn btn-sm btn-primary' onclick='openEditModal(".$e['id'].")'>✏️</button>
                            <a href='?delete=".$e['id']."' onclick='return confirm(\"Delete?\")' class='btn btn-sm btn-danger'>Del</a>
                        </td>
                    </tr>";
                }
            } else { echo "<tr><td colspan='7' class='text-center'>No entries yet.</td></tr>"; }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ===== EDIT MODAL ===== -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #1e293b, #334155); color: white;">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="entry_id" id="edit_entry_id" value="">
                    <div class="mb-3">
                        <label>Type</label>
                        <select name="type" id="edit_type" class="form-control" required>
                            <option value="income">Fund In (Income)</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Amount (₹)</label>
                        <input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Category</label>
                        <select name="category" id="edit_category" class="form-control" required>
                            <option value="Auction Subscription">Auction Subscription</option>
                            <option value="Hosting">Hosting</option>
                            <option value="Payout">Payout</option>
                            <option value="Salary">Salary</option>
                            <option value="Referral Payout">Referral Payout</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <input type="text" name="description" id="edit_description" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Date</label>
                        <input type="date" name="entry_date" id="edit_entry_date" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_entry" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditModal(id) {
        fetch('get_account_entry.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('edit_entry_id').value = data.id;
                document.getElementById('edit_type').value = data.type;
                document.getElementById('edit_amount').value = data.amount;
                document.getElementById('edit_category').value = data.category;
                document.getElementById('edit_description').value = data.description;
                document.getElementById('edit_entry_date').value = data.entry_date;
                var modal = new bootstrap.Modal(document.getElementById('editModal'));
                modal.show();
            })
            .catch(error => {
                alert('Error loading entry data: ' + error);
            });
    }
</script>

<?php include 'footer.php'; ?>
