<?php
session_start();
require_once '../config/db.php';

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// Read URL Parameter safely
$selected_ward = isset($_GET['ward']) ? $_GET['ward'] : 'Normal';
$valid_wards = ['Normal', 'Critical', 'Other', 'To Discharge'];

if (!in_array($selected_ward, $valid_wards)) {
    $selected_ward = 'Normal';
}

// Handle Ward Transfers & Permanent Discharges without losing any records
if (isset($_POST['change_ward']) || isset($_POST['discharge_patient'])) {
    try {
        $pdo->beginTransaction();
        $baby_id = $_POST['baby_id'];
        
        // Get the baby's current ward status before executing changes
        $curr_stmt = $pdo->prepare("SELECT ward_name FROM babies WHERE baby_id = ?");
        $curr_stmt->execute([$baby_id]);
        $old_ward = $curr_stmt->fetchColumn();

        if (isset($_POST['discharge_patient'])) {
            // --- FIXED: DISCHARGE ACTION (NO DELETION) ---
            // 1. Close out the open tracking log record
            $close_stmt = $pdo->prepare("
                UPDATE patient_movement_logs 
                SET left_at = NOW(),
                    duration_days = ROUND(TIMESTAMPDIFF(SECOND, entered_at, NOW()) / 86400, 2)
                WHERE baby_id = ? AND left_at IS NULL
            ");
            $close_stmt->execute([$baby_id]);

            // 2. Add a final tracking step into the audit trail records
            $open_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward) VALUES (?, 'Discharge', ?, 'Discharged Home')");
            $open_stmt->execute([$baby_id, $old_ward]);

            // 3. KEEP THE DATA: Update status to 'Discharged' and ward to 'None' instead of deleting!
            $update_baby = $pdo->prepare("UPDATE babies SET status = 'Discharged', ward_name = 'None', recommendation_status = 'None' WHERE baby_id = ?");
            $update_baby->execute([$baby_id]);

            $pdo->commit();
            header("Location: ward_patients.php?ward=" . urlencode($selected_ward) . "&msg=Discharged");
            exit();
        } else {
            // --- STANDARD INTER-WARD TRANSFER ACTION ---
            $new_ward = $_POST['ward_name'];

            if ($old_ward !== $new_ward) {
                // 1. Update table registry pointer
                $stmt = $pdo->prepare("UPDATE babies SET ward_name = ?, recommendation_status = 'None' WHERE baby_id = ?");
                $stmt->execute([$new_ward, $baby_id]);

                // 2. Close out previous open stay tracking layer
                $close_stmt = $pdo->prepare("
                    UPDATE patient_movement_logs 
                    SET left_at = NOW(),
                        duration_days = ROUND(TIMESTAMPDIFF(SECOND, entered_at, NOW()) / 86400, 2)
                WHERE baby_id = ? AND left_at IS NULL
                ");
                $close_stmt->execute([$baby_id]);

                // 3. Open next step log layer line trace
                $open_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward) VALUES (?, 'Transfer', ?, ?)");
                $open_stmt->execute([$baby_id, $old_ward, $new_ward]);
            }

            $pdo->commit();
            header("Location: ward_patients.php?ward=" . urlencode($selected_ward) . "&msg=Success");
            exit();
        }
    } catch (Exception $e) { 
        $pdo->rollBack();
        $error = $e->getMessage(); 
    }
}

// Fetch ONLY babies assigned inside this ward who are STILL active status
$query = "SELECT b.*, p.full_name as mother_name 
          FROM babies b 
          JOIN patients p ON b.mother_id = p.id 
          WHERE b.ward_name = ? AND b.status = 'Active'
          ORDER BY b.birth_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([$selected_ward]);
