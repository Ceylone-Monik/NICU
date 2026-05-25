<?php
session_start();
require_once '../config/db.php';

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$message = "";
$error = "";

// Handle checking if mother exists via background AJAX request optimization
if (isset($_GET['check_nic'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE nic = ? LIMIT 1");
    $stmt->execute([$_GET['check_nic']]);
    $existing_mother = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($existing_mother ? $existing_mother : ['exists' => false]);
    exit();
}

// Handle Form Submission
if (isset($_POST['action_type'])) {
    try {
        $pdo->beginTransaction();
        
        $mother_id = !empty($_POST['selected_mother_id']) ? (int)$_POST['selected_mother_id'] : null;
        $nic = trim($_POST['nic']);
        
        // 1. MOTHER PROFILE PERSISTENCE OR CREATION
        if (!$mother_id) {
            $check_m = $pdo->prepare("SELECT id FROM patients WHERE nic = ?");
            $check_m->execute([$nic]);
            $mother_id = $check_m->fetchColumn();
            
            if (!$mother_id) {
                $ins_m = $pdo->prepare("INSERT INTO patients (full_name, dob, nic, phone, blood_group, patient_type, is_active_inpatient, gender, clinic_book_no, lmp_date, edd_date, gravida, para, pregnancy_risk_factors) VALUES (?, ?, ?, ?, ?, 'Pregnant', 1, 'Female', ?, ?, ?, ?, ?, ?)");
                $ins_m->execute([
                    $_POST['full_name'],
                    $_POST['dob'],
                    $nic,
                    $_POST['phone'],
                    $_POST['blood_group'],
                    !empty($_POST['clinic_book_no']) ? $_POST['clinic_book_no'] : null,
                    !empty($_POST['lmp_date']) ? $_POST['lmp_date'] : null,
                    !empty($_POST['edd_date']) ? $_POST['edd_date'] : null,
                    (isset($_POST['gravida']) && $_POST['gravida'] !== '') ? (int)$_POST['gravida'] : null,
                    (isset($_POST['para']) && $_POST['para'] !== '') ? (int)$_POST['para'] : null,
                    !empty($_POST['risk_factors']) ? $_POST['risk_factors'] : null
                ]);
                $mother_id = $pdo->lastInsertId();
            }
        } else {
            if ($_POST['action_type'] === 'new_delivery') {
                $up_m = $pdo->prepare("UPDATE patients SET phone = ?, blood_group = ?, gender = 'Female', clinic_book_no = ?, lmp_date = ?, edd_date = ?, gravida = ?, para = ?, pregnancy_risk_factors = ? WHERE id = ?");
                $up_m->execute([
                    $_POST['phone'],
                    $_POST['blood_group'],
                    !empty($_POST['clinic_book_no']) ? $_POST['clinic_book_no'] : null,
                    !empty($_POST['lmp_date']) ? $_POST['lmp_date'] : null,
                    !empty($_POST['edd_date']) ? $_POST['edd_date'] : null,
                    (isset($_POST['gravida']) && $_POST['gravida'] !== '') ? (int)$_POST['gravida'] : null,
                    (isset($_POST['para']) && $_POST['para'] !== '') ? (int)$_POST['para'] : null,
                    !empty($_POST['risk_factors']) ? $_POST['risk_factors'] : null,
                    $mother_id
                ]);
            } else {
                $up_m = $pdo->prepare("UPDATE patients SET phone = ?, blood_group = ?, gender = 'Female' WHERE id = ?");
                $up_m->execute([$_POST['phone'], $_POST['blood_group'], $mother_id]);
            }
        }

        // 2. ROUTE ACTIONS: EXISTING CHILD vs NEW PREGNANCY ADMISSION
        if ($_POST['action_type'] === 'existing_child_treatment') {
            $baby_id = (int)$_POST['selected_baby_id'];
            
            // Re-activate child status inside the nursery grid for treatment sessions
            $up_b = $pdo->prepare("UPDATE babies SET status = 'Active', ward_name = ?, recommendation_status = 'None' WHERE baby_id = ?");
            $up_b->execute([$_POST['treatment_ward'], $baby_id]);

            // Register movement log timeline trail row
            $log = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward) VALUES (?, 'Admission', 'Outpatient', ?)");
            $log->execute([$baby_id, $_POST['treatment_ward']]);
        } else {
            // New Pregnancy Admission: Just log maternal parameters without an active baby entry!
            
            // Generate unique Bed Number: Date+day number (e.g., 20260523+001)
            $today_date = date('Ymd');
            $today_pattern = $today_date . '+%';
            
            $seq_stmt = $pdo->prepare("SELECT bed_number FROM patient_admissions WHERE bed_number LIKE ? ORDER BY bed_number DESC LIMIT 1");
            $seq_stmt->execute([$today_pattern]);
            $last_bed = $seq_stmt->fetchColumn();
            
            $next_seq = 1;
            if ($last_bed) {
                $parts = explode('+', $last_bed);
                if (count($parts) > 1) {
                    $next_seq = (int)$parts[1] + 1;
                }
            }
            $bed_number = $today_date . '+' . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

            $ins_adm = $pdo->prepare("INSERT INTO patient_admissions (patient_id, clinic_book_no, lmp_date, edd_date, gravida, para, pregnancy_risk_factors, bed_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins_adm->execute([
                $mother_id,
                $_POST['clinic_book_no'],
                $_POST['lmp_date'],
                $_POST['edd_date'],
                $_POST['gravida'],
                $_POST['para'],
                $_POST['risk_factors'],
                $bed_number
            ]);
        }

        $pdo->commit();
        header("Location: dashboard.php?tab=patients&msg=Success");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Transaction Aborted: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intake & Admission Desk</title>
    <link rel="stylesheet" href="../assets/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admission-container { display: flex; justify-content: center; align-items: center; padding: 30px 20px; }
        .admission-card { background: rgba(30, 30, 45, 0.95); border: 1px solid rgba(255, 255, 255, 0.1); width: 100%; max-width: 800px; padding: 35px; border-radius: 24px; color: white; box-shadow: 0 15px 35px rgba(0,0,0,0.4); position: relative; }
        .section-header { border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; margin-bottom: 20px; margin-top: 30px; color: #ff0080; font-weight: bold; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-row-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .input-block { display: flex; flex-direction: column; gap: 5px; text-align: left; position: relative; }
        .input-block label { color: #aaa; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .input-block input, .input-block select { width: 100%; padding: 12px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.15); color: white; font-size: 14px; outline: none; }
        .input-block input:focus, .input-block select:focus { border-color: #00ff96; }
        
        .suggestions-dropdown { position: absolute; top: 100%; left: 0; width: 100%; background: #1e1e2d; border: 1px solid rgba(0,255,150,0.4); border-radius: 8px; z-index: 5000; max-height: 250px; overflow-y: auto; display: none; box-shadow: 0 10px 25px rgba(0,0,0,0.5); margin-top: 5px; }
        .suggestion-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: left; }
        .suggestion-item:hover { background: rgba(0, 255, 150, 0.1); }
        .suggestion-item strong { color: #00ff96; display: block; font-size: 14px; }
        .suggestion-item small { color: #aaa; font-size: 11px; }

        .workflow-options-box { display: none; margin-top: 20px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.15); padding: 20px; border-radius: 12px; }
        .routing-cards-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .route-selection-card { background: rgba(255,255,255,0.03); border: 2px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 20px; cursor: pointer; text-align: center; transition: 0.2s; }
        .route-selection-card:hover { border-color: #00ff96; background: rgba(0,255,150,0.02); }
        .route-selection-card.selected { border-color: #ff0080; background: rgba(255,0,128,0.04); }
        .route-selection-card i { font-size: 30px; margin-bottom: 10px; color: #ff0080; }
        .route-selection-card h4 { margin: 0 0 5px 0; font-size: 15px; color: white; }
        .route-selection-card p { margin: 0; font-size: 12px; color: #aaa; }

        .returning-babies-list { display: none; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .baby-select-tile { display: flex; align-items: center; justify-content: space-between; background: rgba(100,200,255,0.05); border: 1px solid rgba(100,200,255,0.2); padding: 12px 20px; border-radius: 8px; cursor: pointer; }
        .baby-select-tile.chosen { border-color: #00ff96; background: rgba(0,255,150,0.08); }
        .autofill-indicator { display: none; background: rgba(0, 255, 150, 0.1); border: 1px solid #00ff96; color: #00ff96; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: bold; margin-bottom: 20px; align-items: center; gap: 10px; }
        
        #new_pregnancy_section { display: block; }
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
        <div class="admission-container">
            <div class="admission-card">
                <h2 style="color:#00ff96; margin:0 0 5px 0;"><i class="fas fa-hospital-user"></i> Patient Intake Enrollment Desk</h2>
                <p style="color:#aaa; font-size:13px; margin-bottom:25px;">Type identity details to cross-match entries or process fresh clinical additions.</p>

                <?php if(!empty($error)): ?><div style="padding:12px; background:rgba(255,71,87,0.1); color:#ff4757; border:1px solid #ff4757; border-radius:8px; margin-bottom:20px; font-size:13px; font-weight:bold;"><?php echo $error; ?></div><?php endif; ?>

                <div id="autofill_badge" class="autofill-indicator">
                    <i class="fas fa-id-card-alt"></i> <span>Returning Mother Profile Verified! Core profile parameters imported.</span>
                    <button type="button" onclick="resetFormFields()" style="background:transparent; border:none; color:#ff4757; margin-left:auto; font-weight:bold; cursor:pointer;">Clear</button>
                </div>

                <form method="POST" id="mainIntakeForm">
                    <input type="hidden" name="selected_mother_id" id="hidden_mother_id">
                    <input type="hidden" name="selected_baby_id" id="hidden_baby_id">
                    <input type="hidden" name="action_type" id="hidden_action_type" value="new_delivery">

                    <div class="section-header" style="margin-top:0;">📋 STEP 1: MATERNAL DEMOGRAPHICS INDEX</div>
                    <div class="input-row-grid">
                        <div class="input-block">
                            <label>National Identity Card (NIC Number)</label>
                            <input type="text" name="nic" id="field_nic" placeholder="Type NIC profile lookup target..." required autocomplete="off" oninput="fetchMaternalSuggestions(this.value)">
                            <div id="suggestions_box" class="suggestions-dropdown"></div>
                        </div>
                        <div class="input-block">
                            <label>Mother Full Name</label>
                            <input type="text" name="full_name" id="field_name" placeholder="Full legal name entry" required>
                        </div>
                    </div>
                    <div class="input-row-grid">
                        <div class="input-block">
                            <label>Date of Birth</label>
                            <input type="date" name="dob" id="field_dob" required>
                        </div>
                        <div class="input-block">
                            <label>Phone Number Contact</label>
                            <input type="text" name="phone" id="field_phone" placeholder="Contact link input" required>
                        </div>
                    </div>
                    <div class="input-block" style="margin-bottom: 20px;">
                        <label>Blood Group Specification</label>
                        <select name="blood_group" id="field_blood">
                            <option value="Unknown">Unknown</option>
                            <option value="A+">A+</option><option value="A-">A-</option>
                            <option value="B+">B+</option><option value="B-">B-</option>
                            <option value="AB+">AB+</option><option value="AB-">AB-</option>
                            <option value="O+">O+</option><option value="O-">O-</option>
                        </select>
                    </div>

                    <div id="returning_mother_workflow" class="workflow-options-box">
                        <h4 style="color:#00ff96; margin:0 0 15px 0; text-align:left;"><i class="fas fa-exchange-alt"></i> Patient Match Identified: Select Operational Pathway</h4>
                        <div class="routing-cards-grid">
                            <div class="route-selection-card selected" id="card_route_new" onclick="setWorkflowRoute('new_delivery')">
                                <i class="fas fa-baby-carriage"></i>
                                <h4>New Pregnancy Admission</h4>
                                <p>Register a subsequent newborn delivery case under this profile loop.</p>
                            </div>
                            <div class="route-selection-card" id="card_route_return" onclick="setWorkflowRoute('existing_child')">
                                <i class="fas fa-prescription-bottle-alt"></i>
                                <h4>Returning Child Treatment</h4>
                                <p>Process follow-up clinic checkups for an existing infant record.</p>
                            </div>
                        </div>

                        <div id="returning_babies_area" class="returning-babies-list">
                            <label style="color:#aaa; font-size:12px; font-weight:600; display:block; text-align:left; margin-bottom:5px;">Select Target Child Patient Profile</label>
                            <div id="babies_tiles_render_box"></div>
                            
                            <div class="input-block" style="margin-top:15px;">
                                <label>Assign Treatment Admission Destination Ward</label>
                                <select name="treatment_ward" id="field_treatment_ward">
                                    <option value="Normal">Normal Ward</option>
                                    <option value="Critical">Critical Ward</option>
                                    <option value="Other">Other Ward</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="new_pregnancy_section">
                        <div class="section-header">📑 STEP 2: CLINICAL METRICS ADMISSION ROSTER</div>
                        <div class="input-row-grid">
                            <div class="input-block"><label>Clinic Book Reference Number</label><input type="text" name="clinic_book_no" id="f_book" placeholder="e.g., MOH/MAL/2026/115"></div>
                            <div class="input-block"><label>Last Menstrual Period (LMP)</label><input type="date" name="lmp_date" id="f_lmp"></div>
                        </div>
                        <div class="input-row-grid">
                            <div class="input-block"><label>Expected Delivery Window (EDD)</label><input type="date" name="edd_date" id="f_edd" readonly style="opacity:0.7; background:rgba(255,255,255,0.02);"></div>
                            <div class="input-block"><label>Gravida (Total Pregnancies)</label><input type="number" name="gravida" id="f_g" min="1" value="1"></div>
                        </div>
                        <div class="input-row-grid">
                            <div class="input-block"><label>Para (Viable Births History)</label><input type="number" name="para" id="f_p" min="0" value="0"></div>
                            <div class="input-block"><label>High Risk Conditions checklist notes</label><input type="text" name="risk_factors" id="f_risk" placeholder="e.g., Gestational Diabetes, None"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn" style="width:100%; margin-top:30px; background:#00ff96; color:#1a1a2e; font-weight:bold; height:50px; font-size:15px; border:none; border-radius:8px; cursor:pointer; text-transform:uppercase; letter-spacing:0.5px;">
                        <i class="fas fa-check-circle"></i> Commit Admission Registry Process
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let associatedBabiesArray = [];

// Automatic EDD calculation from LMP based on Naegele's Rule (LMP + 280 days)
document.addEventListener('DOMContentLoaded', function() {
    const lmpInput = document.getElementById('f_lmp');
    const eddInput = document.getElementById('f_edd');

    if (lmpInput && eddInput) {
        lmpInput.addEventListener('change', function() {
            const lmpValue = this.value;
            if (!lmpValue) return;

            const lmpDate = new Date(lmpValue);
            lmpDate.setDate(lmpDate.getDate() + 280);

            const year = lmpDate.getFullYear();
            const month = String(lmpDate.getMonth() + 1).padStart(2, '0');
            const day = String(lmpDate.getDate()).padStart(2, '0');

            eddInput.value = `${year}-${month}-${day}`;
        });
    }
});

function fetchMaternalSuggestions(val) {
    const box = document.getElementById('suggestions_box');
    if (val.trim().length < 2) { box.style.display = 'none'; return; }

    fetch('search_mother.php?q=' + encodeURIComponent(val))
        .then(response => response.json())
        .then(data => {
            if (data && data.length > 0) {
                box.innerHTML = "";
                box.style.display = 'block';
                data.forEach(mother => {
                    let div = document.createElement('div');
                    div.className = "suggestion-item";
                    div.innerHTML = `<strong>${mother.full_name}</strong><small>NIC: ${mother.nic} | Phone: ${mother.phone}</small>`;
                    div.onclick = function() { selectMotherProfile(mother); };
                    box.appendChild(div);
                });
            } else { box.style.display = 'none'; }
        });
}

function selectMotherProfile(m) {
    document.getElementById('suggestions_box').style.display = 'none';
    
    document.getElementById('hidden_mother_id').value = m.id;
    document.getElementById('field_nic').value = m.nic;
    document.getElementById('field_name').value = m.full_name;
    document.getElementById('field_name').readOnly = true;
    document.getElementById('field_name').style.opacity = '0.6';
    
    document.getElementById('field_dob').value = m.dob;
    document.getElementById('field_dob').readOnly = true;
    document.getElementById('field_dob').style.opacity = '0.6';
    
    document.getElementById('field_phone').value = m.phone;
    document.getElementById('field_blood').value = m.blood_group;

    associatedBabiesArray = m.babies || [];
    
    document.getElementById('returning_mother_workflow').style.display = 'block';
    document.getElementById('autofill_badge').style.display = 'flex';
    setWorkflowRoute('new_delivery');
}

function setWorkflowRoute(type) {
    document.getElementById('hidden_action_type').value = (type === 'new_delivery') ? 'new_delivery' : 'existing_child_treatment';
    
    document.getElementById('card_route_new').classList.remove('selected');
    document.getElementById('card_route_return').classList.remove('selected');

    const formPregnancySection = document.getElementById('new_pregnancy_section');
    const returnBabiesSection = document.getElementById('returning_babies_area');

    if(type === 'new_delivery') {
        document.getElementById('card_route_new').classList.add('selected');
        formPregnancySection.style.display = 'block';
        returnBabiesSection.style.display = 'none';
        toggleRequiredFields(true);
    } else {
        document.getElementById('card_route_return').classList.add('selected');
        formPregnancySection.style.display = 'none';
        returnBabiesSection.style.display = 'flex';
        toggleRequiredFields(false);
        renderBabiesSelectionTiles();
    }
}

function toggleRequiredFields(shouldRequire) {
    const fields = ['f_book', 'f_lmp'];
    fields.forEach(id => {
        const field = document.getElementById(id);
        if(field) field.required = shouldRequire;
    });
}

function renderBabiesSelectionTiles() {
    const box = document.getElementById('babies_tiles_render_box');
    box.innerHTML = "";
    
    if(associatedBabiesArray.length === 0) {
        box.innerHTML = "<div style='color:#ffa502; font-size:13px; font-style:italic; padding:10px;'>No previous child records found under this patient file profile template.</div>";
        return;
    }

    associatedBabiesArray.forEach(baby => {
        let tile = document.createElement('div');
        tile.className = "baby-select-tile";
        tile.id = "baby_tile_" + baby.baby_id;
        tile.innerHTML = `
            <div>
                <strong>${baby.baby_name}</strong><br>
                <small style='color:#aaa;'>Gender: ${baby.baby_gender} | Born: ${baby.birth_date}</small>
            </div>
            <span class="role-badge nurse" style="font-size:10px;">Status: ${baby.status}</span>
        `;
        tile.onclick = function() { chooseBabyForTreatment(baby.baby_id); };
        box.appendChild(tile);
    });
}

function chooseBabyForTreatment(id) {
    document.getElementById('hidden_baby_id').value = id;
    document.querySelectorAll('.baby-select-tile').forEach(t => t.classList.remove('chosen'));
    document.getElementById('baby_tile_' + id).classList.add('chosen');
}

function resetFormFields() {
    document.getElementById('mainIntakeForm').reset();
    document.getElementById('field_name').readOnly = false;
    document.getElementById('field_name').style.opacity = '1';
    document.getElementById('field_dob').readOnly = false;
    document.getElementById('field_dob').style.opacity = '1';
    document.getElementById('returning_mother_workflow').style.display = 'none';
    document.getElementById('autofill_badge').style.display = 'none';
}
</script>
</body>
</html>