<?php
// ============================================================
// 📋 Jobs / Interview Scheduling – Admin Panel
// ============================================================

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit;
}

// ---- Handle Delete ----
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM job_applications WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_jobs.php?msg=deleted");
    exit;
}

// ---- Handle Add/Update ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)$_POST['user_id'];
    $name = trim($_POST['name']);
    $father_name = trim($_POST['father_name']);
    $job_location = trim($_POST['job_location']);
    $city = trim($_POST['city']);
    $mobile = trim($_POST['mobile']);
    $interview_date = $_POST['interview_date'];
    $interview_time = $_POST['interview_time'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : 0;

    // Handle file uploads
    $resume_path = '';
    $kyc_path = '';

    // Resume
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] == 0) {
        $upload_dir = 'uploads/resumes/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION);
        $filename = 'resume_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['resume']['tmp_name'], $upload_dir . $filename);
        $resume_path = $upload_dir . $filename;
    }

    // KYC
    if (isset($_FILES['kyc']) && $_FILES['kyc']['error'] == 0) {
        $upload_dir = 'uploads/kyc/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['kyc']['name'], PATHINFO_EXTENSION);
        $filename = 'kyc_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['kyc']['tmp_name'], $upload_dir . $filename);
        $kyc_path = $upload_dir . $filename;
    }

    if ($edit_id > 0) {
        // Update
        $sql = "UPDATE job_applications SET 
                    user_id = ?, name = ?, father_name = ?, job_location = ?, city = ?, 
                    mobile = ?, interview_date = ?, interview_time = ?,
                    resume_path = COALESCE(?, resume_path),
                    kyc_path = COALESCE(?, kyc_path),
                    updated_at = NOW()
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $father_name, $job_location, $city, $mobile, $interview_date, $interview_time, $resume_path, $kyc_path, $edit_id]);
        $msg = "updated";
    } else {
        // Insert
        $sql = "INSERT INTO job_applications (user_id, name, father_name, job_location, city, mobile, interview_date, interview_time, resume_path, kyc_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $name, $father_name, $job_location, $city, $mobile, $interview_date, $interview_time, $resume_path, $kyc_path]);
        $msg = "added";
    }
    header("Location: admin_jobs.php?msg=" . $msg);
    exit;
}

