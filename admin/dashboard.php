<?php
session_start();
// Include the database connection from the config folder
require_once '../config/db.php';

// SECURITY CHECK: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// --- STAFF LOGIC (Pagination) ---
$users_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $users_per_page;

// Get active tab from URL or default to 'dashboard'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

$total_staff_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_staff_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $users_per_page);

$staff_query = "SELECT id, full_name, email, role FROM users LIMIT " . (int)$users_per_page . " OFFSET " . (int)$offset;
$users = $pdo->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);


// --- PATIENT LOGIC (Pagination) ---
$patients_per_page = 10;
$patient_page = isset($_GET['patient_page']) ? (int)$_GET['patient_page'] : 1;
$patient_offset = ($patient_page - 1) * $patients_per_page;

$p_total_stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$total_patients = $p_total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_patient_pages = ceil($total_patients / $patients_per_page);

$patients_query = "SELECT id, full_name, nic, phone, created_at FROM patients ORDER BY created_at DESC LIMIT " . (int)$patients_per_page . " OFFSET " . (int)$patient_offset;
$recent_patients = $pdo->query($patients_query)->fetchAll(PDO::FETCH_ASSOC);

// --- STATISTICS COUNTS ---
$doctor_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'Doctor'");
$total_doctors = $doctor_count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$nurse_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'Nurse'");
$total_nurses = $nurse_count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;


// --- REPORTS LOGIC (New) ---
// Fetching all medical reports with Patient and Doctor names
$report_query = "SELECT r.*, p.full_name as patient_name, u.full_name as doctor_name 
                 FROM medical_reports r
                 JOIN patients p ON r.patient_id = p.id
                 JOIN users u ON r.doctor_id = u.id
                 ORDER BY r.created_at DESC";
