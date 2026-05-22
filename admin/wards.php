<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$message = "";

if (isset($_POST['register_baby'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO babies (mother_id, baby_name, baby_gender, birth_date, weight_kg, condition_notes, ward_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['mother_id'],
            !empty($_POST['baby_name']) ? $_POST['baby_name'] : 'Baby of Pink Card Holder',
            $_POST['baby_gender'],
            $_POST['birth_date'],
            $_POST['weight_kg'],
            $_POST['condition_notes'],
            $_POST['initial_ward']
        ]);
        header("Location: wards.php?msg=BabyRegistered");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

if (isset($_POST['change_ward'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET ward_name = ?, recommendation_status = 'None' WHERE baby_id = ?");
        $stmt->execute([$_POST['ward_name'], $_POST['baby_id']]);
        header("Location: wards.php?msg=TransferSuccess");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

if (isset($_POST['approve_recommendation'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET ward_name = recommended_ward, recommendation_status = 'None', recommended_ward = NULL WHERE baby_id = ?");
        $stmt->execute([$_POST['baby_id']]);
        header("Location: wards.php?msg=Approved");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

if (isset($_POST['reject_recommendation'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET recommendation_status = 'None', recommended_ward = NULL WHERE baby_id = ?");
        $stmt->execute([$_POST['baby_id']]);
        header("Location: wards.php?msg=Rejected");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

$mothers = $pdo->query("SELECT id, full_name, clinic_book_no FROM patients WHERE patient_type = 'Pregnant' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pending_recommendations = $pdo->query("SELECT b.*, p.full_name as mother_name FROM babies b JOIN patients p ON b.mother_id = p.id WHERE b.recommendation_status = 'Pending' ORDER BY b.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

$wards = ['Normal' => [], 'Critical' => [], 'Other' => [], 'To Discharge' => []];
$all_babies = $pdo->query("SELECT b.*, p.full_name as mother_name FROM babies b JOIN patients p ON b.mother_id = p.id ORDER BY b.birth_date DESC")->fetchAll(PDO::FETCH_ASSOC);

foreach ($all_babies as $baby) {
    if (array_key_exists($baby['ward_name'], $wards)) {
        $wards[$baby['ward_name']][] = $baby;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neonatal Wards Management</title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-section-card { background: rgba(30, 30, 45, 0.85); padding: 25px; border-radius: 15px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 30px; }
        .form-inline-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; }
        .form-inline-grid input, .form-inline-grid select { width: 100%; padding: 10px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 13px; }
        
        .rec-box-list { background: rgba(255, 165, 0, 0.05); border: 1px dashed #ffa502; padding: 20px; border-radius: 15px; margin-bottom: 30px; }
        .rec-item { display: flex; align-items: center; justify-content: space-between; background: rgba(30,30,45,0.8); padding: 12px 20px; border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.05); }
        .rec-buttons { display: flex; gap: 8px; }
        .btn-action { padding: 6px 14px; border: none; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; cursor: pointer; }
        .btn-approve { background: #00ff96; color: #1a1a2e; }
        .btn-reject { background: #ff4757; color: white; }

        .ward-layout-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .ward-column-card { background: rgba(30, 30, 45, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 18px; padding: 20px; min-height: 400px; display: flex; flex-direction: column; }
        .ward-header { font-size: 15px; font-weight: bold; text-transform: uppercase; padding-bottom: 12px; margin-bottom: 15px; border-bottom: 2px solid; display: flex; align-items: center; gap: 10px; }
        .ward-normal { color: #00ff96; border-bottom-color: #00ff96; }
        .ward-critical { color: #ff4757; border-bottom-color: #ff4757; }
        .ward-other { color: #3498db; border-bottom-color: #3498db; }
        .ward-discharge { color: #e67e22; border-bottom-color: #e67e22; }

        .patient-list-container { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
        .patient-bed-tile { background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); padding: 15px; border-radius: 10px; cursor: pointer; transition: 0.2s; }
        .patient-bed-tile:hover { background: rgba(0, 255, 150, 0.05); border-color: #00ff96; }
        .patient-bed-tile h5 { margin: 0 0 5px 0; color: #00ff96; font-size: 14px; }
        .patient-bed-tile p { margin: 0 0 8px 0; color: #ccc; font-size: 12px; line-height: 1.4; }
        .select-transfer-input { width: 100%; padding: 8px; border-radius: 6px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px; cursor: pointer; }
        .empty-bed-notice { color: #555; text-align: center; font-style: italic; margin-top: 40px; font-size: 13px; }

        /* Unified Popup Overlay Setup Style */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-card { background: rgba(30, 30, 45, 1); border: 1px solid #ff0080; width: 95%; max-width: 800px; padding: 30px; border-radius: 20px; color: white; max-height: 90vh; overflow-y: auto; }
        .popup-split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 15px; }
        .popup-col h4 { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 12px; font-weight: 600; }
        .data-row { margin-bottom: 10px; font-size: 13px; }
        .data-row label { display: block; color: #aaa; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; font-weight: 600; }
        .data-row span { color: #fff; }
    </style>
</head>
<body>

<div class="container-main">
    <div class="sidebar">
        <div class="profile-section"><div class="avatar">🧑‍💼</div><h3>Pawan</h3><p>Administrator</p></div>
        <div class="nav-menu">
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=dashboard'"><i class="fas fa-home"></i> Admin Dashboard</div>
            <div class="nav-item active"><i class="fas fa-baby"></i> Wards (Infants)</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=staff'"><i class="fas fa-users-cog"></i> Staff Management</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=patients'"><i class="fas fa-hospital-user"></i> Patient Records</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=reports'"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
        <div class="logout-section"><a href="../logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content">
        <div class="content-header">
            <h1>Infant Ward Management Portal</h1>
            <p>Current Date: <?php echo date('F d, Y'); ?></p>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Approved'): ?>
            <div style="padding:15px; background:rgba(0,255,150,0.1); color:#00ff96; border-radius:10px; margin-bottom:20px; border: 1px solid #00ff96;">✔ Doctor recommendation Approved. Infant successfully transferred.</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Rejected'): ?>
            <div style="padding:15px; background:rgba(255,71,87,0.1); color:#ff4757; border-radius:10px; margin-bottom:20px; border: 1px solid #ff4757;">❌ Doctor recommendation Declined and removed from queue.</div>
        <?php endif; ?>

        <?php if (!empty($pending_recommendations)): ?>
            <div class="rec-box-list">
                <h4 style="color:#ffa502; margin-top:0; margin-bottom:12px;"><i class="fas fa-bell"></i> Pending Doctor Recommendations</h4>
                <?php foreach ($pending_recommendations as $r): ?>
                    <div class="rec-item">
                        <div style="color:white; font-size:13px;">
                            Recommend moving <strong><?php echo htmlspecialchars($r['baby_name']); ?></strong> (Mother: <?php echo htmlspecialchars($r['mother_name']); ?>) 
                            from <span style="color:#ff4757; font-weight:bold;"><?php echo $r['ward_name']; ?> Ward</span> 
                            to <span style="color:#00ff96; font-weight:bold;"><?php echo $r['recommended_ward']; ?> Ward</span>.
                        </div>
                        <div class="rec-buttons">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="baby_id" value="<?php echo $r['baby_id']; ?>">
                                <button type="submit" name="approve_recommendation" class="btn-action btn-approve">Approve</button>
                                <button type="submit" name="reject_recommendation" class="btn-action btn-reject">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-section-card">
            <h3 style="color:#00ff96; margin-top:0; margin-bottom:15px;"><i class="fas fa-baby-carriage"></i> Log Newborn Delivery Admission</h3>
            <form method="POST">
                <div class="form-inline-grid">
                    <div>
                        <select name="mother_id" required>
                            <option value="" selected disabled>Select Mother...</option>
                            <?php foreach ($mothers as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?> (<?php echo htmlspecialchars($m['clinic_book_no']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><input type="text" name="baby_name" placeholder="Baby Name (Optional)"></div>
                    <div>
                        <select name="baby_gender" required>
                            <option value="" selected disabled>Select Gender...</option>
                            <option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option>
                        </select>
                    </div>
                    <div><input type="datetime-local" name="birth_date" required></div>
                    <div><input type="number" step="0.001" name="weight_kg" placeholder="Weight (kg)" required></div>
                    <div><input type="text" name="condition_notes" placeholder="Condition Description"></div>
                    <div>
                        <select name="initial_ward" required>
                            <option value="Normal">Assign: Normal Ward</option><option value="Critical">Assign: Critical Ward</option>
                            <option value="Other">Assign: Other Ward</option><option value="To Discharge">Assign: To Discharge</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="register_baby" class="btn" style="margin-top:15px; width:100%; background:#00ff96; color:#1a1a2e; font-weight:bold; border:none;">✓ SAVE INFANT RECORD & ADMIT TO WARD</button>
            </form>
        </div>

        <div class="ward-layout-grid">
            <?php foreach ($wards as $ward_title => $babies_in_ward): ?>
                <div class="ward-column-card">
                    <div class="ward-header ward-<?php echo strtolower(explode(' ', $ward_title)[0]); ?>">
                        <i class="fas fa-baby"></i> <?php echo $ward_title; ?> Ward (<?php echo count($babies_in_ward); ?>)
                    </div>
                    <div class="patient-list-container">
                        <?php if (empty($babies_in_ward)): ?><div class="empty-bed-notice">No infants here</div><?php endif; ?>
                        <?php foreach ($babies_in_ward as $b): ?>
                            <div class="patient-bed-tile" onclick="openUnifiedModal(<?php echo $b['baby_id']; ?>, event)">
                                <h5><?php echo htmlspecialchars($b['baby_name']); ?></h5>
                                <p>
                                    <strong>Mother:</strong> <?php echo htmlspecialchars($b['mother_name']); ?><br>
                                    <strong>Gender:</strong> <?php echo htmlspecialchars($b['baby_gender']); ?><br>
                                    <strong>Born:</strong> <?php echo date('M d, H:i', strtotime($b['birth_date'])); ?><br>
                                    <strong>Weight:</strong> <?php echo $b['weight_kg']; ?> kg
                                </p>
                                <form method="POST" onclick="event.stopPropagation();">
                                    <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                    <select name="ward_name" class="select-transfer-input" onchange="this.form.submit()">
                                        <option value="" selected disabled>Transfer to...</option>
                                        <option value="Normal" <?php if($ward_title=='Normal') echo 'disabled'; ?>>Normal Ward</option>
                                        <option value="Critical" <?php if($ward_title=='Critical') echo 'disabled'; ?>>Critical Ward</option>
                                        <option value="Other" <?php if($ward_title=='Other') echo 'disabled'; ?>>Other Ward</option>
                                        <option value="To Discharge" <?php if($ward_title=='To Discharge') echo 'disabled'; ?>>To Discharge</option>
                                    </select>
                                    <input type="hidden" name="change_ward" value="1">
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div id="unifiedModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">
            <h2 style="color:#ff0080; margin:0;"><i class="fas fa-hospital-user"></i> Clinical Case Overview</h2>
            <button class="btn" onclick="closeUnifiedModal()" style="background:#ff4757; border:none; padding:5px 12px;">&times;</button>
        </div>
        <div class="popup-split-grid">
            <div class="popup-col">
                <h4 style="color:#00ff96;"><i class="fas fa-baby"></i> Infant Tracking Records</h4>
                <div class="data-row"><label>Baby Name</label><span id="pop_b_name"></span></div>
                <div class="data-row"><label>Gender</label><span id="pop_b_gender"></span></div>
                <div class="data-row"><label>Birth Date / Time</label><span id="pop_b_dob"></span></div>
                <div class="data-row"><label>Weight at Delivery</label><span id="pop_b_weight"></span></div>
                <div class="data-row"><label>Current Station Location</label><span id="pop_b_ward" style="font-weight:bold; color:#00ff96;"></span></div>
                <div class="data-row"><label>Neonatal Clinical Notes</label><span id="pop_b_notes"></span></div>
            </div>
            <div class="popup-col">
                <h4 style="color:#ff0080;"><i class="fas fa-female"></i> Mother Maternal Health Profile</h4>
                <div class="data-row"><label>Mother Full Name</label><span id="pop_m_name"></span></div>
                <div class="data-row"><label>Clinic Book Reference</label><span id="pop_m_book"></span></div>
                <div class="data-row"><label>Identity Card (NIC)</label><span id="pop_m_nic"></span></div>
                <div class="data-row"><label>Phone Contact</label><span id="pop_m_phone"></span></div>
                <div class="data-row"><label>Blood Specification</label><span id="pop_m_blood"></span></div>
                <div class="data-row"><label>Obstetric Metrics (G/P)</label>Gravida <span id="pop_m_g"></span>, Para <span id="pop_m_p"></span></div>
                <div class="data-row"><label>Expected Delivery Window (EDD)</label><span id="pop_m_edd"></span></div>
                <div class="data-row"><label>High Risk Conditions Checklist</label><span id="pop_m_risk" style="color:#ff4757; font-weight:bold;"></span></div>
            </div>
        </div>
    </div>
</div>

<script>
function openUnifiedModal(babyId, event) {
    fetch('get_baby_details.php?baby_id=' + babyId)
        .then(response => response.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            
            document.getElementById('unifiedModal').style.display = 'flex';
            // Infant Assignments
            document.getElementById('pop_b_name').innerText = data.baby_name;
            document.getElementById('pop_b_gender').innerText = data.baby_gender;
            document.getElementById('pop_b_dob').innerText = data.birth_date;
            document.getElementById('pop_b_weight').innerText = data.weight_kg + " kg";
            document.getElementById('pop_b_ward').innerText = data.ward_name + " Ward";
            document.getElementById('pop_b_notes').innerText = data.condition_notes || "None documented";
            
            // Maternal Profile Mapping
            document.getElementById('pop_m_name').innerText = data.mother_name;
            document.getElementById('pop_m_book').innerText = data.clinic_book_no;
            document.getElementById('pop_m_nic').innerText = data.mother_nic;
            document.getElementById('pop_m_phone').innerText = data.mother_phone;
            document.getElementById('pop_m_blood').innerText = data.mother_blood || "Unknown";
            document.getElementById('pop_m_g').innerText = data.gravida;
            document.getElementById('pop_m_p').innerText = data.para;
            document.getElementById('pop_m_edd').innerText = data.edd_date;
            document.getElementById('pop_m_risk').innerText = data.pregnancy_risk_factors || "None Listed (Low Risk)";
        });
}

function closeUnifiedModal() {
    document.getElementById('unifiedModal').style.display = 'none';
}
</script>
</body>
</html>