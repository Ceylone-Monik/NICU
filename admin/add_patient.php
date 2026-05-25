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

        // Generate unique Bed Number: Date+day number (e.g., 20260523-001)
        $today_date = date('Ymd');
        $today_pattern = $today_date . '-%';
        
        $seq_stmt = $pdo->prepare("SELECT bed_number FROM patient_admissions WHERE bed_number LIKE ? ORDER BY bed_number DESC LIMIT 1");
        $seq_stmt->execute([$today_pattern]);
        $last_bed = $seq_stmt->fetchColumn();
        
        $next_seq = 1;
        if ($last_bed) {
            $parts = explode('-', $last_bed);
            if (count($parts) > 1) {
                $next_seq = (int)$parts[1] + 1;
            }
        }
        $bed_number = $today_date . '-' . str_pad($next_seq, 3, '0', STR_PAD_LEFT);

        // 2. ROUTE ACTIONS: EXISTING CHILD vs NEW PREGNANCY ADMISSION
        if ($_POST['action_type'] === 'existing_child_treatment') {
            $baby_id = (int)$_POST['selected_baby_id'];
            
            // Extract the mother's current pregnancy profile parameters to duplicate in the admissions record
            $m_details_stmt = $pdo->prepare("SELECT clinic_book_no, lmp_date, edd_date, gravida, para, pregnancy_risk_factors FROM patients WHERE id = ?");
            $m_details_stmt->execute([$mother_id]);
            $m_details = $m_details_stmt->fetch(PDO::FETCH_ASSOC);

            // Create a new stay admission record for this returning treatment stay
            $ins_adm = $pdo->prepare("INSERT INTO patient_admissions (patient_id, clinic_book_no, lmp_date, edd_date, gravida, para, pregnancy_risk_factors, bed_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins_adm->execute([
                $mother_id,
                $m_details['clinic_book_no'] ?: null,
                $m_details['lmp_date'] ?: null,
                $m_details['edd_date'] ?: null,
                $m_details['gravida'] !== null ? (int)$m_details['gravida'] : null,
                $m_details['para'] !== null ? (int)$m_details['para'] : null,
                $m_details['pregnancy_risk_factors'] ?: null,
                $bed_number
            ]);

            // Re-activate child status and update bed number inside the nursery grid for treatment sessions
            $up_b = $pdo->prepare("UPDATE babies SET status = 'Active', ward_name = ?, recommendation_status = 'None', admission_bed_number = ? WHERE baby_id = ?");
            $up_b->execute([$_POST['treatment_ward'], $bed_number, $baby_id]);

            // Register movement log timeline trail row
            $log = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward, bed_number) VALUES (?, 'Admission', 'Outpatient', ?, ?)");
            $log->execute([$baby_id, $_POST['treatment_ward'], $bed_number]);
        } else {
            // New Pregnancy Admission: Just log maternal parameters without an active baby entry!
            $ins_adm = $pdo->prepare("INSERT INTO patient_admissions (patient_id, clinic_book_no, lmp_date, edd_date, gravida, para, pregnancy_risk_factors, bed_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $ins_adm->execute([
                $mother_id,
                !empty($_POST['clinic_book_no']) ? $_POST['clinic_book_no'] : null,
                !empty($_POST['lmp_date']) ? $_POST['lmp_date'] : null,
                !empty($_POST['edd_date']) ? $_POST['edd_date'] : null,
                (isset($_POST['gravida']) && $_POST['gravida'] !== '') ? (int)$_POST['gravida'] : null,
                (isset($_POST['para']) && $_POST['para'] !== '') ? (int)$_POST['para'] : null,
                !empty($_POST['risk_factors']) ? $_POST['risk_factors'] : null,
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    
    <script>
        (function() {
            const theme = localStorage.getItem('theme') || 'light';
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    
    <style type="text/tailwindcss">
    :root {
      --card: #f7f8f8;
      --ring: #1da1f2;
      --input: #f7f9fa;
      --muted: #E5E5E6;
      --accent: #E3ECF6;
      --border: #e1eaef;
      --radius: 1.3rem;
      --chart-1: #1e9df1;
      --chart-2: #00b87a;
      --chart-3: #f7b928;
      --chart-4: #17bf63;
      --chart-5: #e0245e;
      --popover: #ffffff;
      --primary: #1e9df1;
      --sidebar: #f7f8f8;
      --font-mono: Menlo, monospace;
      --font-sans: 'Open Sans', sans-serif;
      --secondary: #0f1419;
      --background: #ffffff;
      --font-serif: Georgia, serif;
      --foreground: #0f1419;
      --destructive: #f4212e;
      --shadow-blur: 0px;
      --shadow-color: rgba(29,161,242,0.15);
      --sidebar-ring: #1da1f2;
      --shadow-spread: 0px;
      --shadow-opacity: 0;
      --sidebar-accent: #E3ECF6;
      --sidebar-border: #e1e8ed;
      --card-foreground: #0f1419;
      --shadow-offset-x: 0px;
      --shadow-offset-y: 2px;
      --sidebar-primary: #1e9df1;
      --muted-foreground: #0f1419;
      --accent-foreground: #1e9df1;
      --popover-foreground: #0f1419;
      --primary-foreground: #ffffff;
      --sidebar-foreground: #0f1419;
      --secondary-foreground: #ffffff;
      --destructive-foreground: #ffffff;
      --sidebar-accent-foreground: #1e9df1;
      --sidebar-primary-foreground: #ffffff;
    }

    .dark {
      --card: #17181c;
      --ring: #1da1f2;
      --input: #22303c;
      --muted: #181818;
      --accent: #061622;
      --border: #242628;
      --chart-1: #1e9df1;
      --chart-2: #00b87a;
      --chart-3: #f7b928;
      --chart-4: #17bf63;
      --chart-5: #e0245e;
      --popover: #000000;
      --primary: #1c9cf0;
      --sidebar: #17181c;
      --secondary: #f0f3f4;
      --background: #000000;
      --foreground: #e7e9ea;
      --destructive: #f4212e;
      --shadow-color: rgba(29,161,242,0.25);
      --sidebar-ring: #1da1f2;
      --sidebar-accent: #061622;
      --sidebar-border: #38444d;
      --card-foreground: #d9d9d9;
      --sidebar-primary: #1da1f2;
      --muted-foreground: #72767a;
      --accent-foreground: #1c9cf0;
      --popover-foreground: #e7e9ea;
      --primary-foreground: #ffffff;
      --sidebar-foreground: #d9d9d9;
      --secondary-foreground: #0f1419;
      --destructive-foreground: #ffffff;
      --sidebar-accent-foreground: #1c9cf0;
      --sidebar-primary-foreground: #ffffff;
    }

    @theme inline {
      --color-card: var(--card);
      --color-ring: var(--ring);
      --color-input: var(--input);
      --color-muted: var(--muted);
      --color-accent: var(--accent);
      --color-border: var(--border);
      --color-radius: var(--radius);
      --color-chart-1: var(--chart-1);
      --color-chart-2: var(--chart-2);
      --color-chart-3: var(--chart-3);
      --color-chart-4: var(--chart-4);
      --color-chart-5: var(--chart-5);
      --color-popover: var(--popover);
      --color-primary: var(--primary);
      --color-sidebar: var(--sidebar);
      --color-font-mono: var(--font-mono);
      --color-font-sans: var(--font-sans);
      --color-secondary: var(--secondary);
      --color-background: var(--background);
      --color-font-serif: var(--font-serif);
      --color-foreground: var(--foreground);
      --color-destructive: var(--destructive);
      --color-shadow-blur: var(--shadow-blur);
      --color-shadow-color: var(--shadow-color);
      --color-sidebar-ring: var(--sidebar-ring);
      --color-shadow-spread: var(--shadow-spread);
      --color-shadow-opacity: var(--shadow-opacity);
      --color-sidebar-accent: var(--sidebar-accent);
      --color-sidebar-border: var(--sidebar-border);
      --color-card-foreground: var(--card-foreground);
      --color-shadow-offset-x: var(--shadow-offset-x);
      --color-shadow-offset-y: var(--shadow-offset-y);
      --color-sidebar-primary: var(--sidebar-primary);
      --color-muted-foreground: var(--muted-foreground);
      --color-accent-foreground: var(--accent-foreground);
      --color-popover-foreground: var(--popover-foreground);
      --color-primary-foreground: var(--primary-foreground);
      --color-sidebar-foreground: var(--sidebar-foreground);
      --color-secondary-foreground: var(--secondary-foreground);
      --color-destructive-foreground: var(--destructive-foreground);
      --color-sidebar-accent-foreground: var(--sidebar-accent-foreground);
      --color-sidebar-primary-foreground: var(--sidebar-primary-foreground);
    }

    @custom-variant dark (&:where(.dark, .dark *));

    body {
        font-family: var(--font-sans);
    }
    </style>
</head>
<body class="min-h-screen bg-background text-foreground flex flex-col md:flex-row transition-colors duration-200">

<div class="flex-1 flex flex-col md:flex-row w-full">
    <!-- Sidebar -->
    <div class="w-full md:w-72 bg-sidebar border-b md:border-b-0 md:border-r border-sidebar-border text-sidebar-foreground p-6 flex flex-col justify-between flex-shrink-0 transition-colors duration-200">
        <div>
            <div class="flex flex-col items-center text-center pb-6 border-b border-sidebar-border mb-6">
                <div class="w-16 h-16 rounded-full bg-accent text-accent-foreground flex items-center justify-center text-3xl mb-3 shadow-inner">
                    🧑‍💼
                </div>
                <h3 class="text-base font-bold text-foreground">Pawan</h3>
                <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-1">Administrator</p>
            </div>
            
            <nav class="space-y-1">
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-dashboard" onclick="window.location.href='dashboard.php?tab=dashboard'">
                    <i class="fas fa-home w-5 text-center text-primary"></i> <span>Admin Dashboard</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-wards" onclick="window.location.href='wards.php'">
                    <i class="fas fa-procedures w-5 text-center text-primary"></i> <span>Wards (Infants)</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-staff" onclick="window.location.href='dashboard.php?tab=staff'">
                    <i class="fas fa-users-cog w-5 text-center text-primary"></i> <span>Staff Management</span>
                </div>
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="btn-patients" onclick="window.location.href='dashboard.php?tab=patients'">
                    <i class="fas fa-hospital-user w-5 text-center"></i> <span>Patient Records</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-reports" onclick="window.location.href='dashboard.php?tab=reports'">
                    <i class="fas fa-chart-pie w-5 text-center text-primary"></i> <span>Reports</span>
                </div>
            </nav>
        </div>

        <div>
            <!-- Theme Toggler (Pill Switcher) -->
            <div class="mt-6 pt-6 border-t border-sidebar-border flex flex-col gap-2">
                <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Theme Mode</span>
                <div class="relative flex items-center bg-muted p-1 rounded-full w-full select-none">
                    <div id="theme-indicator" class="absolute top-1 bottom-1 left-1 rounded-full bg-card shadow-sm transition-all duration-300 w-[calc(50%-4px)]"></div>
                    <button onclick="setTheme('light')" class="z-10 flex-1 flex items-center justify-center gap-2 py-1.5 text-xs font-semibold transition-colors duration-200 text-foreground" id="theme-btn-light">
                        <span>☀️ Light</span>
                    </button>
                    <button onclick="setTheme('dark')" class="z-10 flex-1 flex items-center justify-center gap-2 py-1.5 text-xs font-medium transition-colors duration-200 text-muted-foreground" id="theme-btn-dark">
                        <span>🌙 Dark</span>
                    </button>
                </div>
            </div>

            <!-- Logout -->
            <div class="mt-4 pt-4 border-t border-sidebar-border">
                <a href="../logout.php" class="flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 text-destructive hover:bg-destructive/10">
                    <i class="fas fa-door-open w-5 text-center"></i> <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-grow flex flex-col p-6 md:p-10 overflow-y-auto">
        <a href="dashboard.php?tab=patients" class="inline-flex items-center gap-2 text-primary hover:underline font-bold text-sm mb-6 transition-all"><i class="fas fa-arrow-left"></i> Return to Patients Index</a>

        <div class="border-b border-border pb-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Patient Intake Enrollment Desk</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Type identity details to cross-match entries or process fresh clinical additions.</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <?php if(!empty($error)): ?>
            <div class="p-4 bg-destructive/10 text-destructive border border-destructive/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-exclamation-circle text-base"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div id="autofill_badge" class="hidden p-4 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-radius mb-6 text-sm font-semibold items-center gap-3">
            <i class="fas fa-id-card-alt text-base"></i> 
            <span>Returning Mother Profile Verified! Core profile parameters imported.</span>
            <button type="button" onclick="resetFormFields()" class="bg-transparent border-none text-destructive ml-auto font-bold cursor-pointer text-xs uppercase hover:underline">Clear</button>
        </div>

        <div class="card bg-card border border-border rounded-radius p-8 shadow-sm hover:shadow-md transition-all duration-200 max-w-3xl">
            <form method="POST" id="mainIntakeForm" class="space-y-6">
                <input type="hidden" name="selected_mother_id" id="hidden_mother_id">
                <input type="hidden" name="selected_baby_id" id="hidden_baby_id">
                <input type="hidden" name="action_type" id="hidden_action_type" value="new_delivery">

                <!-- Step 1 Header -->
                <div class="text-xs font-bold text-primary border-b border-border pb-2 uppercase tracking-wider">📋 STEP 1: MATERNAL DEMOGRAPHICS INDEX</div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="relative">
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">National Identity Card (NIC Number)</label>
                        <input type="text" name="nic" id="field_nic" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Type NIC profile lookup target..." required autocomplete="off" oninput="fetchMaternalSuggestions(this.value)">
                        <div id="suggestions_box" class="absolute left-0 right-0 mt-2 bg-popover border border-primary/45 rounded-lg shadow-xl max-h-60 overflow-y-auto hidden z-50 divide-y divide-border"></div>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Mother Full Name</label>
                        <input type="text" name="full_name" id="field_name" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Full legal name entry" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Date of Birth</label>
                        <input type="date" name="dob" id="field_dob" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Phone Number Contact</label>
                        <input type="text" name="phone" id="field_phone" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Contact link input" required>
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Blood Group Specification</label>
                    <select name="blood_group" id="field_blood" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm cursor-pointer">
                        <option value="Unknown">Unknown</option>
                        <option value="A+">A+</option><option value="A-">A-</option>
                        <option value="B+">B+</option><option value="B-">B-</option>
                        <option value="AB+">AB+</option><option value="AB-">AB-</option>
                        <option value="O+">O+</option><option value="O-">O-</option>
                    </select>
                </div>

                <!-- Step 1.5 Returning Mother Pathway -->
                <div id="returning_mother_workflow" class="hidden p-5 bg-primary/5 border border-dashed border-primary/20 rounded-xl space-y-4">
                    <h4 class="text-xs font-bold text-primary uppercase tracking-wider flex items-center gap-1.5"><i class="fas fa-exchange-alt"></i> Patient Match Identified: Select Operational Pathway</h4>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-card border-2 border-primary rounded-xl p-5 cursor-pointer text-center hover:bg-primary/5 transition-all" id="card_route_new" onclick="setWorkflowRoute('new_delivery')">
                            <i class="fas fa-baby-carriage text-2xl text-primary mb-2"></i>
                            <h4 class="text-sm font-bold text-foreground mb-1">New Pregnancy Admission</h4>
                            <p class="text-[11px] text-muted-foreground leading-normal">Register a subsequent newborn delivery case under this profile loop.</p>
                        </div>
                        <div class="bg-card border border-border rounded-xl p-5 cursor-pointer text-center hover:border-primary/50 hover:bg-primary/5 transition-all" id="card_route_return" onclick="setWorkflowRoute('existing_child')">
                            <i class="fas fa-prescription-bottle-alt text-2xl text-primary mb-2"></i>
                            <h4 class="text-sm font-bold text-foreground mb-1">Returning Child Treatment</h4>
                            <p class="text-[11px] text-muted-foreground leading-normal">Process follow-up clinic checkups for an existing infant record.</p>
                        </div>
                    </div>

                    <div id="returning_babies_area" class="hidden flex-col gap-3">
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Select Target Child Patient Profile</label>
                        <div id="babies_tiles_render_box" class="space-y-2"></div>
                        
                        <div class="mt-4">
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Assign Treatment Admission Destination Ward</label>
                            <select name="treatment_ward" id="field_treatment_ward" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs cursor-pointer">
                                <option value="Normal">Normal Ward</option>
                                <option value="Critical">Critical Ward</option>
                                <option value="Other">Other Ward</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2 Pregnancy Admission -->
                <div id="new_pregnancy_section" class="space-y-4">
                    <div class="text-xs font-bold text-primary border-b border-border pb-2 uppercase tracking-wider">📑 STEP 2: CLINICAL METRICS ADMISSION ROSTER</div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Clinic Book Reference Number</label>
                            <input type="text" name="clinic_book_no" id="f_book" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="e.g., MOH/MAL/2026/115">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Last Menstrual Period (LMP)</label>
                            <input type="date" name="lmp_date" id="f_lmp" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Expected Delivery Window (EDD)</label>
                            <input type="date" name="edd_date" id="f_edd" readonly class="w-full bg-input border border-border text-muted-foreground rounded-lg p-2.5 outline-none text-sm opacity-60 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Gravida (Total Pregnancies)</label>
                            <input type="number" name="gravida" id="f_g" min="1" value="1" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Para (Viable Births History)</label>
                            <input type="number" name="para" id="f_p" min="0" value="0" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">High Risk Conditions checklist notes</label>
                            <input type="text" name="risk_factors" id="f_risk" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="e.g., Gestational Diabetes, None">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg text-sm shadow-sm transition-all cursor-pointer border border-primary/10 uppercase tracking-wider flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle text-base"></i> Commit Admission Registry Process
                </button>
            </form>
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
                box.classList.remove('hidden');
                data.forEach(mother => {
                    let div = document.createElement('div');
                    div.className = "p-3 cursor-pointer text-left hover:bg-primary/10 transition-colors border-b border-border/50";
                    div.innerHTML = `<strong class="text-sm font-semibold text-primary block">${mother.full_name}</strong><small class="text-[11px] text-muted-foreground">NIC: ${mother.nic} | Phone: ${mother.phone}</small>`;
                    div.onclick = function() { selectMotherProfile(mother); };
                    box.appendChild(div);
                });
            } else { box.style.display = 'none'; box.classList.add('hidden'); }
        });
}

