<?php
session_start();
require_once '../config/db.php';

// SECURITY CHECK: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$alert_message = "";

// 1. HANDLE NEW BABY REGISTRATION WITH TIME JOURNEY LOGS & BED NUMBER LINKING
if (isset($_POST['register_baby'])) {
    try {
        $pdo->beginTransaction();
        $mother_id = $_POST['mother_id'];

        // Extract the mother's most recent active inpatient stay bed number entry session cleanly
        $bed_stmt = $pdo->prepare("SELECT bed_number FROM patient_admissions WHERE patient_id = ? ORDER BY admitted_at DESC LIMIT 1");
        $bed_stmt->execute([$mother_id]);
        $active_stay_bed_id = $bed_stmt->fetchColumn() ?: null;

        // A. Insert the baby record into the babies data table linked to the unique Admission Bed Key
        $stmt = $pdo->prepare("INSERT INTO babies (mother_id, admission_bed_number, baby_name, baby_gender, birth_date, weight_kg, condition_notes, ward_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $mother_id,
            $active_stay_bed_id,
            !empty($_POST['baby_name']) ? $_POST['baby_name'] : 'Baby of Pink Card Holder',
            $_POST['baby_gender'],
            $_POST['birth_date'],
            $_POST['weight_kg'],
            $_POST['condition_notes'],
            $_POST['initial_ward']
        ]);
        
        $new_baby_id = $pdo->lastInsertId();

        // B. Automatically generate the initial timeline track path
        $log_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward, bed_number) VALUES (?, 'Admission', 'None', ?, ?)");
        $log_stmt->execute([$new_baby_id, $_POST['initial_ward'], $active_stay_bed_id]);

        $pdo->commit();
        header("Location: wards.php?msg=BabyRegistered");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $alert_message = "Error: " . $e->getMessage();
    }
}

// 2. HANDLE APPROVING DOCTOR'S RECOMMENDATION
if (isset($_POST['approve_recommendation'])) {
    try {
        $pdo->beginTransaction();
        $baby_id = $_POST['baby_id'];

        // Get active positioning values before shifting tracking arrays
        $curr_stmt = $pdo->prepare("SELECT ward_name, recommended_ward, admission_bed_number FROM babies WHERE baby_id = ?");
        $curr_stmt->execute([$baby_id]);
        $baby_info = $curr_stmt->fetch(PDO::FETCH_ASSOC);
        
        $old_ward = $baby_info['ward_name'];
        $new_ward = $baby_info['recommended_ward'];

        if ($old_ward !== $new_ward) {
            // Update table registry pointer
            $stmt = $pdo->prepare("UPDATE babies SET ward_name = ?, recommendation_status = 'None', recommended_ward = NULL WHERE baby_id = ?");
            $stmt->execute([$new_ward, $baby_id]);

            // Close old movement tracking layer log row
            $close_stmt = $pdo->prepare("
                UPDATE patient_movement_logs 
                SET left_at = NOW(),
                    duration_days = ROUND(TIMESTAMPDIFF(SECOND, entered_at, NOW()) / 86400, 2)
                WHERE baby_id = ? AND left_at IS NULL
            ");
            $close_stmt->execute([$baby_id]);

            // Open approved entry line track step
            $action = ($new_ward === 'To Discharge') ? 'Discharge' : 'Transfer';
            $open_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward, bed_number) VALUES (?, ?, ?, ?, ?)");
            $open_stmt->execute([$baby_id, $action, $old_ward, $new_ward, $baby_info['admission_bed_number']]);
        }

        $pdo->commit();
        header("Location: wards.php?msg=Approved");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $alert_message = "Error: " . $e->getMessage();
    }
}

