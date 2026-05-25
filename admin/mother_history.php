<?php
session_start();
require_once '../config/db.php';

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$mother_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. FETCH MOTHER DETAILS
$m_stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$m_stmt->execute([$mother_id]);
$mother = $m_stmt->fetch(PDO::FETCH_ASSOC);

if (!$mother) {
    header("Location: dashboard.php?tab=patients");
    exit();
}

// 2. FETCH ALL BABIES (Active AND Discharged) BELONGING TO THIS MOTHER
$b_stmt = $pdo->prepare("SELECT * FROM babies WHERE mother_id = ? ORDER BY status ASC, birth_date DESC");
$b_stmt->execute([$mother_id]);
$active_babies = $b_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. FETCH PREGNANCY ADMISSIONS (STAY HISTORY)
$adm_stmt = $pdo->prepare("SELECT * FROM patient_admissions WHERE patient_id = ? ORDER BY admitted_at DESC");
$adm_stmt->execute([$mother_id]);
$admissions = $adm_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Archive: <?php echo htmlspecialchars($mother['full_name']); ?></title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-summary-card { background: rgba(30, 30, 45, 0.85); padding: 25px; border-radius: 15px; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 30px; text-align: left; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .meta-block label { display: block; color: #00ff96; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .meta-block span { color: #fff; font-size: 14px; }
        
        .back-nav-btn { display: inline-flex; align-items: center; gap: 8px; color: #00ff96; text-decoration: none; font-size: 14px; margin-bottom: 20px; font-weight: bold; transition: 0.2s; }
        .back-nav-btn:hover { color: #fff; transform: translateX(-3px); }

        .clickable-baby-row { cursor: pointer; transition: 0.2s; }
        .clickable-baby-row:hover { background: rgba(0, 255, 150, 0.05) !important; }

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
            <div class="nav-item" onclick="window.location.href='wards.php'"><i class="fas fa-procedures"></i> Wards</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=staff'"><i class="fas fa-users-cog"></i> Staff Management</div>
            <div class="nav-item active" onclick="window.location.href='dashboard.php?tab=patients'"><i class="fas fa-hospital-user"></i> Patient Records</div>
            <div class="nav-item" onclick="window.location.href='dashboard.php?tab=reports'"><i class="fas fa-chart-pie"></i> Reports</div>
        </div>
        <div class="logout-section"><a href="../logout.php" class="logout-btn">Logout</a></div>
    </div>

    <div class="main-content">
        <a href="dashboard.php?tab=patients" class="back-nav-btn"><i class="fas fa-arrow-left"></i> Return to Patients Index</a>

        <div class="profile-summary-card">
            <h2 style="margin:0; color:#00ff96;"><i class="fas fa-female"></i> Maternal File: <?php echo htmlspecialchars($mother['full_name']); ?></h2>
            <div class="meta-grid">
                <div class="meta-block"><label>NIC Identity</label><span><?php echo htmlspecialchars($mother['nic']); ?></span></div>
                <div class="meta-block"><label>Phone Reference</label><span><?php echo htmlspecialchars($mother['phone']); ?></span></div>
                <div class="meta-block"><label>Clinic Card Ref</label><span><?php echo htmlspecialchars($mother['clinic_book_no'] ?: 'N/A'); ?></span></div>
                <div class="meta-block"><label>Obstetrics (G/P)</label><span>Gravida <?php echo $mother['gravida']; ?>, Para <?php echo $mother['para']; ?></span></div>
            </div>
        </div>

        <div class="users-table-container">
            <div class="table-header">
                <h3>👶 Linked Infant Delivery Directory</h3>
                <p style="color:#aaa; font-size:12px;">Click on any infant below to see their medical overview and detailed timeline history.</p>
            </div>
            
            <?php if (empty($active_babies)): ?>
                <div class="no-users"><p>📭 No active or historical infant records registered under this profile.</p></div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Infant Tracking Name</th>
                            <th>Gender</th>
                            <th>Birth Timestamp</th>
                            <th>Birth Weight</th>
                            <th>Current System Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_babies as $b): ?>
                        <tr class="clickable-baby-row" onclick="openUnifiedModal(<?php echo $b['baby_id']; ?>)">
                            <td><strong><?php echo htmlspecialchars($b['baby_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['baby_gender']); ?></td>
                            <td><?php echo date('M d, Y - H:i', strtotime($b['birth_date'])); ?></td>
                            <td><?php echo $b['weight_kg']; ?> kg</td>
                            <td>
                                <?php if ($b['status'] === 'Discharged'): ?>
                                    <span class="role-badge admin" style="text-transform:uppercase; background:rgba(230,126,34,0.15); color:#e67e22; border-color:rgba(230,126,34,0.3);">
                                        🏁 Discharged Home
                                    </span>
                                <?php else: ?>
                                    <span class="role-badge doctor" style="text-transform:uppercase;">
                                        Active: <?php echo htmlspecialchars($b['ward_name']); ?> Ward
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="users-table-container" style="margin-top: 30px;">
            <div class="table-header">
                <h3>Maternal Pregnancy Admission & Stay Periods</h3>
                <p style="color:#aaa; font-size:12px;">Chronological timeline listing all pregnancy admission stay periods with assigned unique bed numbers.</p>
            </div>
            
            <?php if (empty($admissions)): ?>
                <div class="no-users"><p>📭 No pregnancy admission stay records registered under this profile.</p></div>
            <?php else: ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Stay ID</th>
                            <th>Stay Bed Number</th>
                            <th>Clinic Book Reference</th>
                            <th>LMP Date</th>
                            <th>EDD Date</th>
                            <th>Admitted Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admissions as $adm): ?>
                        <?php $has_bed = !empty($adm['bed_number']); ?>
                        <tr <?php if ($has_bed): ?>class="clickable-baby-row" onclick="openUnifiedModalByBedNumber('<?php echo htmlspecialchars($adm['bed_number']); ?>')"<?php endif; ?>>
                            <td>#<?php echo $adm['admission_id']; ?></td>
                            <td><strong style="color: #00ff96;"><?php echo htmlspecialchars($adm['bed_number'] ?: 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($adm['clinic_book_no'] ?: 'N/A'); ?></td>
                            <td><?php echo $adm['lmp_date'] ? date('M d, Y', strtotime($adm['lmp_date'])) : 'N/A'; ?></td>
                            <td><?php echo $adm['edd_date'] ? date('M d, Y', strtotime($adm['edd_date'])) : 'N/A'; ?></td>
                            <td><?php echo date('M d, Y - H:i', strtotime($adm['admitted_at'])); ?></td>
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
                <div class="data-row"><label>Neonatal Notes</label><span id="pop_b_notes"></span></div>
            </div>
            <div class="popup-col">
                <h4 style="color:#ff0080;"><i class="fas fa-female"></i> Mother Profile Reference</h4>
                <div class="data-row"><label>Mother Full Name</label><span id="pop_m_name"></span></div>
                <div class="data-row"><label>Clinic Book Reference</label><span id="pop_m_book"></span></div>
                <div class="data-row"><label>Identity Card (NIC)</label><span id="pop_m_nic"></span></div>
                <div class="data-row"><label>Phone Contact</label><span id="pop_m_phone"></span></div>
                <div class="data-row"><label>Blood Specification</label><span id="pop_m_blood"></span></div>
                <div class="data-row"><label>Expected Delivery Window (EDD)</label><span id="pop_m_edd"></span></div>
                <div class="data-row"><label>High Risk Conditions Checklist</label><span id="pop_m_risk" style="color:#ff4757; font-weight:bold;"></span></div>
            </div>        <div style="margin-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h4 style="color:#ffa502; font-weight:600; margin:0;"><i class="fas fa-route"></i> Clinical Ward Stay Timeline Journey</h4>
                <div id="stay_period_selector_wrapper" style="display: none; align-items: center; gap: 8px;">
                    <label style="font-size: 11px; color: #aaa; font-weight: 600; text-transform: uppercase;">Stay Period:</label>
                    <select id="pop_stay_period_select" onchange="changeStayPeriodFilter()" style="padding: 6px 12px; border-radius: 6px; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.2); color: white; font-size: 12px; cursor: pointer; outline: none;"></select>
                </div>
            </div>
            <div id="timeline_tree_output" style="display: flex; flex-direction: column; gap: 10px; padding-left: 5px;"></div>
        </div>
    </div>
</div>

<script>
let currentOpenBabyId = null;

function openUnifiedModal(babyId, bedNumber = '') {
    currentOpenBabyId = babyId;
    let url = 'get_baby_details.php?baby_id=' + babyId;
    if (bedNumber) {
        url += '&bed_number=' + encodeURIComponent(bedNumber);
    }
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) { alert(data.error); return; }
            document.getElementById('unifiedModal').style.display = 'flex';
            
            document.getElementById('pop_b_name').innerText = data.baby_name;
            document.getElementById('pop_b_gender').innerText = data.baby_gender;
            document.getElementById('pop_b_dob').innerText = data.birth_date;
            document.getElementById('pop_b_weight').innerText = data.weight_kg + " kg";
            document.getElementById('pop_b_ward').innerText = data.status === 'Discharged' ? 'Discharged Home 🏁' : data.ward_name + " Ward";
            document.getElementById('pop_b_bed_number').innerText = data.admission_bed_number || "N/A";
            document.getElementById('pop_b_notes').innerText = data.condition_notes || "None documented";
            
            document.getElementById('pop_m_name').innerText = data.mother_name;
            document.getElementById('pop_m_book').innerText = data.clinic_book_no || "N/A";
            document.getElementById('pop_m_nic').innerText = data.mother_nic;
            document.getElementById('pop_m_phone').innerText = data.mother_phone;
            document.getElementById('pop_m_blood').innerText = data.mother_blood || "Unknown";
            document.getElementById('pop_m_edd').innerText = data.edd_date || "N/A";
            document.getElementById('pop_m_risk').innerText = data.pregnancy_risk_factors || "None (Low Risk)";

            // Update Stay Period Dropdown
            const selectorWrapper = document.getElementById('stay_period_selector_wrapper');
            const selectDropdown = document.getElementById('pop_stay_period_select');
            
            if (data.all_stays && data.all_stays.length > 1) {
                selectDropdown.innerHTML = "";
                data.all_stays.forEach((stay) => {
                    let option = document.createElement('option');
                    option.value = stay;
                    option.text = stay;
                    if (stay === data.selected_bed_number) {
                        option.selected = true;
                    }
                    selectDropdown.appendChild(option);
                });
                selectorWrapper.style.display = 'flex';
            } else {
                selectorWrapper.style.display = 'none';
            }

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
                treeContainer.innerHTML = "<div style='color:#555; font-style:italic;'>No history tracking path recorded for this stay period.</div>";
            }
        });
}

function changeStayPeriodFilter() {
    const selectedBed = document.getElementById('pop_stay_period_select').value;
    if (currentOpenBabyId) {
        openUnifiedModal(currentOpenBabyId, selectedBed);
    }
}

function openUnifiedModalByBedNumber(bedNumber) {
    if (!bedNumber) return;
    let url = 'get_baby_details.php?bed_number=' + encodeURIComponent(bedNumber);
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) { 
                alert(data.error); 
                return; 
            }
            openUnifiedModal(data.baby_id, bedNumber);
        })
        .catch(err => {
            alert("Failed to fetch details for this stay period.");
        });
}

function closeUnifiedModal() { document.getElementById('unifiedModal').style.display = 'none'; }
</script>
</body>
</html>