function selectMotherProfile(m) {
    document.getElementById('suggestions_box').style.display = 'none';
    document.getElementById('suggestions_box').classList.add('hidden');
    
    document.getElementById('hidden_mother_id').value = m.id;
    document.getElementById('field_nic').value = m.nic;
    document.getElementById('field_name').value = m.full_name;
    document.getElementById('field_name').readOnly = true;
    document.getElementById('field_name').classList.add('opacity-60', 'cursor-not-allowed');
    
    document.getElementById('field_dob').value = m.dob;
    document.getElementById('field_dob').readOnly = true;
    document.getElementById('field_dob').classList.add('opacity-60', 'cursor-not-allowed');
    
    document.getElementById('field_phone').value = m.phone;
    document.getElementById('field_blood').value = m.blood_group;

    associatedBabiesArray = m.babies || [];
    
    document.getElementById('returning_mother_workflow').style.display = 'block';
    document.getElementById('returning_mother_workflow').classList.remove('hidden');
    document.getElementById('autofill_badge').style.display = 'flex';
    document.getElementById('autofill_badge').classList.remove('hidden');
    setWorkflowRoute('new_delivery');
}

function setWorkflowRoute(type) {
    document.getElementById('hidden_action_type').value = (type === 'new_delivery') ? 'new_delivery' : 'existing_child_treatment';
    
    const cardRouteNew = document.getElementById('card_route_new');
    const cardRouteReturn = document.getElementById('card_route_return');
    
    cardRouteNew.classList.remove('border-primary', 'bg-primary/5');
    cardRouteNew.classList.add('border-border');
    cardRouteReturn.classList.remove('border-primary', 'bg-primary/5');
    cardRouteReturn.classList.add('border-border');

    const formPregnancySection = document.getElementById('new_pregnancy_section');
    const returnBabiesSection = document.getElementById('returning_babies_area');

    if(type === 'new_delivery') {
        cardRouteNew.classList.add('border-primary', 'bg-primary/5');
        cardRouteNew.classList.remove('border-border');
        formPregnancySection.style.display = 'block';
        formPregnancySection.classList.remove('hidden');
        returnBabiesSection.style.display = 'none';
        returnBabiesSection.classList.add('hidden');
        toggleRequiredFields(true);
    } else {
        cardRouteReturn.classList.add('border-primary', 'bg-primary/5');
        cardRouteReturn.classList.remove('border-border');
        formPregnancySection.style.display = 'none';
        formPregnancySection.classList.add('hidden');
        returnBabiesSection.style.display = 'flex';
        returnBabiesSection.classList.remove('hidden');
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
        box.innerHTML = "<div class='text-yellow-600 dark:text-yellow-400 text-xs italic p-3'>No previous child records found under this patient file profile template.</div>";
        return;
    }

    associatedBabiesArray.forEach(baby => {
        let tile = document.createElement('div');
        tile.className = "flex items-center justify-between bg-primary/5 border border-primary/20 p-3 rounded-lg cursor-pointer transition-all hover:bg-primary/10";
        tile.id = "baby_tile_" + baby.baby_id;
        tile.innerHTML = `
            <div>
                <strong class="text-sm font-semibold text-foreground">${baby.baby_name}</strong><br>
                <small class="text-[11px] text-muted-foreground">Gender: ${baby.baby_gender} | Born: ${baby.birth_date}</small>
            </div>
            <span class="px-2 py-0.5 rounded bg-primary/10 text-primary border border-primary/20 text-[10px] font-semibold">Status: ${baby.status}</span>
        `;
        tile.onclick = function() { chooseBabyForTreatment(baby.baby_id); };
        box.appendChild(tile);
    });
}