// 3. HANDLE REJECTING DOCTOR'S RECOMMENDATION
if (isset($_POST['reject_recommendation'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET recommendation_status = 'None', recommended_ward = NULL WHERE baby_id = ?");
        $stmt->execute([$_POST['baby_id']]);
        header("Location: wards.php?msg=Rejected");
        exit();
    } catch (Exception $e) { $alert_message = "Error: " . $e->getMessage(); }
}

// FETCH DATA FOR NAVIGATION COUNT PANELS
$mothers = $pdo->query("SELECT id, full_name, clinic_book_no FROM patients WHERE patient_type = 'Pregnant' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$pending_recommendations = $pdo->query("SELECT b.*, p.full_name as mother_name FROM babies b JOIN patients p ON b.mother_id = p.id WHERE b.recommendation_status = 'Pending' ORDER BY b.created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

$counts = [
    'Normal' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Normal'")->fetchColumn(),
    'Critical' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Critical'")->fetchColumn(),
    'Other' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Other'")->fetchColumn(),
    'To Discharge' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='To Discharge'")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neonatal Wards Overview</title>
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

        .ward-layout-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
        .ward-nav-card { background: rgba(30, 30, 45, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 18px; padding: 30px 20px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 15px; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(0,0,0,0.3); cursor: pointer; }
        .ward-nav-card.card-normal { border-left: 5px solid #00ff96; }
        .ward-nav-card.card-critical { border-left: 5px solid #ff4757; }
        .ward-nav-card.card-other { border-left: 5px solid #3498db; }
        .ward-nav-card.card-discharge { border-left: 5px solid #e67e22; }

        .ward-nav-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.5); background: rgba(40, 40, 60, 0.9); }
        .ward-nav-card i { font-size: 40px; }
        .card-normal i { color: #00ff96; } .card-critical i { color: #ff4757; } .card-other i { color: #3498db; } .card-discharge i { color: #e67e22; }
        .ward-title-txt { color: #fff; font-size: 16px; font-weight: bold; text-transform: uppercase; margin: 0; }
        
        .nav-view-btn { margin-top: 5px; padding: 8px 18px; border: none; border-radius: 8px; font-size: 12px; font-weight: bold; text-transform: uppercase; background: rgba(255,255,255,0.06); color: #fff; border: 1px solid rgba(255,255,255,0.1); }
        .ward-nav-card:hover .nav-view-btn { background: #00ff96; color: #1a1a2e; border-color: #00ff96; }
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
            <p>Select a ward compartment card below to execute trace audits.</p>
        </div>

        <?php if (!empty($alert_message)): ?>
            <div style="padding:15px; background:rgba(255,71,87,0.1); color:#ff4757; border-radius:10px; margin-bottom:20px; border: 1px solid #ff4757;"><?php echo $alert_message; ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'BabyRegistered'): ?>
            <div style="padding:15px; background:rgba(0,255,150,0.1); color:#00ff96; border-radius:10px; margin-bottom:20px; border: 1px solid #00ff96;">✔ New baby details saved and assigned to ward stay period folder. Timeline history active.</div>
        <?php endif; ?>
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
            <div class="ward-nav-card card-normal" onclick="window.location.href='ward_patients.php?ward=Normal'">
                <i class="fas fa-check-circle"></i>
                <p class="ward-title-txt">Normal Ward</p>
                <span class="role-badge doctor"><?php echo $counts['Normal']; ?> Active Babies</span>
                <button class="nav-view-btn">Open Ward Table →</button>
            </div>
            <div class="ward-nav-card card-critical" onclick="window.location.href='ward_patients.php?ward=Critical'">
                <i class="fas fa-heartbeat"></i>
                <p class="ward-title-txt">Critical Ward</p>
                <span class="role-badge admin" style="background:rgba(255,71,87,0.15); color:#ff4757; border-color:rgba(255,71,87,0.3);"><?php echo $counts['Critical']; ?> Active Babies</span>
                <button class="nav-view-btn">Open Ward Table →</button>
            </div>
            <div class="ward-nav-card card-other" onclick="window.location.href='ward_patients.php?ward=Other'">
                <i class="fas fa-baby"></i>
                <p class="ward-title-txt">Other Ward</p>
                <span class="role-badge nurse"><?php echo $counts['Other']; ?> Active Babies</span>
                <button class="nav-view-btn">Open Ward Table →</button>
            </div>
            <div class="ward-nav-card card-discharge" onclick="window.location.href='ward_patients.php?ward=To Discharge'">
                <i class="fas fa-door-open"></i>
                <p class="ward-title-txt">To Discharge</p>
                <span class="role-badge" style="background:rgba(230,126,34,0.15); color:#e67e22; border:1px solid rgba(230,126,34,0.3);"><?php echo $counts['To Discharge']; ?> Active Babies</span>
                <button class="nav-view-btn">Open Ward Table →</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>