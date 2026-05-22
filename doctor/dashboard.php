<?php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// 2. FETCH DOCTOR DATA
$stmt = $pdo->prepare("SELECT users.full_name, doctor_details.specialization FROM users JOIN doctor_details ON users.id = doctor_details.user_id WHERE users.id = ?");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

// 3. HANDLE REPORT SAVING
if (isset($_POST['save_report'])) {
    try {
        if (!empty($_POST['report_id'])) {
            $sql = "UPDATE medical_reports SET symptoms=?, diagnosis=?, vitals=?, prescription=?, remarks=? WHERE report_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks'], $_POST['report_id']]);
        } else {
            $sql = "INSERT INTO medical_reports (patient_id, doctor_id, symptoms, diagnosis, vitals, prescription, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['p_id'], $userId, $_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks']]);
        }
        header("Location: dashboard.php?msg=Success");
        exit();
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// 4. FETCH DATA
$all_patients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$stmt_reports = $pdo->prepare("SELECT medical_reports.*, patients.full_name, patients.nic, patients.emergency_contact_name, patients.emergency_phone FROM medical_reports JOIN patients ON medical_reports.patient_id = patients.id WHERE medical_reports.doctor_id = ? ORDER BY created_at DESC");
$stmt_reports->execute([$userId]);
$reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard</title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .section { display: none; }
        .section.active { display: block; }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-card { background: rgba(30, 30, 45, 1); border: 1px solid rgba(0, 255, 150, 0.3); width: 95%; max-width: 750px; padding: 35px; border-radius: 24px; color: white; overflow-y: auto; max-height: 90vh; }
        .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
        .detail-item { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        .detail-item label { color: #00ff96; font-size: 11px; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 5px; }
        .guardian-box { grid-column: span 2; background: rgba(255, 165, 0, 0.1); border: 1px solid rgba(255, 165, 0, 0.3); padding: 15px; border-radius: 12px; margin-top: 10px; }
        .report-input { width: 100%; padding: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; }
        textarea.report-input { height: 100px; resize: none; }
    </style>
</head>
<body>

<div class="container-main">
    <div class="sidebar">
        <div class="profile-section">
            <div class="avatar">👨‍⚕️</div>
            <h3>Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
            <p><?php echo htmlspecialchars($doctor['specialization']); ?></p>
        </div>
        <div class="nav-menu">
            <div class="nav-item active" id="nav-home" onclick="showSection('home')"><i class="fas fa-home"></i> Dashboard</div>
            <div class="nav-item" id="nav-patients" onclick="showSection('patients')"><i class="fas fa-user-injured"></i> Patients</div>
            <div class="nav-item" id="nav-reports" onclick="showSection('reports')"><i class="fas fa-file-medical"></i> Reports History</div>
        </div>
        <div class="logout-section"><a href="../logout.php" class="logout-btn">🛑 Logout</a></div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Doctor Portal</h1>
            <p><?php echo date('F d, Y'); ?></p>
        </div>

        <div id="home" class="section active">
            <div class="card"><h3>Welcome, Dr. <?php echo $doctor['full_name']; ?></h3><p>Manage patients and reports using the menu.</p></div>
        </div>

        <div id="patients" class="section">
            <div class="users-table-container">
                <table class="users-table">
                    <thead><tr><th>Name</th><th>NIC</th><th>Phone</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($all_patients as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($p['nic']); ?></td>
                            <td><?php echo htmlspecialchars($p['phone']); ?></td>
                            <td style="display:flex; gap:10px;">
                                <button class="btn" style="background:#00ff96; color:#1a1a2e;" onclick='openProfile(<?php echo json_encode($p); ?>)'>VIEW DETAILS</button>
                                <button class="btn" style="background:#ffa502;" onclick='openReportForm(<?php echo json_encode($p); ?>)'>ADD REPORT</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="reports" class="section">
            <div class="users-table-container">
                <table class="users-table">
                    <thead><tr><th>Patient</th><th>NIC</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['nic']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
                            <td style="display:flex; gap:10px;">
                                <button class="btn" style="background:#00ff96; color:#1a1a2e;" onclick='viewReport(<?php echo json_encode($r); ?>)'>VIEW REPORT</button>
                                <button class="btn" style="background:#3498db; color:white;" onclick='editReport(<?php echo json_encode($r); ?>)'>UPDATE</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="modal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2 id="modal_title" style="color:#00ff96; margin:0;"></h2>
            <button class="btn" onclick="closeModal()" style="background:#ff4757; border:none;">&times;</button>
        </div>

        <div id="profile_view_area" style="display:none;">
            <div class="detail-grid">
                <div class="detail-item"><label>DOB</label><span id="m_dob"></span></div>
                <div class="detail-item"><label>Gender</label><span id="m_gender"></span></div>
                <div class="detail-item"><label>Blood Group</label><span id="m_blood"></span></div>
                <div class="detail-item"><label>NIC</label><span id="m_nic"></span></div>
                <div class="detail-item" style="grid-column: span 2;"><label>Medical Allergies</label><span id="m_allergies"></span></div>
                <div id="m_guardian_box" class="guardian-box" style="display:none;">
                    <h4 style="color:#ffa502; margin:0 0 10px 0;">Guardian Details</h4>
                    <span id="m_g_name"></span> (<span id="m_g_rel"></span>)
                </div>
            </div>
        </div>

        <div id="report_view_area" style="display:none; margin-top:20px;">
            <div class="detail-grid" style="border-top: 1px dashed #00ff96; padding-top: 20px;">
                <div class="detail-item" style="grid-column: span 2;"><label>Diagnosis</label><span id="v_diag"></span></div>
                <div class="detail-item"><label>Vitals</label><span id="v_vitals"></span></div>
                <div class="detail-item"><label>Symptoms</label><span id="v_symp"></span></div>
                <div class="detail-item" style="grid-column: span 2;"><label>Prescription</label><span id="v_pres"></span></div>
                <div class="detail-item" style="grid-column: span 2;"><label>Doctor Remarks</label><span id="v_rem"></span></div>
            </div>
        </div>

        <div id="emergency_row" style="display:none; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;">
            <label style="color:#00ff96; font-size:11px; text-transform:uppercase; font-weight:600;">Emergency Contact (Next of Kin)</label>
            <p style="margin:5px 0 0 0;"><span id="m_em_name" style="font-weight:600;"></span> <span id="m_em_phone" style="color:#aaa; margin-left:10px;"></span></p>
        </div>

        <div id="form_area" style="display:none; margin-top:20px;">
            <form method="POST">
                <input type="hidden" name="p_id" id="f_p_id">
                <input type="hidden" name="report_id" id="f_rep_id">
                <div class="detail-grid" style="margin-top:0;">
                    <input type="text" name="vitals" id="f_vitals" class="report-input" placeholder="Vitals (BP, Temp)">
                    <input type="text" name="symptoms" id="f_symp" class="report-input" placeholder="Symptoms">
                </div>
                <textarea name="diagnosis" id="f_diag" class="report-input" placeholder="Diagnosis"></textarea>
                <textarea name="prescription" id="f_pres" class="report-input" placeholder="Prescription"></textarea>
                <textarea name="remarks" id="f_rem" class="report-input" placeholder="Remarks"></textarea>
                <button type="submit" name="save_report" class="btn" style="width:100%; background:#00ff96; color:#1a1a2e; font-weight:bold;">SAVE REPORT</button>
            </form>
        </div>
    </div>
</div>

<script>
    function showSection(id) {
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        document.getElementById('nav-' + id).classList.add('active');
    }

    function closeModal() { document.getElementById('modal').style.display = 'none'; }

    // Helper to fill basic patient data
    function fillBasicInfo(p) {
        document.getElementById('m_dob').innerText = p.dob;
        document.getElementById('m_gender').innerText = p.gender;
        document.getElementById('m_blood').innerText = p.blood_group;
        document.getElementById('m_nic').innerText = p.nic;
        document.getElementById('m_allergies').innerText = p.allergies || "None";
        document.getElementById('m_em_name').innerText = p.emergency_contact_name || "N/A";
        document.getElementById('m_em_phone').innerText = p.emergency_phone ? "(" + p.emergency_phone + ")" : "";
        
        const gBox = document.getElementById('m_guardian_box');
        if(p.guardian_name) {
            gBox.style.display = 'block';
            document.getElementById('m_g_name').innerText = p.guardian_name;
            document.getElementById('m_g_rel').innerText = p.guardian_relation;
        } else { gBox.style.display = 'none'; }
    }

    function openProfile(p) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Patient Profile: " + p.full_name;
        document.getElementById('profile_view_area').style.display = 'block';
        document.getElementById('emergency_row').style.display = 'block';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'none';
        fillBasicInfo(p);
    }

    function openReportForm(p) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "New Report: " + p.full_name;
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        document.getElementById('f_p_id').value = p.id;
        document.getElementById('f_rep_id').value = "";
        document.querySelector('form').reset();
    }

    function viewReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Medical Record: " + r.full_name;
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'block';
        document.getElementById('report_view_area').style.display = 'block';
        document.getElementById('form_area').style.display = 'none';
        
        document.getElementById('v_diag').innerText = r.diagnosis;
        document.getElementById('v_vitals').innerText = r.vitals;
        document.getElementById('v_symp').innerText = r.symptoms;
        document.getElementById('v_pres').innerText = r.prescription;
        document.getElementById('v_rem').innerText = r.remarks;
        document.getElementById('m_em_name').innerText = r.emergency_contact_name || "N/A";
        document.getElementById('m_em_phone').innerText = r.emergency_phone ? "(" + r.emergency_phone + ")" : "";
    }

    function editReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Edit Report: " + r.full_name;
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        
        document.getElementById('f_rep_id').value = r.report_id;
        document.getElementById('f_vitals').value = r.vitals;
        document.getElementById('f_symp').value = r.symptoms;
        document.getElementById('f_diag').value = r.diagnosis;
        document.getElementById('f_pres').value = r.prescription;
        document.getElementById('f_rem').value = r.remarks;
    }
</script>
</body>
</html>