// ---- Get all jobs ----
$jobs = $pdo->query("
    SELECT j.*, u.name as user_name, u.email as user_email 
    FROM job_applications j
    LEFT JOIN users u ON j.user_id = u.id
    ORDER BY j.id DESC
")->fetchAll();

// ---- Get all users for dropdown ----
$users = $pdo->query("SELECT id, name, email, phone FROM users ORDER BY name")->fetchAll();

include 'header.php';
?>

<style>
    .job-table th { background: #f1f5f9; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.4px; color: #475569; }
    .job-table td { vertical-align: middle; }
    .modal-content { background: #fff; color: #0f172a; }
    .modal-header { border-bottom: 1px solid #e2e8f0; }
    .modal-footer { border-top: 1px solid #e2e8f0; }
    .time-slot-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; max-height: 200px; overflow-y: auto; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
    .time-slot-grid .slot-btn { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: white; cursor: pointer; transition: all 0.2s; text-align: center; font-size: 0.85rem; }
    .time-slot-grid .slot-btn:hover { background: #eef2ff; border-color: #2563eb; }
    .time-slot-grid .slot-btn.selected { background: #2563eb; color: white; border-color: #2563eb; }
    .form-control-sm { font-size: 0.9rem; }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="text-light"><i class="fas fa-briefcase me-2"></i>Job Applications / Interviews</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#jobModal">
            <i class="fas fa-plus"></i> Schedule Interview
        </button>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $_GET['msg'] == 'added' ? '✅ Interview scheduled successfully!' : ($_GET['msg'] == 'updated' ? '✅ Updated!' : '🗑️ Deleted!') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card-premium">
        <div class="table-responsive">
            <table class="table job-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Candidate</th>
                        <th>Father</th>
                        <th>Job Location</th>
                        <th>City</th>
                        <th>Mobile</th>
                        <th>Interview Date</th>
                        <th>Time</th>
                        <th>Resume</th>
                        <th>KYC</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                        <tr><td colspan="12" class="text-center text-muted py-4">No interviews scheduled yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td><?= $job['id'] ?></td>
                        <td><strong><?= htmlspecialchars($job['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($job['user_email']) ?></small></td>
                        <td><?= htmlspecialchars($job['father_name'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($job['job_location'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($job['city'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($job['mobile'] ?? '—') ?></td>
                        <td><?= $job['interview_date'] ? date('d M Y', strtotime($job['interview_date'])) : '—' ?></td>
                        <td><?= $job['interview_time'] ? date('h:i A', strtotime($job['interview_time'])) : '—' ?></td>
                        <td>
                            <?php if ($job['resume_path']): ?>
                                <a href="<?= htmlspecialchars($job['resume_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($job['kyc_path']): ?>
                                <a href="<?= htmlspecialchars($job['kyc_path']) ?>" target="_blank" class="btn btn-sm btn-outline-success"><i class="fas fa-id-card"></i></a>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-info"><?= htmlspecialchars($job['status']) ?></span></td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-btn" 
                                    data-id="<?= $job['id'] ?>"
                                    data-user_id="<?= $job['user_id'] ?>"
                                    data-name="<?= htmlspecialchars($job['name']) ?>"
                                    data-father="<?= htmlspecialchars($job['father_name']) ?>"
                                    data-location="<?= htmlspecialchars($job['job_location']) ?>"
                                    data-city="<?= htmlspecialchars($job['city']) ?>"
                                    data-mobile="<?= htmlspecialchars($job['mobile']) ?>"
                                    data-date="<?= $job['interview_date'] ?>"
                                    data-time="<?= $job['interview_time'] ?>"
                                    data-resume="<?= htmlspecialchars($job['resume_path']) ?>"
                                    data-kyc="<?= htmlspecialchars($job['kyc_path']) ?>"
                                    data-bs-toggle="modal" data-bs-target="#jobModal">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $job['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this interview?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====== JOB MODAL ====== -->
<div class="modal fade" id="jobModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" id="jobForm">
                <input type="hidden" name="edit_id" id="edit_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i><span id="modalTitle">Schedule Interview</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Select User *</label>
                            <select name="user_id" id="user_id" class="form-control" required onchange="fillUserDetails(this.value)">
                                <option value="">— Select User —</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Candidate Name *</label>
                            <input type="text" name="name" id="candidate_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Father Name</label>
                            <input type="text" name="father_name" id="father_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mobile No.</label>
                            <input type="text" name="mobile" id="mobile" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Job Location</label>
                            <input type="text" name="job_location" id="job_location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="city" class="form-control">
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
                            <small class="text-muted" id="existing_resume"></small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">KYC Document (Image/PDF)</label>
                            <input type="file" name="kyc" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                            <small class="text-muted" id="existing_kyc"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ---- Auto-fill user details ----
function fillUserDetails(userId) {
    if (!userId) return;
    const users = <?= json_encode($users) ?>;
    const user = users.find(u => u.id == userId);
    if (user) {
        document.getElementById('candidate_name').value = user.name || '';
        document.getElementById('mobile').value = user.phone || '';
    }
}

// ---- Generate time slots (10 AM – 6 PM, 30 min interval, exclude Sundays) ----
function generateTimeSlots(date) {
    const grid = document.getElementById('timeSlotGrid');
    grid.innerHTML = '';
    document.getElementById('selected_time').value = '';

    if (!date) return;

    const selectedDate = new Date(date);
    const dayOfWeek = selectedDate.getDay(); // 0 = Sunday
    if (dayOfWeek === 0) {
        grid.innerHTML = '<div class="text-danger">❌ Sundays are not available for interviews. Please select another date.</div>';
        return;
    }

    const startHour = 10;
    const endHour = 18; // 6 PM
    const interval = 30;

    let slots = [];
    for (let h = startHour; h < endHour; h++) {
        for (let m = 0; m < 60; m += interval) {
            let hours = h;
            let minutes = m;
            if (hours >= 12) {
                if (hours > 12) hours -= 12;
                slots.push({ h: h, m: m, display: ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2) + ' PM' });
            } else {
                slots.push({ h: h, m: m, display: ('0' + hours).slice(-2) + ':' + ('0' + minutes).slice(-2) + ' AM' });
            }
        }
    }
    // Also include 6:00 PM (18:00) if not already in loop (since loop stops at 18)
    slots.push({ h: 18, m: 0, display: '06:00 PM' });

    slots.forEach((slot, index) => {
        const btn = document.createElement('div');
        btn.className = 'slot-btn';
        btn.textContent = slot.display;
        btn.dataset.time = ('0' + slot.h).slice(-2) + ':' + ('0' + slot.m).slice(-2);
        btn.onclick = function() {
            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('selected_time').value = this.dataset.time;
        };
        grid.appendChild(btn);
    });
}

// ---- Edit modal fill ----
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('jobModal');
    modal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget; // button that triggered the modal
        if (button && button.classList.contains('edit-btn')) {
            // Edit mode
            document.getElementById('modalTitle').textContent = 'Edit Interview';
            document.getElementById('edit_id').value = button.dataset.id;
            document.getElementById('user_id').value = button.dataset.user_id;
            document.getElementById('candidate_name').value = button.dataset.name;
            document.getElementById('father_name').value = button.dataset.father || '';
            document.getElementById('job_location').value = button.dataset.location || '';
            document.getElementById('city').value = button.dataset.city || '';
            document.getElementById('mobile').value = button.dataset.mobile || '';
            const date = button.dataset.date;
            document.getElementById('interview_date').value = date;
            if (date) generateTimeSlots(date);
            const time = button.dataset.time;
            if (time) {
                document.getElementById('selected_time').value = time;
                // Highlight the corresponding slot
                document.querySelectorAll('.slot-btn').forEach(btn => {
                    if (btn.dataset.time === time) {
                        btn.classList.add('selected');
                    }
                });
            }
            // Show existing files
            document.getElementById('existing_resume').textContent = button.dataset.resume ? 'Current: ' + button.dataset.resume : '';
            document.getElementById('existing_kyc').textContent = button.dataset.kyc ? 'Current: ' + button.dataset.kyc : '';
            document.getElementById('saveBtn').textContent = 'Update';
        } else {
            // Add mode
            document.getElementById('modalTitle').textContent = 'Schedule Interview';
            document.getElementById('edit_id').value = 0;
            document.getElementById('jobForm').reset();
            document.getElementById('selected_time').value = '';
            document.getElementById('timeSlotGrid').innerHTML = '';
            document.getElementById('existing_resume').textContent = '';
            document.getElementById('existing_kyc').textContent = '';
            document.getElementById('saveBtn').textContent = 'Save';
            // Set default date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const dateStr = tomorrow.toISOString().split('T')[0];
            document.getElementById('interview_date').value = dateStr;
            generateTimeSlots(dateStr);
        }
    });
});
</script>

<?php include 'footer.php'; ?>
