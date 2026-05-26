<?php
session_start();
// Include the database connection from the config folder
require_once '../config/db.php';

// SECURITY CHECK: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// --- STAFF LOGIC (Pagination) ---
$users_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $users_per_page;

// Get active tab from URL or default to 'dashboard'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

$total_staff_stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_users = $total_staff_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $users_per_page);

$staff_query = "SELECT id, full_name, email, role FROM users LIMIT " . (int)$users_per_page . " OFFSET " . (int)$offset;
$users = $pdo->query($staff_query)->fetchAll(PDO::FETCH_ASSOC);


// --- PATIENT LOGIC (Pagination) ---
$patients_per_page = 10;
$patient_page = isset($_GET['patient_page']) ? (int)$_GET['patient_page'] : 1;
$patient_offset = ($patient_page - 1) * $patients_per_page;

$p_total_stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$total_patients = $p_total_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
$total_patient_pages = ceil($total_patients / $patients_per_page);

$patients_query = "SELECT id, full_name, nic, phone, created_at FROM patients ORDER BY created_at DESC LIMIT " . (int)$patients_per_page . " OFFSET " . (int)$patient_offset;
$recent_patients = $pdo->query($patients_query)->fetchAll(PDO::FETCH_ASSOC);

// --- STATISTICS COUNTS ---
$doctor_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'Doctor'");
$total_doctors = $doctor_count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

$nurse_count_stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'Nurse'");
$total_nurses = $nurse_count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;


// --- REPORTS LOGIC ---
$report_query = "SELECT r.*, p.full_name as patient_name, u.full_name as doctor_name 
                 FROM medical_reports r
                 LEFT JOIN patients p ON r.patient_id = p.id
                 LEFT JOIN users u ON r.doctor_id = u.id
                 ORDER BY r.created_at DESC";
