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
        $curr_stmt = $pdo->prepare("SELECT ward_name, admission_bed_number FROM babies WHERE baby_id = ?");
        $curr_stmt->execute([$baby_id]);
        $baby_info = $curr_stmt->fetch(PDO::FETCH_ASSOC);
        $old_ward = $baby_info['ward_name'];
        $bed_number = $baby_info['admission_bed_number'];

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
            $open_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward, bed_number) VALUES (?, 'Discharge', ?, 'Discharged Home', ?)");
            $open_stmt->execute([$baby_id, $old_ward, $bed_number]);

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
                $open_stmt = $pdo->prepare("INSERT INTO patient_movement_logs (baby_id, action_type, from_ward, to_ward, bed_number) VALUES (?, 'Transfer', ?, ?, ?)");
                $open_stmt->execute([$baby_id, $old_ward, $new_ward, $bed_number]);
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

    .nav-item {
        color: var(--sidebar-foreground);
        opacity: 0.8;
        transition: all 0.15s ease;
    }
    .nav-item i {
        color: var(--primary) !important;
        transition: all 0.15s ease;
    }
    .nav-item:hover {
        background-color: var(--sidebar-accent) !important;
        color: var(--sidebar-accent-foreground) !important;
        opacity: 1;
    }
    .nav-item:hover i {
        color: var(--sidebar-accent-foreground) !important;
    }
    .nav-item.active {
        background-color: var(--sidebar-accent) !important;
        color: var(--sidebar-accent-foreground) !important;
        font-weight: 700 !important;
        opacity: 1;
    }
    .nav-item.active i {
        color: var(--sidebar-accent-foreground) !important;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(6px);
        z-index: 50;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }
    .modal-card {
        background-color: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 2.2rem;
        width: 100%;
        max-width: 48rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-height: 90vh;
        overflow-y: auto;
        color: var(--foreground);
    }

    .journey-log-item {
        background-color: var(--input);
        padding: 1rem;
        border-radius: 0.5rem;
        border-left-width: 4px;
        font-size: 13px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        color: var(--foreground);
        border-color: var(--border);
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
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="btn-wards" onclick="window.location.href='wards.php'">
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
        <a href="wards.php" class="inline-flex items-center gap-2 text-primary hover:underline font-bold text-sm mb-6 transition-all"><i class="fas fa-arrow-left"></i> Back to Wards Station Overview</a>

        <div class="border-b border-border pb-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground"><?php echo htmlspecialchars($selected_ward); ?> Ward Patient Directory</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Currently viewing active delivery charts for this cluster location.</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <!-- Ward Navigation Switcher Tabs -->
        <div class="flex items-center gap-2 mb-6 bg-muted p-1 rounded-xl max-w-2xl select-none">
            <?php 
            $ward_tabs = [
                'Normal' => ['label' => 'Normal Ward', 'icon' => 'fa-check-circle', 'colorClass' => 'text-emerald-500'],
                'Critical' => ['label' => 'Critical Ward', 'icon' => 'fa-heartbeat', 'colorClass' => 'text-destructive'],
                'Other' => ['label' => 'Other Ward', 'icon' => 'fa-baby', 'colorClass' => 'text-blue-500'],
                'To Discharge' => ['label' => 'To Discharge', 'icon' => 'fa-door-open', 'colorClass' => 'text-orange-500']
            ];
            foreach ($ward_tabs as $key => $tabInfo):
                $isActiveTab = ($key === $selected_ward);
            ?>
                <a href="ward_patients.php?ward=<?php echo urlencode($key); ?>" 
                   class="flex-1 flex items-center justify-center gap-2 py-2 px-3 text-xs font-semibold rounded-lg transition-all duration-150 <?php echo $isActiveTab ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:bg-card/50 hover:text-foreground'; ?>">
                    <i class="fas <?php echo $tabInfo['icon']; ?> <?php echo $tabInfo['colorClass']; ?>"></i>
                    <span><?php echo $tabInfo['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Success'): ?>
            <div class="p-4 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-check-circle"></i>
                <span>✔ Patient ward localization map records synchronized successfully.</span>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'Discharged'): ?>
            <div class="p-4 bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-door-open"></i>
                <span>🏁 Infant discharged home successfully! Removed from ward view, archived securely.</span>
            </div>
        <?php endif; ?>

        <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
            <?php if (empty($babies)): ?>
                <div class="p-8 text-center text-muted-foreground text-sm">📭 There are currently no active infant records registered in this ward tracking block.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Infant Name</th>
                                <th class="p-4">Mother's Full Name</th>
                                <th class="p-4">Gender</th>
                                <th class="p-4">Birth Date / Time</th>
                                <th class="p-4">Weight</th>
                                <th class="p-4 text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($babies as $b): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150 cursor-pointer" onclick="openUnifiedModal(<?php echo $b['baby_id']; ?>)">
                                <td class="p-4 pl-6 text-sm text-foreground"><strong class="font-bold text-foreground"><?php echo htmlspecialchars($b['baby_name']); ?></strong></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($b['mother_name']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($b['baby_gender']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo date('M d, Y - H:i', strtotime($b['birth_date'])); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo $b['weight_kg']; ?> kg</td>
                                <td class="p-4 text-right pr-6" onclick="event.stopPropagation();">
                                    <?php if ($selected_ward === 'To Discharge'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to permanently discharge this baby home?');" class="m-0">
                                            <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                            <button type="submit" name="discharge_patient" class="px-3.5 py-1.5 bg-destructive hover:bg-destructive/90 text-destructive-foreground font-bold rounded-lg text-xs transition-all duration-150 uppercase tracking-wide cursor-pointer flex items-center gap-1.5 ml-auto">
                                                <i class="fas fa-door-open"></i> Discharge
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="m-0 flex items-center justify-end">
                                            <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                            <select name="ward_name" class="bg-input border border-border text-foreground rounded-lg px-2.5 py-1 text-xs outline-none focus:border-primary cursor-pointer w-full max-w-[140px]" onchange="this.form.submit()">
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
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="unifiedModal" class="modal-overlay">
    <div class="modal-card border-primary/30">
        <div class="flex justify-between items-center border-b border-border pb-4 mb-6">
            <h2 class="text-lg font-bold text-primary flex items-center gap-2"><i class="fas fa-hospital-user"></i> Clinical Case Overview</h2>
            <button onclick="closeUnifiedModal()" class="text-muted-foreground hover:text-foreground text-2xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
            <div>
                <h4 class="text-sm font-bold text-foreground border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-baby text-primary"></i> Infant Tracking Records</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Baby Name</label><span id="pop_b_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Gender</label><span id="pop_b_gender" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Birth Date / Time</label><span id="pop_b_dob" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Weight at Delivery</label><span id="pop_b_weight" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Current Station Location</label><span id="pop_b_ward" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Admission Bed Number</label><span id="pop_b_bed_number" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Neonatal Clinical Notes</label><span id="pop_b_notes" class="text-sm text-foreground"></span></div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-rose-500 border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-female"></i> Mother Maternal Health Profile</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Mother Full Name</label><span id="pop_m_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Clinic Book Reference</label><span id="pop_m_book" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Identity Card (NIC)</label><span id="pop_m_nic" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Phone Contact</label><span id="pop_m_phone" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Blood Specification</label><span id="pop_m_blood" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Obstetric Metrics (G/P)</label><span class="text-sm text-foreground font-semibold">Gravida <span id="pop_m_g"></span>, Para <span id="pop_m_p"></span></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Expected Delivery Window (EDD)</label><span id="pop_m_edd" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">High Risk Conditions Checklist</label><span id="pop_m_risk" class="text-sm text-rose-500 font-bold"></span></div>
                </div>
            </div>
        </div>
        <div class="mt-8 border-t border-border pt-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                <h4 class="text-sm font-bold text-yellow-600 dark:text-yellow-400 flex items-center gap-2"><i class="fas fa-route"></i> Clinical Ward Stay Timeline Journey</h4>
                <div id="stay_period_selector_wrapper" style="display: none;" class="items-center gap-2">
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Stay Period:</label>
                    <select id="pop_stay_period_select" onchange="changeStayPeriodFilter()" class="bg-input border border-border text-foreground rounded-lg px-2.5 py-1 text-xs outline-none focus:border-primary cursor-pointer"></select>
                </div>
            </div>
            <div id="timeline_tree_output" class="flex flex-col gap-3.5 pl-1"></div>
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
                        <div>${indicatorIcon} <strong class="font-bold">${log.action_type} to ${log.to_ward}</strong></div>
                        <div class="text-[11px] text-muted-foreground">In: ${timeOutput}</div>
                        <div style="color:${accentColor}; font-weight:bold;">${spanDuration}</div>
                    `;
                    treeContainer.appendChild(divRowNode);
                });
            } else {
                treeContainer.innerHTML = "<div class='text-muted-foreground text-xs italic p-4'>No history tracking path recorded for this stay period.</div>";
            }
        });
}

function changeStayPeriodFilter() {
    const selectedBed = document.getElementById('pop_stay_period_select').value;
    if (currentOpenBabyId) {
        openUnifiedModal(currentOpenBabyId, selectedBed);
    }
}

function closeUnifiedModal() { document.getElementById('unifiedModal').style.display = 'none'; }

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