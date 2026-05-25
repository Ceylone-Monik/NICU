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

        <!-- Mother Profile Summary Card -->
        <div class="card bg-card border border-border rounded-radius p-8 shadow-sm hover:shadow-md transition-all duration-200 mb-8">
            <h2 class="text-xl font-bold text-foreground mb-6 flex items-center gap-2"><i class="fas fa-female text-primary"></i> Maternal File: <?php echo htmlspecialchars($mother['full_name']); ?></h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">NIC Identity</label><span class="text-sm font-semibold text-foreground"><?php echo htmlspecialchars($mother['nic']); ?></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Phone Reference</label><span class="text-sm text-foreground"><?php echo htmlspecialchars($mother['phone']); ?></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Clinic Card Ref</label><span class="text-sm text-foreground"><?php echo htmlspecialchars($mother['clinic_book_no'] ?: 'N/A'); ?></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Obstetrics (G/P)</label><span class="text-sm text-foreground font-semibold">Gravida <?php echo $mother['gravida']; ?>, Para <?php echo $mother['para']; ?></span></div>
            </div>
        </div>

        <!-- Linked Infant Delivery Directory -->
        <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm mb-8">
            <div class="p-5 border-b border-border">
                <h3 class="text-base font-bold text-foreground">👶 Linked Infant Delivery Directory</h3>
                <p class="text-xs text-muted-foreground mt-1">Click on any infant below to see their medical overview and detailed timeline history.</p>
            </div>
            
            <?php if (empty($active_babies)): ?>
                <div class="p-8 text-center text-muted-foreground text-sm">📭 No active or historical infant records registered under this profile.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Infant Name</th>
                                <th class="p-4">Gender</th>
                                <th class="p-4">Birth Timestamp</th>
                                <th class="p-4">Birth Weight</th>
                                <th class="p-4 pr-6">Current Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($active_babies as $b): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150 cursor-pointer" onclick="openUnifiedModal(<?php echo $b['baby_id']; ?>)">
                                <td class="p-4 pl-6 text-sm font-semibold text-foreground"><?php echo htmlspecialchars($b['baby_name']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($b['baby_gender']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo date('M d, Y - H:i', strtotime($b['birth_date'])); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo $b['weight_kg']; ?> kg</td>
                                <td class="p-4 pr-6 text-sm">
                                    <?php if ($b['status'] === 'Discharged'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                            🏁 Discharged
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                            Active: <?php echo htmlspecialchars($b['ward_name']); ?> Ward
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Maternal Pregnancy Admissions Table -->
        <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
            <div class="p-5 border-b border-border">
                <h3 class="text-base font-bold text-foreground">Admission stays history</h3>
                <p class="text-xs text-muted-foreground mt-1">Chronological listing of all pregnancy admission stay periods with assigned unique bed numbers.</p>
            </div>
            
            <?php if (empty($admissions)): ?>
                <div class="p-8 text-center text-muted-foreground text-sm">📭 No pregnancy admission stay records registered under this profile.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Stay ID</th>
                                <th class="p-4">Stay Bed Number</th>
                                <th class="p-4">Clinic Book Reference</th>
                                <th class="p-4">LMP Date</th>
                                <th class="p-4">EDD Date</th>
                                <th class="p-4 pr-6">Admitted Timestamp</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($admissions as $adm): ?>
                            <?php $has_bed = !empty($adm['bed_number']); ?>
                            <tr <?php if ($has_bed): ?>class="hover:bg-muted/20 transition-colors duration-150 cursor-pointer" onclick="openUnifiedModalByBedNumber('<?php echo htmlspecialchars($adm['bed_number']); ?>')"<?php else: ?>class="hover:bg-muted/10 transition-colors duration-150"<?php endif; ?>>
                                <td class="p-4 pl-6 text-sm text-muted-foreground">#<?php echo $adm['admission_id']; ?></td>
                                <td class="p-4 text-sm font-semibold text-primary"><?php echo htmlspecialchars($adm['bed_number'] ?: 'N/A'); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($adm['clinic_book_no'] ?: 'N/A'); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo $adm['lmp_date'] ? date('M d, Y', strtotime($adm['lmp_date'])) : 'N/A'; ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo $adm['edd_date'] ? date('M d, Y', strtotime($adm['edd_date'])) : 'N/A'; ?></td>
                                <td class="p-4 pr-6 text-sm text-muted-foreground"><?php echo date('M d, Y - H:i', strtotime($adm['admitted_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="unifiedModal" class="modal-overlay">
    <div class="modal-card border-primary/30">
        <div class="flex justify-between items-center border-b border-border pb-4 mb-6">
            <h2 class="text-lg font-bold text-primary flex items-center gap-2"><i class="fas fa-hospital-user"></i> Clinical Case Overview</h2>
            <button onclick="closeUnifiedModal()" class="text-muted-foreground hover:text-foreground text-2xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
            <div>
                <h4 class="text-sm font-bold text-foreground border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-baby text-primary"></i> Infant Parameters</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Baby Name</label><span id="pop_b_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Gender</label><span id="pop_b_gender" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Birth Date / Time</label><span id="pop_b_dob" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Weight at Delivery</label><span id="pop_b_weight" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Current Station Location</label><span id="pop_b_ward" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Admission Bed Number</label><span id="pop_b_bed_number" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Neonatal Notes</label><span id="pop_b_notes" class="text-sm text-foreground"></span></div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-rose-500 border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-female"></i> Mother Profile Reference</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Mother Full Name</label><span id="pop_m_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Clinic Book Reference</label><span id="pop_m_book" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Identity Card (NIC)</label><span id="pop_m_nic" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Phone Contact</label><span id="pop_m_phone" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Blood Specification</label><span id="pop_m_blood" class="text-sm text-foreground"></span></div>
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