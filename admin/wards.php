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
    'Normal' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Normal' AND status='Active'")->fetchColumn(),
    'Critical' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Critical' AND status='Active'")->fetchColumn(),
    'Other' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='Other' AND status='Active'")->fetchColumn(),
    'To Discharge' => $pdo->query("SELECT COUNT(*) FROM babies WHERE ward_name='To Discharge' AND status='Active'")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neonatal Wards Overview</title>
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
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="btn-wards">
                    <i class="fas fa-procedures w-5 text-center"></i> <span>Wards (Infants)</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-staff" onclick="window.location.href='dashboard.php?tab=staff'">
                    <i class="fas fa-users-cog w-5 text-center text-primary"></i> <span>Staff Management</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-patients" onclick="window.location.href='dashboard.php?tab=patients'">
                    <i class="fas fa-hospital-user w-5 text-center text-primary"></i> <span>Patient Records</span>
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
        <div class="border-b border-border pb-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Infant Ward Management Portal</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Select a ward compartment card below to execute trace audits.</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <?php if (!empty($alert_message)): ?>
            <div class="p-4 bg-destructive/10 text-destructive border border-destructive/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($alert_message); ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'BabyRegistered'): ?>
            <div class="p-4 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>✔ New baby details saved and assigned to ward stay period folder. Timeline history active.</span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Approved'): ?>
            <div class="p-4 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>✔ Doctor recommendation Approved. Infant successfully transferred.</span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Rejected'): ?>
            <div class="p-4 bg-destructive/10 text-destructive border border-destructive/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-times-circle"></i>
                <span>❌ Doctor recommendation Declined and removed from queue.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending_recommendations)): ?>
            <div class="p-6 bg-yellow-500/5 border border-dashed border-yellow-500/30 rounded-radius mb-8 animate-in fade-in duration-200">
                <h4 class="text-sm font-bold text-yellow-600 dark:text-yellow-400 mb-4 flex items-center gap-2"><i class="fas fa-bell animate-bounce"></i> Pending Doctor Recommendations</h4>
                <div class="space-y-3">
                    <?php foreach ($pending_recommendations as $r): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between bg-card border border-border/60 p-4 rounded-lg gap-4 shadow-sm">
                            <div class="text-sm text-foreground">
                                Recommend moving <strong class="font-bold"><?php echo htmlspecialchars($r['baby_name']); ?></strong> (Mother: <?php echo htmlspecialchars($r['mother_name']); ?>) 
                                from <span class="text-destructive font-bold"><?php echo htmlspecialchars($r['ward_name']); ?> Ward</span> 
                                to <span class="text-primary font-bold"><?php echo htmlspecialchars($r['recommended_ward']); ?> Ward</span>.
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" class="flex gap-2 m-0">
                                    <input type="hidden" name="baby_id" value="<?php echo $r['baby_id']; ?>">
                                    <button type="submit" name="approve_recommendation" class="px-3.5 py-1.5 bg-primary text-primary-foreground font-bold rounded-lg text-xs uppercase cursor-pointer transition-all duration-150 hover:bg-primary/95 shadow-sm">Approve</button>
                                    <button type="submit" name="reject_recommendation" class="px-3.5 py-1.5 bg-destructive text-destructive-foreground font-bold rounded-lg text-xs uppercase cursor-pointer transition-all duration-150 hover:bg-destructive/95 shadow-sm">Reject</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Log Newborn Form -->
        <div class="card bg-card border border-border rounded-radius p-8 shadow-sm hover:shadow-md transition-all duration-200 mb-8">
            <h3 class="text-lg font-bold text-primary mb-6 flex items-center gap-2"><i class="fas fa-baby-carriage"></i> Log Newborn Delivery Admission</h3>
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Mother</label>
                        <select name="mother_id" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs cursor-pointer" required>
                            <option value="" selected disabled>Select Mother...</option>
                            <?php foreach ($mothers as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?> (<?php echo htmlspecialchars($m['clinic_book_no']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Baby Name (Optional)</label>
                        <input type="text" name="baby_name" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs" placeholder="Baby Name (Optional)">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Gender</label>
                        <select name="baby_gender" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs cursor-pointer" required>
                            <option value="" selected disabled>Select Gender...</option>
                            <option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Birth Date / Time</label>
                        <input type="datetime-local" name="birth_date" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Weight (kg)</label>
                        <input type="number" step="0.001" name="weight_kg" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs" placeholder="Weight (kg)" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Condition Description</label>
                        <input type="text" name="condition_notes" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs" placeholder="Condition Description">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Assign Initial Ward</label>
                        <select name="initial_ward" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-xs cursor-pointer" required>
                            <option value="Normal">Assign: Normal Ward</option><option value="Critical">Assign: Critical Ward</option>
                            <option value="Other">Assign: Other Ward</option><option value="To Discharge">Assign: To Discharge</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="register_baby" class="w-full py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg text-sm shadow-sm transition-all cursor-pointer border border-primary/10">✓ SAVE INFANT RECORD & ADMIT TO WARD</button>
            </form>
        </div>

        <!-- Ward compartments grid layout -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Normal Ward Card -->
            <div class="bg-card border-l-4 border-l-emerald-500 border border-border rounded-radius p-6 text-center flex flex-col items-center justify-center gap-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer" onclick="window.location.href='ward_patients.php?ward=Normal'">
                <i class="fas fa-check-circle text-4xl text-emerald-500"></i>
                <p class="text-base font-bold text-foreground uppercase tracking-wide">Normal Ward</p>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20"><?php echo $counts['Normal']; ?> Active Babies</span>
                <button class="px-4 py-2 border border-border hover:bg-emerald-500 hover:text-white hover:border-emerald-500 font-bold rounded-lg text-xs transition-all duration-150 uppercase tracking-wider cursor-pointer">Open Ward Table →</button>
            </div>
            <!-- Critical Ward Card -->
            <div class="bg-card border-l-4 border-l-destructive border border-border rounded-radius p-6 text-center flex flex-col items-center justify-center gap-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer" onclick="window.location.href='ward_patients.php?ward=Critical'">
                <i class="fas fa-heartbeat text-4xl text-destructive"></i>
                <p class="text-base font-bold text-foreground uppercase tracking-wide">Critical Ward</p>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-destructive/10 text-destructive border border-destructive/20"><?php echo $counts['Critical']; ?> Active Babies</span>
                <button class="px-4 py-2 border border-border hover:bg-destructive hover:text-white hover:border-destructive font-bold rounded-lg text-xs transition-all duration-150 uppercase tracking-wider cursor-pointer">Open Ward Table →</button>
            </div>
            <!-- Other Ward Card -->
            <div class="bg-card border-l-4 border-l-blue-500 border border-border rounded-radius p-6 text-center flex flex-col items-center justify-center gap-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer" onclick="window.location.href='ward_patients.php?ward=Other'">
                <i class="fas fa-baby text-4xl text-blue-500"></i>
                <p class="text-base font-bold text-foreground uppercase tracking-wide">Other Ward</p>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20"><?php echo $counts['Other']; ?> Active Babies</span>
                <button class="px-4 py-2 border border-border hover:bg-blue-500 hover:text-white hover:border-blue-500 font-bold rounded-lg text-xs transition-all duration-150 uppercase tracking-wider cursor-pointer">Open Ward Table →</button>
            </div>
            <!-- To Discharge Card -->
            <div class="bg-card border-l-4 border-l-orange-500 border border-border rounded-radius p-6 text-center flex flex-col items-center justify-center gap-4 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer" onclick="window.location.href='ward_patients.php?ward=To Discharge'">
                <i class="fas fa-door-open text-4xl text-orange-500"></i>
                <p class="text-base font-bold text-foreground uppercase tracking-wide">To Discharge</p>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20"><?php echo $counts['To Discharge']; ?> Active Babies</span>
                <button class="px-4 py-2 border border-border hover:bg-orange-500 hover:text-white hover:border-orange-500 font-bold rounded-lg text-xs transition-all duration-150 uppercase tracking-wider cursor-pointer">Open Ward Table →</button>
            </div>
        </div>
    </div>
</div>

<script>
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

    // Initialise toggle position on page load
    document.addEventListener('DOMContentLoaded', () => {
        const activeTheme = localStorage.getItem('theme') || 'light';
        setTheme(activeTheme);
    });
</script>
</body>
</html>