function chooseBabyForTreatment(id) {
    document.getElementById('hidden_baby_id').value = id;
    document.querySelectorAll('[id^="baby_tile_"]').forEach(t => {
        t.classList.remove('border-primary', 'bg-primary/10');
        t.classList.add('border-primary/20', 'bg-primary/5');
    });
    const chosenTile = document.getElementById('baby_tile_' + id);
    if(chosenTile) {
        chosenTile.classList.add('border-primary', 'bg-primary/10');
        chosenTile.classList.remove('border-primary/20', 'bg-primary/5');
    }
}

function resetFormFields() {
    document.getElementById('mainIntakeForm').reset();
    const fieldName = document.getElementById('field_name');
    fieldName.readOnly = false;
    fieldName.classList.remove('opacity-60', 'cursor-not-allowed');
    const fieldDob = document.getElementById('field_dob');
    fieldDob.readOnly = false;
    fieldDob.classList.remove('opacity-60', 'cursor-not-allowed');
    
    document.getElementById('returning_mother_workflow').style.display = 'none';
    document.getElementById('returning_mother_workflow').classList.add('hidden');
    document.getElementById('autofill_badge').style.display = 'none';
    document.getElementById('autofill_badge').classList.add('hidden');
}

function setTheme(mode) {
    const themeIndicator = document.getElementById('theme-indicator');
    const btnLight = document.getElementById('theme-btn-light');
    const btnDark = document.getElementById('theme-btn-dark');

    if (mode === 'dark') {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        if(themeIndicator) themeIndicator.style.left = 'calc(50% + 2px)';
        if(btnDark) {
            btnDark.classList.remove('text-muted-foreground', 'font-medium');
            btnDark.classList.add('text-foreground', 'font-semibold');
        }
        if(btnLight) {
            btnLight.classList.remove('text-foreground', 'font-semibold');
            btnLight.classList.add('text-muted-foreground', 'font-medium');
        }
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        if(themeIndicator) themeIndicator.style.left = '4px';
        if(btnLight) {
            btnLight.classList.remove('text-muted-foreground', 'font-medium');
            btnLight.classList.add('text-foreground', 'font-semibold');
        }
        if(btnDark) {
            btnDark.classList.remove('text-foreground', 'font-semibold');
            btnDark.classList.add('text-muted-foreground', 'font-medium');
        }
    }
}

// Initialise theme toggler position on page load
document.addEventListener('DOMContentLoaded', () => {
    const activeTheme = localStorage.getItem('theme') || 'light';
    setTheme(activeTheme);
});
</script>
</body>
</html>