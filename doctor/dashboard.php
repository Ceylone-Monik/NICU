<?php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";

// 2. FETCH DOCTOR DATA
$stmt = $pdo->prepare("SELECT users.full_name, doctor_details.specialization FROM users JOIN doctor_details ON users.id = doctor_details.user_id WHERE users.id = ?");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

// 3. HANDLE REPORT SAVING (Mothers & Babies)
if (isset($_POST['save_report'])) {
    try {
        $p_id = !empty($_POST['p_id']) ? $_POST['p_id'] : null;
        $baby_id = !empty($_POST['baby_id']) ? $_POST['baby_id'] : null;

        if (!empty($_POST['report_id'])) {
            $sql = "UPDATE medical_reports SET symptoms=?, diagnosis=?, vitals=?, prescription=?, remarks=? WHERE report_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks'], $_POST['report_id']]);
        } else {
            $sql = "INSERT INTO medical_reports (patient_id, baby_id, doctor_id, symptoms, diagnosis, vitals, prescription, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$p_id, $baby_id, $userId, $_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks']]);
        }
        header("Location: dashboard.php?msg=Success");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// 4. HANDLE SUBMITTING WARD MOVE RECOMMENDATION
if (isset($_POST['submit_recommendation'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET recommended_ward = ?, recommendation_status = 'Pending' WHERE baby_id = ?");
        $stmt->execute([$_POST['recommended_ward'], $_POST['baby_id']]);
        $message = "Recommendation submitted to Administrator successfully!";
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// 5. FETCH DATA ROSTERS
$all_patients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch reports history with fallback naming structures for infants
$stmt_reports = $pdo->prepare("
    SELECT r.*, 
           p.full_name as mother_name, p.nic as mother_nic,
           b.baby_name
    FROM medical_reports r 
    LEFT JOIN patients p ON r.patient_id = p.id 
    LEFT JOIN babies b ON r.baby_id = b.baby_id
    WHERE r.doctor_id = ? 
    ORDER BY r.created_at DESC
");
$stmt_reports->execute([$userId]);
$reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);

$query_babies = "SELECT b.*, p.full_name as mother_name FROM babies b JOIN patients p ON b.mother_id = p.id ORDER BY b.birth_date DESC";
$all_babies = $pdo->query($query_babies)->fetchAll(PDO::FETCH_ASSOC);
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
        .report-input { width: 100%; padding: 12px; margin-bottom: 15px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; }
        textarea.report-input { height: 100px; resize: none; }

        .clickable-baby-row { cursor: pointer; transition: 0.2s; }
        .clickable-baby-row:hover { background: rgba(0, 255, 150, 0.04) !important; }
        
        .action-btn-container { display: flex; align-items: center; justify-content: flex-start; gap: 10px; padding: 4px 0; }
        .table-btn { padding: 8px 16px !important; font-size: 12px !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: 0.5px; border-radius: 8px !important; display: inline-flex !important; align-items: center; justify-content: center; gap: 6px; height: 36px !important; transition: all 0.3s ease !important; border: none; cursor: pointer; }
        .table-btn.view-btn { background: linear-gradient(135deg, #00ff96 0%, #00cc7f 100%) !important; color: #1a1a2e !important; }
        .table-btn.report-btn { background: linear-gradient(135deg, #ffa502 0%, #ff8c00 100%) !important; color: #1a1a2e !important; }
        .table-btn.update-btn { background: linear-gradient(135deg, #3498db 0%, #2980b9 100%) !important; color: #ffffff !important; }
        
        .recommendation-form-box { display: flex; align-items: center; gap: 10px; }
        .select-recommend-input { padding: 6px 10px; border-radius: 6px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px; cursor: pointer; height: 34px; }
        .recommend-btn { padding: 0 12px; background: #00ff96; color: #1a1a2e; border: none; font-weight: bold; border-radius: 6px; font-size: 11px; cursor: pointer; text-transform: uppercase; height: 34px; }
        .status-badge-pending { padding: 5px 10px; background: rgba(255, 165, 0, 0.15); color: #ffa502; border: 1px solid rgba(255, 165, 0, 0.3); border-radius: 6px; font-size: 11px; font-weight: bold; }

        .popup-split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 15px; }
        .popup-col h4 { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 12px; font-weight: 600; }
        .data-row { margin-bottom: 10px; font-size: 13px; text-align: left; }
        .data-row label { display: block; color: #aaa; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; font-weight: 600; }
        .data-row span { color: #fff; }
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
            <div class="nav-item" id="nav-babies" onclick="showSection('babies')"><i class="fas fa-baby"></i> Infants (Wards)</div>
            <div class="nav-item" id="nav-reports" onclick="showSection('reports')"><i class="fas fa-file-medical"></i> Reports History</div>
        </div>
        <div class="logout-section"><a href="../logout.php" class="logout-btn">🛑 Logout</a></div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Doctor Portal</h1>
            <p><?php echo date('F d, Y'); ?></p>
        </div>

        <?php if (!empty($message) || (isset($_GET['msg']) && $_GET['msg'] === 'Success')): ?>
            <div style="padding:15px; background:rgba(0,255,150,0.1); color:#00ff96; border-radius:10px; margin-bottom:20px; border: 1px solid #00ff96;">
                <?php echo !empty($message) ? $message : "Medical report synchronized successfully!"; ?>
            </div>
        <?php endif; ?>

        <div id="home" class="section active">
            <div class="card"><h3>Welcome, Dr. <?php echo $doctor['full_name']; ?></h3><p>Manage patients, issue clinical prescriptions, and coordinate nursery ward assignments.</p></div>
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
                            <td>
                                <div class="action-btn-container">
                                    <button class="btn table-btn view-btn" onclick='openProfile(<?php echo json_encode($p); ?>)'>
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    <button class="btn table-btn report-btn" onclick='openReportForm(<?php echo json_encode($p); ?>, "mother")'>
                                        <i class="fas fa-plus-circle"></i> Add Report
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="babies" class="section">
            <div class="users-table-container">
                <div class="table-header"><h3>👶 Active Nursery Wards Roster</h3></div>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Infant Name</th>
                            <th>Mother</th>
                            <th>Current Ward</th>
                            <th>Suggest Ward Move</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_babies as $b): ?>
                        <tr class="clickable-baby-row" onclick="openUnifiedModalFromDoctor(<?php echo $b['baby_id']; ?>)">
                            <td><strong><?php echo htmlspecialchars($b['baby_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['mother_name']); ?></td>
                            <td><span class="role-badge doctor"><?php echo htmlspecialchars($b['ward_name']); ?> Ward</span></td>
                            <td onclick="event.stopPropagation();">
                                <?php if ($b['recommendation_status'] === 'Pending'): ?>
                                    <div class="status-badge-pending">
                                        <i class="fas fa-hourglass-half"></i> Pending Admin (-> <?php echo $b['recommended_ward']; ?>)
                                    </div>
                                <?php else: ?>
                                    <form method="POST" class="recommendation-form-box">
                                        <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                        <select name="recommended_ward" class="select-recommend-input" required>
                                            <option value="" selected disabled>Select...</option>
                                            <?php 
                                            $options = ['Normal', 'Critical', 'Other', 'To Discharge'];
                                            foreach($options as $opt) {
                                                if($opt !== $b['ward_name']) { echo "<option value='$opt'>$opt</option>"; }
                                            }
                                            ?>
                                        </select>
                                        <button type="submit" name="submit_recommendation" class="recommend-btn"><i class="fas fa-paper-plane"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td onclick="event.stopPropagation();">
                                <button class="btn table-btn report-btn" style="height:30px !important; padding:4px 10px !important;" onclick='openReportForm(<?php echo json_encode($b); ?>, "baby")'>
                                    <i class="fas fa-file-medical"></i> + Report
                                </button>
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
                    <thead><tr><th>Patient Type/Target</th><th>Reference Name</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($reports as $r): ?>
                        <tr>
                            <td>
                                <span class="role-badge <?php echo !empty($r['baby_id']) ? 'nurse' : 'doctor'; ?>">
                                    <?php echo !empty($r['baby_id']) ? '👶 Infant' : '🤰 Mother'; ?>
                                </span>
                            </td>
                            <td>
                                <?php echo !empty($r['baby_id']) ? htmlspecialchars($r['baby_name']) . " (Infd.)" : htmlspecialchars($r['mother_name']); ?>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
                            <td>
                                <div class="action-btn-container">
                                    <button class="btn table-btn view-btn" onclick='viewReport(<?php echo json_encode($r); ?>)'>
                                        <i class="fas fa-file-alt"></i> View
                                    </button>
                                    <button class="btn table-btn update-btn" onclick='editReport(<?php echo json_encode($r); ?>)'>
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                </div>
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
            <button class="btn" onclick="closeModal()" style="background:#ff4757; border:none; color:white; cursor:pointer;">&times;</button>
        </div>
        <div id="profile_view_area" style="display:none;">
            <div class="detail-grid">
                <div class="detail-item"><label>DOB</label><span id="m_dob"></span></div>
                <div class="detail-item"><label>Gender</label><span id="m_gender"></span></div>
                <div class="detail-item"><label>Blood Group</label><span id="m_blood"></span></div>
                <div class="detail-item"><label>NIC</label><span id="m_nic"></span></div>
                <div class="detail-item" style="grid-column: span 2;"><label>Medical Allergies</label><span id="m_allergies"></span></div>
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
            <label style="color:#00ff96; font-size:11px; text-transform:uppercase; font-weight:600;">Emergency Contact</label>
            <p style="margin:5px 0 0 0;"><span id="m_em_name" style="font-weight:600;"></span> <span id="m_em_phone" style="color:#aaa; margin-left:10px;"></span></p>
        </div>
        <div id="form_area" style="display:none; margin-top:20px;">
            <form method="POST">
                <input type="hidden" name="p_id" id="f_p_id">
                <input type="hidden" name="baby_id" id="f_baby_id">
                <input type="hidden" name="report_id" id="f_rep_id">
                
                <div class="detail-grid" style="margin-top:0;">
                    <input type="text" name="vitals" id="f_vitals" class="report-input" placeholder="Vitals (BP, Temp, or Birth Weight)">
                    <input type="text" name="symptoms" id="f_symp" class="report-input" placeholder="Symptoms / Observations">
                </div>
                <textarea name="diagnosis" id="f_diag" class="report-input" placeholder="Diagnosis / Clinical Impression"></textarea>
                <textarea name="prescription" id="f_pres" class="report-input" placeholder="Prescription (Medications, Feed instructions)"></textarea>
                <textarea name="remarks" id="f_rem" class="report-input" placeholder="Remarks"></textarea>
                <button type="submit" name="save_report" class="btn" style="width:100%; background:#00ff96; color:#1a1a2e; font-weight:bold;">SAVE REPORT</button>
            </form>
        </div>
    </div>
</div>

<div id="unifiedBabyModal" class="modal-overlay" style="z-index: 3000;">
    <div class="modal-card" style="border-color: #ff0080;">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">
            <h2 style="color:#ff0080; margin:0;"><i class="fas fa-notes-medical"></i> Case Overview</h2>
            <button class="btn" onclick="closeUnifiedBabyModal()" style="background:#ff4757; border:none; padding:5px 12px; color:white; cursor:pointer;">&times;</button>
        </div>
        <div class="popup-split-grid">
            <div class="popup-col">
                <h4 style="color:#00ff96;"><i class="fas fa-baby"></i> Infant Parameters</h4>
                <div class="data-row"><label>Baby Name</label><span id="pop_b_name"></span></div>
                <div class="data-row"><label>Gender</label><span id="pop_b_gender"></span></div>
                <div class="data-row"><label>Birth Date / Time</label><span id="pop_b_dob"></span></div>
                <div class="data-row"><label>Weight</label><span id="pop_b_weight"></span></div>
                <div class="data-row"><label>Current Ward</label><span id="pop_b_ward" style="font-weight:bold; color:#00ff96;"></span></div>
                <div class="data-row"><label>Admission Bed Number</label><span id="pop_b_bed_number" style="font-weight:bold; color:#00ff96;"></span></div>
                <div class="data-row"><label>Condition Notes</label><span id="pop_b_notes"></span></div>
            </div>
            <div class="popup-col">
                <h4 style="color:#ff0080;"><i class="fas fa-female"></i> Mother Profile</h4>
                <div class="data-row"><label>Mother Full Name</label><span id="pop_m_name"></span></div>
                <div class="data-row"><label>Clinic Book Reference</label><span id="pop_m_book"></span></div>
                <div class="data-row"><label>Identity Card (NIC)</label><span id="pop_m_nic"></span></div>
                <div class="data-row"><label>Phone Contact</label><span id="pop_m_phone"></span></div>
                <div class="data-row"><label>Blood Specification</label><span id="pop_m_blood"></span></div>
                <div class="data-row"><label>Obstetric Metrics (G/P)</label>Gravida <span id="pop_m_g"></span>, Para <span id="pop_m_p"></span></div>
                <div class="data-row"><label>Expected Delivery Window (EDD)</label><span id="pop_m_edd"></span></div>
                <div class="data-row"><label>Maternal Risk Factors</label><span id="pop_m_risk" style="color:#ff4757; font-weight:bold;"></span></div>
            </div>
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
    function closeUnifiedBabyModal() { document.getElementById('unifiedBabyModal').style.display = 'none'; }

    function openUnifiedModalFromDoctor(babyId) {
        fetch('../admin/get_baby_details.php?baby_id=' + babyId)
            .then(response => response.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                document.getElementById('unifiedBabyModal').style.display = 'flex';
                document.getElementById('pop_b_name').innerText = data.baby_name;
                document.getElementById('pop_b_gender').innerText = data.baby_gender;
                document.getElementById('pop_b_dob').innerText = data.birth_date;
                document.getElementById('pop_b_weight').innerText = data.weight_kg + " kg";
                document.getElementById('pop_b_ward').innerText = data.ward_name + " Ward";
                document.getElementById('pop_b_bed_number').innerText = data.admission_bed_number || "N/A";
                document.getElementById('pop_b_notes').innerText = data.condition_notes || "None documented";
                
                document.getElementById('pop_m_name').innerText = data.mother_name;
                document.getElementById('pop_m_book').innerText = data.clinic_book_no || "N/A";
                document.getElementById('pop_m_nic').innerText = data.mother_nic;
                document.getElementById('pop_m_phone').innerText = data.mother_phone;
                document.getElementById('pop_m_blood').innerText = data.mother_blood || "Unknown";
                document.getElementById('pop_m_g').innerText = data.gravida || "0";
                document.getElementById('pop_m_p').innerText = data.para || "0";
                document.getElementById('pop_m_edd').innerText = data.edd_date || "N/A";
                document.getElementById('pop_m_risk').innerText = data.pregnancy_risk_factors || "None (Low Risk)";
            });
    }

    function openReportForm(data, targetType) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        
        // Reset old entry inputs cleanly
        document.getElementById('f_rep_id').value = "";
        document.getElementById('f_vitals').value = "";
        document.getElementById('f_symp').value = "";
        document.getElementById('f_diag').value = "";
        document.getElementById('f_pres').value = "";
        document.getElementById('f_rem').value = "";

        if(targetType === 'baby') {
            document.getElementById('modal_title').innerText = "Clinical Report: " + data.baby_name;
            document.getElementById('f_baby_id').value = data.baby_id;
            document.getElementById('f_p_id').value = "";
        } else {
            document.getElementById('modal_title').innerText = "Clinical Report: " + data.full_name;
            document.getElementById('f_p_id').value = data.id;
            document.getElementById('f_baby_id').value = "";
        }
    }

    function viewReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Medical Record View";
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'block';
        document.getElementById('form_area').style.display = 'none';
        
        document.getElementById('v_diag').innerText = r.diagnosis;
        document.getElementById('v_vitals').innerText = r.vitals;
        document.getElementById('v_symp').innerText = r.symptoms;
        document.getElementById('v_pres').innerText = r.prescription;
        document.getElementById('v_rem').innerText = r.remarks;
    }

    function editReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Update Clinical Report";
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        
        document.getElementById('f_rep_id').value = r.report_id;
        document.getElementById('f_p_id').value = r.patient_id || "";
        document.getElementById('f_baby_id').value = r.baby_id || "";
        document.getElementById('f_vitals').value = r.vitals;
        document.getElementById('f_symp').value = r.symptoms;
        document.getElementById('f_diag').value = r.diagnosis;
        document.getElementById('f_pres').value = r.prescription;
        document.getElementById('f_rem').value = r.remarks;
    }

    function fillBasicInfo(p) {
        document.getElementById('m_dob').innerText = p.dob;
        document.getElementById('m_gender').innerText = p.gender;
        document.getElementById('m_blood').innerText = p.blood_group;
        document.getElementById('m_nic').innerText = p.nic;
        document.getElementById('m_allergies').innerText = p.allergies || "None";
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
</script>
</body>
</html>