$all_reports = $pdo->query($report_query)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Admin Dashboard</title>
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

    .section { display: none; }
    .section.active { display: block !important; }

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
        max-width: 40rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-height: 90vh;
        overflow-y: auto;
        color: var(--foreground);
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
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="btn-dashboard" onclick="showSection('dashboard')">
                    <i class="fas fa-home w-5 text-center"></i> <span>Admin Dashboard</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-wards" onclick="window.location.href='wards.php'">
                    <i class="fas fa-procedures w-5 text-center"></i> <span>Wards</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-staff" onclick="showSection('staff')">
                    <i class="fas fa-users-cog w-5 text-center"></i> <span>Staff Management</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-patients" onclick="showSection('patients')">
                    <i class="fas fa-hospital-user w-5 text-center"></i> <span>Patient Records</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="btn-reports" onclick="showSection('reports')">
                    <i class="fas fa-chart-pie w-5 text-center"></i> <span>Reports</span>
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
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Admin Portal</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Hospital Management System</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <!-- Dashboard Home Section -->
        <div id="dashboard" class="section active space-y-8 animate-in fade-in duration-200">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm">
                <h2 class="text-xl font-bold text-foreground mb-2">Welcome to Admin Dashboard</h2>
                <p class="text-sm text-muted-foreground">Here's an overview of your hospital's current statistics.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Doctors Count -->
                <div class="bg-card border-2 border-primary/20 rounded-radius p-6 flex flex-col items-center justify-center text-center shadow-sm aspect-square max-w-[220px] mx-auto w-full hover:border-primary/50 transition-all duration-200">
                    <span class="text-4xl mb-4 p-3 bg-primary/10 text-primary rounded-xl"><i class="fas fa-user-md"></i></span>
                    <h3 class="text-3xl font-extrabold text-foreground leading-none"><?php echo $total_doctors; ?></h3>
                    <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-2">Doctors</p>
                </div>
                <!-- Nurses Count -->
                <div class="bg-card border-2 border-primary/20 rounded-radius p-6 flex flex-col items-center justify-center text-center shadow-sm aspect-square max-w-[220px] mx-auto w-full hover:border-primary/50 transition-all duration-200">
                    <span class="text-4xl mb-4 p-3 bg-primary/10 text-primary rounded-xl"><i class="fas fa-user-nurse"></i></span>
                    <h3 class="text-3xl font-extrabold text-foreground leading-none"><?php echo $total_nurses; ?></h3>
                    <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-2">Nurses</p>
                </div>
                <!-- Patients Count -->
                <div class="bg-card border-2 border-primary/20 rounded-radius p-6 flex flex-col items-center justify-center text-center shadow-sm aspect-square max-w-[220px] mx-auto w-full hover:border-primary/50 transition-all duration-200">
                    <span class="text-4xl mb-4 p-3 bg-primary/10 text-primary rounded-xl"><i class="fas fa-hospital-user"></i></span>
                    <h3 class="text-3xl font-extrabold text-foreground leading-none"><?php echo $total_patients; ?></h3>
                    <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-2">Patients</p>
                </div>
            </div>
        </div>

        <!-- Staff Section -->
        <div id="staff" class="section space-y-6">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-foreground mb-2">📋 Staff Management</h3>
                    <p class="text-sm text-muted-foreground">Register new Doctors and Nurses. You currently have <strong><?php echo $total_users; ?></strong> staff members.</p>
                </div>
                <a href="add_staff.php" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg transition-all duration-150 text-sm flex items-center gap-1.5 shadow-sm hover:shadow cursor-pointer">+ Add Staff</a>
            </div>

            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border flex justify-between items-center flex-wrap gap-2">
                    <h3 class="text-base font-bold text-foreground">👥 Registered Staff Members</h3>
                    <div class="text-xs font-semibold text-muted-foreground">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Full Name</th>
                                <th class="p-4">Email</th>
                                <th class="p-4 pr-6">Role</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150">
                                <td class="p-4 pl-6 text-sm font-semibold text-foreground"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="p-4 pr-6 text-sm">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?php echo strtolower($user['role']) === 'doctor' ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-accent text-accent-foreground border border-border'; ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="p-4 border-t border-border flex justify-center items-center gap-2">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&tab=staff" class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all <?php echo ($current_page == $i) ? 'bg-primary border-primary text-primary-foreground font-bold' : 'bg-card border-border text-muted-foreground hover:text-foreground hover:bg-muted'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="patients" class="section space-y-6">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-foreground mb-2">🏥 Patient Management</h3>
                    <p class="text-sm text-muted-foreground">Click on any mother's record table line to explore her delivery records and child ward stay analytics history.</p>
                </div>
                <a href="add_patient.php" class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg transition-all duration-150 text-sm flex items-center gap-1.5 shadow-sm hover:shadow cursor-pointer">+ Add Patient</a>
            </div>

            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border flex justify-between items-center flex-wrap gap-2">
                    <h3 class="text-base font-bold text-foreground">📑 Patient Admissions Directory</h3>
                    <div class="text-xs font-semibold text-muted-foreground">Page <?php echo $patient_page; ?> of <?php echo $total_patient_pages; ?></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">ID</th>
                                <th class="p-4">Name</th>
                                <th class="p-4">NIC</th>
                                <th class="p-4">Phone</th>
                                <th class="p-4 pr-6">Admitted Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($recent_patients as $p): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150 cursor-pointer" onclick="window.location.href='mother_history.php?id=<?php echo $p['id']; ?>'">
                                <td class="p-4 pl-6 text-sm text-muted-foreground">#<?php echo $p['id']; ?></td>
                                <td class="p-4 text-sm font-semibold text-foreground"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($p['nic']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($p['phone']); ?></td>
                                <td class="p-4 pr-6 text-sm text-muted-foreground"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_patient_pages > 1): ?>
                <div class="p-4 border-t border-border flex justify-center items-center gap-2">
                    <?php for ($i = 1; $i <= $total_patient_pages; $i++): ?>
                        <a href="?patient_page=<?php echo $i; ?>&tab=patients" class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all <?php echo ($patient_page == $i) ? 'bg-primary border-primary text-primary-foreground font-bold' : 'bg-card border-border text-muted-foreground hover:text-foreground hover:bg-muted'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reports Section -->
        <div id="reports" class="section space-y-6">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm">
                <h3 class="text-lg font-bold text-foreground mb-2">📊 Hospital Medical Reports</h3>
                <p class="text-sm text-muted-foreground">Overview of all clinical reports issued by Doctors.</p>
            </div>

            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">📑 Clinical Report History</h3>
                </div>
                <?php if (count($all_reports) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Patient</th>
                                <th class="p-4">Doctor</th>
                                <th class="p-4">Diagnosis</th>
                                <th class="p-4">Date</th>
                                <th class="p-4 text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($all_reports as $report): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150">
                                <td class="p-4 pl-6 text-sm font-semibold text-foreground"><?php echo htmlspecialchars($report['patient_name'] ?? 'N/A'); ?></td>
                                <td class="p-4 text-sm text-muted-foreground">Dr. <?php echo htmlspecialchars($report['doctor_name'] ?? 'Staff'); ?></td>
                                <td class="p-4 text-sm text-muted-foreground max-w-xs truncate"><?php echo htmlspecialchars($report['diagnosis'] ?? 'N/A'); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></td>
                                <td class="p-4 text-right pr-6">
                                    <button class="px-3.5 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary font-bold rounded-lg transition-all duration-150 text-xs cursor-pointer border border-primary/20" onclick='viewReport(<?php echo json_encode($report); ?>)'>VIEW</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="p-8 text-center text-muted-foreground text-sm">
                    📭 No medical reports found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="reportModal" class="modal-overlay">
    <div class="modal-card">
        <div class="flex justify-between items-center border-b border-border pb-4 mb-6">
            <h2 class="text-lg font-bold text-foreground">Medical Report</h2>
            <button onclick="closeModal()" class="text-muted-foreground hover:text-foreground text-2xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="space-y-4">
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Patient</label><span id="r_patient" class="text-sm font-semibold text-foreground"></span></div>
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Doctor</label><span id="r_doctor" class="text-sm text-foreground"></span></div>
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Vitals</label><span id="r_vitals" class="text-sm text-foreground"></span></div>
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Symptoms</label><span id="r_symptoms" class="text-sm text-foreground"></span></div>
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Diagnosis</label><span id="r_diagnosis" class="text-sm text-foreground"></span></div>
            <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Prescription</label><span id="r_prescription" class="text-sm text-foreground"></span></div>
            <div class="pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Remarks</label><span id="r_remarks" class="text-sm text-foreground"></span></div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'dashboard';
        showSection(activeTab);
    });

    function showSection(sectionId) {
        const sections = document.querySelectorAll('.section');
        sections.forEach(section => section.classList.remove('active'));
        document.getElementById(sectionId).classList.add('active');

        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        const activeNav = document.getElementById('btn-' + sectionId);
        if(activeNav) activeNav.classList.add('active');
    }

    function viewReport(report) {
        document.getElementById('reportModal').style.display = 'flex';
        document.getElementById('r_patient').innerText = report.patient_name;
        document.getElementById('r_doctor').innerText = "Dr. " + report.doctor_name;
        document.getElementById('r_vitals').innerText = report.vitals;
        document.getElementById('r_symptoms').innerText = report.symptoms;
        document.getElementById('r_diagnosis').innerText = report.diagnosis;
        document.getElementById('r_prescription').innerText = report.prescription;
        document.getElementById('r_remarks').innerText = report.remarks;
    }

    function closeModal() {
        document.getElementById('reportModal').style.display = 'none';
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

    // Initialise toggle position on page load
    document.addEventListener('DOMContentLoaded', () => {
        const activeTheme = localStorage.getItem('theme') || 'light';
        setTheme(activeTheme);
    });
</script>

</body>
</html>