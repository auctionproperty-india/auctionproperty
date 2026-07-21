<?php
// ============================================================
// 📋 Jobs / Interview – User Side
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'admin') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ---- Get user details ----
$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ---- Handle new application submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_application'])) {
    $father_name = trim($_POST['father_name']);
    $job_location = trim($_POST['job_location']);
    $city = trim($_POST['city']);
    $mobile = trim($_POST['mobile']);
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];

    // File uploads
    $resume_path = '';
    $kyc_path = '';

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $upload_dir = 'uploads/resumes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $filename = 'resume_' . time() . '_' . $user_id . '.' . $ext;
        move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $filename);
        $resume_path = $upload_dir . $filename;
    }

    if (isset($_FILES['kyc']) && $_FILES['kyc']['error'] == 0) {
        $upload_dir = 'uploads/kyc/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['kyc']['name'], PATHINFO_EXTENSION);
        $filename = 'kyc_' . time() . '_' . $user_id . '.' . $ext;
        move_uploaded_file($_FILES['kyc']['tmp_name'], $upload_dir . $filename);
        $kyc_path = $upload_dir . $filename;
    }

    $sql = "INSERT INTO job_applications (user_id, name, father_name, job_location, city, mobile, interview_date, interview_time, resume_path, kyc_path, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user['name'], $father_name, $job_location, $city, $mobile, $interview_date, $interview_time, $resume_path, $kyc_path]);
    
    $msg = "✅ Your application has been submitted successfully. It will be reviewed by admin.";
    $msg_type = "success";
}