$babies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_ward; ?> Ward - Patients</title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .ward-table-card { background: rgba(30, 30, 45, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 18px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
        .back-nav-btn { display: inline-flex; align-items: center; gap: 8px; color: #00ff96; text-decoration: none; font-size: 14px; margin-bottom: 20px; font-weight: bold; transition: 0.2s; }
        .back-nav-btn:hover { color: #fff; transform: translateX(-3px); }
        
        .clickable-row { cursor: pointer; transition: 0.2s; }
        .clickable-row:hover { background: rgba(0, 255, 150, 0.05) !important; }
        
        .select-transfer-input { padding: 6px 12px; border-radius: 6px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px; cursor: pointer; width: 100%; max-width: 180px; }
        .btn-discharge { background: linear-gradient(135deg, #ff4757 0%, #ff2a3a 100%); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 11px; font-weight: bold; text-transform: uppercase; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 10px rgba(255, 71, 87, 0.2); }
        .btn-discharge:hover { transform: translateY(-1px); box-shadow: 0 6px 14px rgba(255, 71, 87, 0.4); filter: brightness(1.1); }
        .empty-bed-notice { color: #aaa; text-align: center; font-style: italic; padding: 40px; font-size: 15px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-card { background: rgba(30, 30, 45, 1); border: 1px solid #ff0080; width: 95%; max-width: 800px; padding: 30px; border-radius: 20px; color: white; max-height: 90vh; overflow-y: auto; }
        .popup-split-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 15px; }
        .popup-col h4 { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 8px; margin-bottom: 12px; font-weight: 600; }
        .data-row { margin-bottom: 10px; font-size: 13px; text-align: left; }
        .data-row label { display: block; color: #aaa; font-size: 11px; text-transform: uppercase; margin-bottom: 2px; font-weight: 600; }
        .data-row span { color: #fff; }

        .journey-log-item { background: rgba(255,255,255,0.02); padding: 12px 18px; border-radius: 8px; border-left: 4px solid #00ff96; font-size: 13px; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
    </style>
</head>
<body>

<div class="container-main">
    <div class="sidebar">
        <div class="profile-section"><div class="avatar">🧑‍💼</div><h3>Pawan</h3><p>Administrator</p></div>
        <div class="nav-menu">
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=dashboard'"><i class="fas fa-home"></i> Admin Dashboard</div>
            <div class="nav-item active" onclick="window.location.href='wards.php'"><i class="fas fa-baby"></i> Wards (Infants)</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=staff'"><i class="fas fa-users-cog"></i> Staff Management</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=patients'"><i class="fas fa-hospital-user"></i> Patient Records</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=reports'"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
        <div class="logout-section"><a href="../logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content">
        <a href="wards.php" class="back-nav-btn"><i class="fas fa-arrow-left"></i> Back to Wards Station Overview</a>

        <div class="content-header">
            <h1><?php echo htmlspecialchars($selected_ward); ?> Ward Patient Directory</h1>
            <p>Currently viewing active delivery charts for this cluster location.</p>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Success'): ?>
            <div style="padding:15px; background:rgba(0,255,150,0.1); color:#00ff96; border-radius:10px; margin-bottom:20px; border: 1px solid #00ff96;">
                ✔ Patient ward localization map records synchronized successfully.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Discharged'): ?>
            <div style="padding:15px; background:rgba(230,126,34,0.15); color:#e67e22; border-radius:10px; margin-bottom:20px; border: 1px solid #e67e22;">
                🏁 Infant discharged home successfully! Removed from ward view, archived securely.
            </div>
        <?php endif; ?>

        <div class="ward-table-card">
            <?php if (empty($babies)): ?>
                <div class="empty-bed-notice">📭 There are currently no active infant records registered in this ward tracking block.</div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Infant Name</th>
                            <th>Mother's Full Name</th>
                            <th>Gender</th>
                            <th>Birth Date / Time</th>
                            <th>Weight</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($babies as $b): ?>
                        <tr class="clickable-row" onclick="openUnifiedModal(<?php echo $b['baby_id']; ?>)">
                            <td><strong><?php echo htmlspecialchars($b['baby_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['mother_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['baby_gender']); ?></td>
                            <td><?php echo date('M d, Y - H:i', strtotime($b['birth_date'])); ?></td>
                            <td><?php echo $b['weight_kg']; ?> kg</td>
                            <td onclick="event.stopPropagation();">
                                <?php if ($selected_ward === 'To Discharge'): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to permanently discharge this baby home?');">
                                        <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                        <button type="submit" name="discharge_patient" class="btn-discharge">
                                            <i class="fas fa-door-open"></i> Discharge Patient
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST">
                                        <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                        <select name="ward_name" class="select-transfer-input" onchange="this.form.submit()">
                                            <option value="" selected disabled>Transfer to...</option>
                                            <?php 
                                            foreach ($valid_wards as $w) {
                                                $disabled = ($w === $selected_ward) ? 'disabled style="color:#555;"' : '';
                                                echo "<option value='$w' $disabled>$w Ward</option>";
                                            }
                                            ?>
                                        </select>
                                        <input type="hidden" name="change_ward" value="1">
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="unifiedModal" class="modal-overlay">
    <div class="modal-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:10px;">
            <h2 style="color:#ff0080; margin:0;"><i class="fas fa-hospital-user"></i> Clinical Case Overview</h2>
            <button class="btn" onclick="closeUnifiedModal()" style="background:#ff4757; border:none; padding:5px 12px; color:white; font-weight:bold; cursor:pointer;">&times;</button>
        </div>
        <div class="popup-split-grid">
            <div class="popup-col">
                <h4 style="color:#00ff96;"><i class="fas fa-baby"></i> Infant Tracking Records</h4>
                <div class="data-row"><label>Baby Name</label><span id="pop_b_name"></span></div>
                <div class="data-row"><label>Gender</label><span id="pop_b_gender"></span></div>
                <div class="data-row"><label>Birth Date / Time</label><span id="pop_b_dob"></span></div>
                <div class="data-row"><label>Weight at Delivery</label><span id="pop_b_weight"></span></div>
                <div class="data-row"><label>Current Station Location</label><span id="pop_b_ward" style="font-weight:bold; color:#00ff96;"></span></div>
                <div class="data-row"><label>Admission Bed Number</label><span id="pop_b_bed_number" style="font-weight:bold; color:#00ff96;"></span></div>
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

        <div style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <h4 style="color:#ffa502; font-weight:600; margin-bottom:15px;"><i class="fas fa-route"></i> Clinical Ward Stay Timeline Journey</h4>
            <div id="timeline_tree_output" style="display: flex; flex-direction: column; gap: 10px; padding-left: 5px;"></div>
        </div>
    </div>
</div>

<script>
function openUnifiedModal(babyId) {
    fetch('get_baby_details.php?baby_id=' + babyId)
        .then(response => response.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            document.getElementById('unifiedModal').style.display = 'flex';
            
            document.getElementById('pop_b_name').innerText = data.baby_name;
            document.getElementById('pop_b_gender').innerText = data.baby_gender;
            document.getElementById('pop_b_dob').innerText = data.birth_date;
            document.getElementById('pop_b_weight').innerText = data.weight_kg + " kg";
            document.getElementById('pop_b_ward').innerText = data.status === 'Discharged' ? 'Discharged' : data.ward_name + " Ward";
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

            const treeContainer = document.getElementById('timeline_tree_output');
            treeContainer.innerHTML = "";

            if (data.movement_history && data.movement_history.length > 0) {
                data.movement_history.forEach((log) => {
                    let indicatorIcon = "📥";
                    let accentColor = "#00ff96";
                    
                    if (log.to_ward === 'Critical') { indicatorIcon = "🚨"; accentColor = "#ff4757"; }
                    if (log.to_ward === 'Other') { indicatorIcon = "🔵"; accentColor = "#3498db"; }
                    if (log.to_ward === 'To Discharge') { indicatorIcon = "📤"; accentColor = "#e67e22"; }
                    if (log.to_ward === 'Discharged Home') { indicatorIcon = "🏁"; accentColor = "#ffaa00"; }

                    let spanDuration = log.left_at ? `── Spent ${log.duration_days} Days` : `── Active Position Now`;
                    let timeOutput = new Date(log.entered_at).toLocaleString();

                    let divRowNode = document.createElement('div');
                    divRowNode.className = "journey-log-item";
                    divRowNode.style.borderLeftColor = accentColor;
                    divRowNode.innerHTML = `
                        <div>${indicatorIcon} <strong>${log.action_type} to ${log.to_ward}</strong></div>
                        <div style="font-size:11px; color:#aaa;">In: ${timeOutput}</div>
                        <div style="color:${accentColor}; font-weight:bold;">${spanDuration}</div>
                    `;
                    treeContainer.appendChild(divRowNode);
                });
            } else {
                treeContainer.innerHTML = "<div style='color:#555; font-style:italic;'>No history tracking path recorded.</div>";
            }
        });
}

function closeUnifiedModal() { document.getElementById('unifiedModal').style.display = 'none'; }
</script>
</body>
</html>