$all_reports = $pdo->query($report_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Style for the View Details button in reports */
        .btn-view { background: #00ff96 !important; color: #1a1a2e !important; padding: 5px 12px; border-radius: 5px; text-decoration: none; font-size: 12px; font-weight: bold; }
        
        /* Modal Style for Viewing Report Details */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-card { background: rgba(30, 30, 45, 1); border: 1px solid rgba(0, 255, 150, 0.3); width: 95%; max-width: 600px; padding: 30px; border-radius: 20px; color: white; box-shadow: 0 0 40px rgba(0,0,0,0.5); }
        .detail-item { margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px; }
        .detail-item label { color: #00ff96; font-size: 11px; text-transform: uppercase; display: block; }

        /* Pagination Style */
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 20px; }
        .pagination a { padding: 8px 15px; background: rgba(255,255,255,0.1); color: white; text-decoration: none; border-radius: 5px; border: 1px solid rgba(0,255,150,0.3); }
        .pagination a.active { background: #00ff96; color: #1a1a2e; font-weight: bold; }
    </style>
</head>
<body>

<div class="container-main">
    <div class="sidebar">
        <div class="profile-section">
            <div class="avatar">🧑‍💼</div>
            <h3><?php echo htmlspecialchars($_SESSION['username'] ?? 'Pawan'); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></p>
        </div>

        <div class="nav-menu">
            <div class="nav-item active" id="btn-dashboard" onclick="showSection('dashboard')">
                <i class="fas fa-home"></i> Admin Dashboard
            </div>
            <div class="nav-item" id="btn-staff" onclick="showSection('staff')">
                <i class="fas fa-users-cog"></i> Staff Management
            </div>
            <div class="nav-item" id="btn-patients" onclick="showSection('patients')">
                <i class="fas fa-hospital-user"></i> Patient Records
            </div>
            <div class="nav-item" id="btn-reports" onclick="showSection('reports')">
                <i class="fas fa-chart-pie"></i> Reports
            </div>
        </div>

        <div class="logout-section">
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Admin Dashboard</h1>
            <p>Current Date: <?php echo date('F d, Y'); ?></p>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboard" class="section active">
            <div class="dashboard-welcome">
                <h2>Welcome to Admin Dashboard</h2>
                <p>Here's an overview of your hospital's current statistics.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon doctor-icon">👨‍⚕️</div>
                    <div class="stat-info">
                        <h3><?php echo $total_doctors; ?></h3>
                        <p>Doctors</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon nurse-icon">👩‍⚕️</div>
                    <div class="stat-info">
                        <h3><?php echo $total_nurses; ?></h3>
                        <p>Nurses</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon patient-icon">🏥</div>
                    <div class="stat-info">
                        <h3><?php echo $total_patients; ?></h3>
                        <p>Patients</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="staff" class="section">
            <div class="card">
                <h3>📋 Staff Management</h3>
                <p>Register new Doctors and Nurses. You currently have <strong><?php echo $total_users; ?></strong> staff members.</p>
                <a href="add_staff.php" class="btn">+ Add New Staff</a>
            </div>

            <div class="users-table-container">
                <div class="table-header">
                    <h3>👥 Registered Staff Members</h3>
                    <div class="user-count">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></div>
                </div>
                <table class="users-table">
                    <thead><tr><th>ID</th><th>Full Name</th><th>Email</th><th>Role</th></tr></thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>#<?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge <?php echo strtolower($user['role']); ?>"><?php echo $user['role']; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&tab=staff" class="<?php echo ($current_page == $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="patients" class="section">
            <div class="card">
                <h3>🏥 Patient Management</h3>
                <p>You have <strong><?php echo $total_patients; ?></strong> registered patients in the system.</p>
                <a href="add_patient.php" class="btn">+ Add New Patient</a>
            </div>
            <div class="users-table-container">
                <div class="table-header">
                    <h3>📑 Patient Admissions</h3>
                    <div class="user-count">Page <?php echo $patient_page; ?> of <?php echo $total_patient_pages; ?></div>
                </div>
                <table class="users-table">
                    <thead><tr><th>ID</th><th>Name</th><th>NIC</th><th>Phone</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent_patients as $p): ?>
                        <tr>
                            <td>#<?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['nic']); ?></td>
                            <td><?php echo htmlspecialchars($p['phone']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_patient_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_patient_pages; $i++): ?>
                        <a href="?patient_page=<?php echo $i; ?>&tab=patients" class="<?php echo ($patient_page == $i) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="reports" class="section">
            <div class="card">
                <h3>📊 Hospital Medical Reports</h3>
                <p>Overview of all clinical reports issued by Doctors.</p>
            </div>

            <div class="users-table-container">
                <div class="table-header"><h3>📑 Clinical Report History</h3></div>
                <?php if (count($all_reports) > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Diagnosis</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['patient_name']); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($report['doctor_name']); ?></td>
                            <td><?php echo htmlspecialchars($report['diagnosis']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                            <td>
                                <button class="btn-view" onclick='viewReport(<?php echo json_encode($report); ?>)'>VIEW</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-users"><p>📭 No medical reports found.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div id="reportModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 style="color:#00ff96; margin:0;">Medical Report</h2>
            <button class="btn" onclick="closeModal()" style="background:#ff4757; border:none; padding: 5px 10px;">&times;</button>
        </div>
        <div style="margin-top:20px;">
            <div class="detail-item"><label>Patient</label><span id="r_patient"></span></div>
            <div class="detail-item"><label>Doctor</label><span id="r_doctor"></span></div>
            <div class="detail-item"><label>Vitals</label><span id="r_vitals"></span></div>
            <div class="detail-item"><label>Symptoms</label><span id="r_symptoms"></span></div>
            <div class="detail-item"><label>Diagnosis</label><span id="r_diagnosis"></span></div>
            <div class="detail-item"><label>Prescription</label><span id="r_prescription"></span></div>
            <div class="detail-item"><label>Remarks</label><span id="r_remarks"></span></div>
        </div>
    </div>
</div>

<script>
    // Set active tab from URL parameter on page load
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'dashboard';
        showSection(activeTab);
    });

    function showSection(sectionId) {
        const sections = document.querySelectorAll('.section');
        sections.forEach(section => section.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');

        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        document.getElementById('btn-' + sectionId).classList.add('active');
    }

    function viewReport(report) {
        document.getElementById('reportModal').style.display = 'flex';
        document.getElementById('r_patient').innerText = report.patient_name;
        document.getElementById('r_doctor').innerText = "Dr. " + report.doctor_name;
        document.getElementById('r_vitals').innerText = report.vitals;
        document.getElementById('r_symptoms').innerText = report.symptoms;
        document.getElementById('r_diagnosis').innerText = report.diagnosis;
        document.getElementById('r_prescription').innerText = report.prescription;
        document.getElementById('r_remarks').innerText = report.remarks;
    }

    function closeModal() {
        document.getElementById('reportModal').style.display = 'none';
    }
</script>

</body>
</html>