// ---- Get user's applications ----
$stmt = $pdo->prepare("SELECT * FROM job_applications WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$applications = $stmt->fetchAll();

include 'header.php';
?>

<style>
    .status-badge { padding: 4px 14px; border-radius: 30px; font-size: 0.75rem; font-weight: 600; }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .time-slot-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
    .time-slot-grid .slot-btn { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: white; cursor: pointer; transition: all 0.2s; text-align: center; font-size: 0.85rem; }
    .time-slot-grid .slot-btn:hover { background: #eef2ff; border-color: #2563eb; }
    .time-slot-grid .slot-btn.selected { background: #2563eb; color: white; border-color: #2563eb; }
    .application-card { background: #ffffff; border-radius: 16px; padding: 18px; border: 1px solid #e2e8f0; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    .application-card .details { display: flex; flex-wrap: wrap; gap: 12px 30px; font-size: 0.9rem; }
    .application-card .details strong { color: #0f172a; }
    .application-card .approval-msg { background: #dcfce7; padding: 12px; border-radius: 12px; color: #166534; margin-top: 10px; border-left: 4px solid #16a34a; }
    .application-card .reject-msg { background: #fee2e2; padding: 12px; border-radius: 12px; color: #991b1b; margin-top: 10px; border-left: 4px solid #dc2626; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-light"><i class="fas fa-briefcase me-2"></i>My Job Applications / Interviews</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jobModal">
            <i class="fas fa-plus"></i> Apply for Job
        </button>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($applications)): ?>
        <div class="alert alert-info">You haven't applied for any job yet. Click "Apply for Job" to schedule an interview.</div>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="application-card">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="fw-bold mb-2">#<?= $app['id'] ?> – <?= htmlspecialchars($app['job_location'] ?: 'N/A') ?></h6>
                    <span class="status-badge status-<?= $app['status'] ?>">
                        <?= ucfirst($app['status']) ?>
                    </span>
                </div>
                <div class="details">
                    <div><strong>Name:</strong> <?= htmlspecialchars($app['name']) ?></div>
                    <div><strong>Father:</strong> <?= htmlspecialchars($app['father_name'] ?: '—') ?></div>
                    <div><strong>City:</strong> <?= htmlspecialchars($app['city'] ?: '—') ?></div>
                    <div><strong>Mobile:</strong> <?= htmlspecialchars($app['mobile'] ?: '—') ?></div>
                    <div><strong>Date:</strong> <?= $app['interview_date'] ? date('d M Y', strtotime($app['interview_date'])) : '—' ?></div>
                    <div><strong>Time:</strong> <?= $app['interview_time'] ? date('h:i A', strtotime($app['interview_time'])) : '—' ?></div>
                    <div>
                        <?php if ($app['resume_path']): ?>
                            <a href="<?= htmlspecialchars($app['resume_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> Resume</a>
                        <?php endif; ?>
                        <?php if ($app['kyc_path']): ?>
                            <a href="<?= htmlspecialchars($app['kyc_path']) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-id-card"></i> KYC</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($app['status'] == 'approved'): ?>
                    <div class="approval-msg">
                        <i class="fas fa-check-circle"></i> <strong>Congratulations!</strong> You have been shortlisted. Please come for interview on <strong><?= date('d M Y', strtotime($app['interview_date'])) ?></strong> at <strong><?= date('h:i A', strtotime($app['interview_time'])) ?></strong>.
                    </div>
                <?php elseif ($app['status'] == 'rejected'): ?>
                    <div class="reject-msg">
                        <i class="fas fa-times-circle"></i> Your application has been rejected. We'll keep your details for future opportunities.
                    </div>
                <?php elseif ($app['status'] == 'pending'): ?>
                    <div class="text-muted small mt-2"><i class="fas fa-clock"></i> Your application is under review. You will be notified once approved.</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ====== JOB MODAL (User Submit) ====== -->
<div class="modal fade" id="jobModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="jobForm">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Apply for Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile No. *</label>
                            <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father Name</label>
                            <input type="text" name="father_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Location</label>
                            <input type="text" name="job_location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Interview Date *</label>
                            <input type="date" name="interview_date" id="interview_date" class="form-control" required min="<?= date('Y-m-d') ?>" onchange="generateTimeSlots(this.value)">
                            <small class="text-muted">Sundays are not available</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Select Time Slot *</label>
                            <div id="timeSlotsContainer">
                                <div class="time-slot-grid" id="timeSlotGrid">
                                    <!-- dynamically filled -->
                                </div>
                            </div>
                            <input type="hidden" name="interview_time" id="selected_time" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Resume (PDF/DOC)</label>
                            <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">KYC Document (Image/PDF)</label>
                            <input type="file" name="kyc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_application" class="btn btn-primary">Submit Application</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function generateTimeSlots(date) {
    const grid = document.getElementById('timeSlotGrid');
    grid.innerHTML = '';
    document.getElementById('selected_time').value = '';

    if (!date) return;

    const selectedDate = new Date(date);
    const dayOfWeek = selectedDate.getDay();
    if (dayOfWeek === 0) {
        grid.innerHTML = '<div class="text-danger">❌ Sundays are not available. Please select another date.</div>';
        return;
    }

    const startHour = 10;
    const endHour = 18;
    const interval = 30;

    let slots = [];
    for (let h = startHour; h < endHour; h++) {
        for (let m = 0; m < 60; m += interval) {
            let hours = h;
            let minutes = m;
            let ampm = (hours >= 12) ? 'PM' : 'AM';
            if (hours > 12) hours -= 12;
            if (hours === 0) hours = 12;
            slots.push({
                h: h,
                m: m,
                display: ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2) + ' ' + ampm,
                time: ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2)
            });
        }
    }
    slots.push({ h: 18, m: 0, display: '06:00 PM', time: '18:00' });

    slots.forEach(slot => {
        const btn = document.createElement('div');
        btn.className = 'slot-btn';
        btn.textContent = slot.display;
        btn.dataset.time = slot.time;
        btn.onclick = function() {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selected_time').value = this.dataset.time;
        };
        grid.appendChild(btn);
    });
}

// Set default date to tomorrow
document.addEventListener('DOMContentLoaded', function() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    document.getElementById('interview_date').value = dateStr;
    generateTimeSlots(dateStr);
});
</script>

<?php include 'footer.php'; ?>
