<?php
session_start();
require '../config/db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Doctor') {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$message = "";

// 2. FETCH DOCTOR DATA
$stmt = $pdo->prepare("SELECT users.full_name, doctor_details.specialization FROM users JOIN doctor_details ON users.id = doctor_details.user_id WHERE users.id = ?");
$stmt->execute([$userId]);
$doctor = $stmt->fetch();

// 3. HANDLE REPORT SAVING (Mothers & Babies)
if (isset($_POST['save_report'])) {
    try {
        $p_id = !empty($_POST['p_id']) ? $_POST['p_id'] : null;
        $baby_id = !empty($_POST['baby_id']) ? $_POST['baby_id'] : null;

        if (!empty($_POST['report_id'])) {
            $sql = "UPDATE medical_reports SET symptoms=?, diagnosis=?, vitals=?, prescription=?, remarks=? WHERE report_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks'], $_POST['report_id']]);
        } else {
            $sql = "INSERT INTO medical_reports (patient_id, baby_id, doctor_id, symptoms, diagnosis, vitals, prescription, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$p_id, $baby_id, $userId, $_POST['symptoms'], $_POST['diagnosis'], $_POST['vitals'], $_POST['prescription'], $_POST['remarks']]);
        }
        header("Location: dashboard.php?msg=Success");
        exit();
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// 4. HANDLE SUBMITTING WARD MOVE RECOMMENDATION
if (isset($_POST['submit_recommendation'])) {
    try {
        $stmt = $pdo->prepare("UPDATE babies SET recommended_ward = ?, recommendation_status = 'Pending' WHERE baby_id = ?");
        $stmt->execute([$_POST['recommended_ward'], $_POST['baby_id']]);
        $message = "Recommendation submitted to Administrator successfully!";
    } catch (Exception $e) { $message = "Error: " . $e->getMessage(); }
}

// 5. FETCH DATA ROSTERS
$all_patients = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch reports history with fallback naming structures for infants
$stmt_reports = $pdo->prepare("
    SELECT r.*, 
           p.full_name as mother_name, p.nic as mother_nic,
           b.baby_name
    FROM medical_reports r 
    LEFT JOIN patients p ON r.patient_id = p.id 
    LEFT JOIN babies b ON r.baby_id = b.baby_id
    WHERE r.doctor_id = ? 
    ORDER BY r.created_at DESC
");
$stmt_reports->execute([$userId]);
$reports = $stmt_reports->fetchAll(PDO::FETCH_ASSOC);

$query_babies = "SELECT b.*, p.full_name as mother_name FROM babies b JOIN patients p ON b.mother_id = p.id ORDER BY b.birth_date DESC";
$all_babies = $pdo->query($query_babies)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
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
        max-width: 48rem;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        max-height: 90vh;
        overflow-y: auto;
        color: var(--foreground);
    }
    </style>
</head>
<body class="min-h-screen bg-background text-foreground flex flex-col md:flex-row transition-colors duration-200">

<div class="flex-1 flex flex-col md:flex-row w-full relative z-10">
    <!-- Sidebar -->
    <div class="w-full md:w-72 bg-sidebar border-b md:border-b-0 md:border-r border-sidebar-border text-sidebar-foreground p-6 flex flex-col justify-between flex-shrink-0 transition-colors duration-200">
        <div>
            <div class="flex flex-col items-center text-center pb-6 border-b border-sidebar-border mb-6">
                <div class="w-16 h-16 rounded-full bg-accent text-accent-foreground flex items-center justify-center text-3xl mb-3 shadow-inner">
                    👨‍⚕️
                </div>
                <h3 class="text-base font-bold text-foreground">Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-1"><?php echo htmlspecialchars($doctor['specialization']); ?></p>
            </div>
            
            <nav class="space-y-1">
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="nav-home" onclick="showSection('home')">
                    <i class="fas fa-home w-5 text-center"></i> <span>Dashboard</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-patients" onclick="showSection('patients')">
                    <i class="fas fa-user-injured w-5 text-center"></i> <span>Patients</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-babies" onclick="showSection('babies')">
                    <i class="fas fa-baby w-5 text-center"></i> <span>Infants (Wards)</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-reports" onclick="showSection('reports')">
                    <i class="fas fa-file-medical w-5 text-center"></i> <span>Reports History</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-search-bed" onclick="showSection('search-bed')">
                    <i class="fas fa-bed w-5 text-center"></i> <span>Search Bed</span>
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
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Doctor Portal</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Hospital Management System</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <?php if (!empty($message) || (isset($_GET['msg']) && $_GET['msg'] === 'Success')): ?>
            <div class="p-4 bg-emerald-500/10 text-emerald-500 border border-emerald-500/20 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2">
                <i class="fas fa-check-circle text-base"></i>
                <span><?php echo !empty($message) ? htmlspecialchars($message) : "Medical report synchronized successfully!"; ?></span>
            </div>
        <?php endif; ?>

        <!-- Home Section -->
        <div id="home" class="section active">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm transition-all duration-200 hover:shadow-md">
                <h3 class="text-xl font-bold text-foreground mb-4">Welcome, Dr. <?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                <p class="text-sm text-muted-foreground leading-relaxed">Manage patients, issue clinical prescriptions, and coordinate nursery ward assignments.</p>
            </div>
        </div>

        <!-- Patients Section -->
        <div id="patients" class="section">
            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border">
                    <h3 class="text-lg font-bold text-foreground">📋 Maternal Patients Directory</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Name</th>
                                <th class="p-4">NIC</th>
                                <th class="p-4">Phone</th>
                                <th class="p-4 text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($all_patients as $p): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150">
                                <td class="p-4 pl-6 text-sm font-semibold text-foreground"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($p['nic']); ?></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($p['phone']); ?></td>
                                <td class="p-4 text-right pr-6">
                                    <div class="inline-flex gap-2">
                                        <button class="px-3.5 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary font-bold rounded-lg transition-all duration-150 text-xs flex items-center gap-1.5 cursor-pointer border border-primary/20" onclick='openProfile(<?php echo json_encode($p); ?>)'>
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                        <button class="px-3.5 py-1.5 bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 font-bold rounded-lg transition-all duration-150 text-xs flex items-center gap-1.5 cursor-pointer border border-yellow-500/20" onclick='openReportForm(<?php echo json_encode($p); ?>, "mother")'>
                                            <i class="fas fa-plus-circle"></i> + Report
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Babies Section -->
        <div id="babies" class="section">
            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border">
                    <h3 class="text-lg font-bold text-foreground">👶 Active Nursery Wards Roster</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Infant Name</th>
                                <th class="p-4">Mother</th>
                                <th class="p-4">Current Ward</th>
                                <th class="p-4">Suggest Ward Move</th>
                                <th class="p-4 text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($all_babies as $b): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150 cursor-pointer" onclick="openUnifiedModalFromDoctor(<?php echo $b['baby_id']; ?>)">
                                <td class="p-4 pl-6 text-sm text-foreground"><strong class="font-bold text-foreground"><?php echo htmlspecialchars($b['baby_name']); ?></strong></td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo htmlspecialchars($b['mother_name']); ?></td>
                                <td class="p-4 text-sm">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                        <?php echo htmlspecialchars($b['ward_name']); ?> Ward
                                    </span>
                                </td>
                                <td class="p-4" onclick="event.stopPropagation();">
                                    <?php if ($b['recommendation_status'] === 'Pending'): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-yellow-500/10 text-yellow-600 dark:text-yellow-400 border border-yellow-500/20">
                                            <i class="fas fa-hourglass-half animate-pulse"></i> Pending (-> <?php echo htmlspecialchars($b['recommended_ward']); ?>)
                                        </span>
                                    <?php else: ?>
                                        <form method="POST" class="flex items-center gap-2 m-0">
                                            <input type="hidden" name="baby_id" value="<?php echo $b['baby_id']; ?>">
                                            <select name="recommended_ward" class="bg-input border border-border text-foreground rounded-lg px-2.5 py-1 text-xs outline-none focus:border-primary cursor-pointer" required>
                                                <option value="" selected disabled>Select...</option>
                                                <?php 
                                                $options = ['Normal', 'Critical', 'Other', 'To Discharge'];
                                                foreach($options as $opt) {
                                                    if($opt !== $b['ward_name']) { echo "<option value='$opt'>$opt</option>"; }
                                                }
                                                ?>
                                            </select>
                                            <button type="submit" name="submit_recommendation" class="px-2.5 py-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg transition-all duration-150 text-xs cursor-pointer border border-primary/10"><i class="fas fa-paper-plane"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 text-right pr-6" onclick="event.stopPropagation();">
                                    <button class="px-3.5 py-1.5 bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 font-bold rounded-lg transition-all duration-150 text-xs flex items-center gap-1.5 cursor-pointer border border-yellow-500/20" onclick='openReportForm(<?php echo json_encode($b); ?>, "baby")'>
                                        <i class="fas fa-file-medical"></i> + Report
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Reports Section -->
        <div id="reports" class="section">
            <div class="overflow-hidden bg-card border border-border rounded-radius shadow-sm">
                <div class="p-5 border-b border-border">
                    <h3 class="text-lg font-bold text-foreground">📋 Clinical Reports Audit Log</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-muted/50 text-foreground text-xs font-bold uppercase tracking-wider border-b border-border">
                                <th class="p-4 pl-6">Target</th>
                                <th class="p-4">Reference Name</th>
                                <th class="p-4">Date</th>
                                <th class="p-4 text-right pr-6">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php foreach ($reports as $r): ?>
                            <tr class="hover:bg-muted/20 transition-colors duration-150">
                                <td class="p-4 pl-6 text-sm">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo !empty($r['baby_id']) ? 'bg-primary/10 text-primary border border-primary/20' : 'bg-accent text-accent-foreground border border-border'; ?>">
                                        <?php echo !empty($r['baby_id']) ? '👶 Infant' : '🤰 Mother'; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-sm font-semibold text-foreground">
                                    <?php echo !empty($r['baby_id']) ? htmlspecialchars($r['baby_name']) . " (Infant)" : htmlspecialchars($r['mother_name']); ?>
                                </td>
                                <td class="p-4 text-sm text-muted-foreground"><?php echo date('Y-m-d', strtotime($r['created_at'])); ?></td>
                                <td class="p-4 text-right pr-6">
                                    <div class="inline-flex gap-2">
                                        <button class="px-3.5 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary font-bold rounded-lg transition-all duration-150 text-xs flex items-center gap-1.5 cursor-pointer border border-primary/20" onclick='viewReport(<?php echo json_encode($r); ?>)'>
                                            <i class="fas fa-file-alt"></i> View
                                        </button>
                                        <button class="px-3.5 py-1.5 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg transition-all duration-150 text-xs flex items-center gap-1.5 cursor-pointer" onclick='editReport(<?php echo json_encode($r); ?>)'>
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Search Bed Section -->
        <div id="search-bed" class="section">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm transition-all duration-200 hover:shadow-md mb-8">
                <h3 class="text-xl font-bold text-foreground mb-2 flex items-center gap-2"><i class="fas fa-search text-primary"></i> Search Patient by Bed Number</h3>
                <p class="text-sm text-muted-foreground mb-6">Locate active newborn infants and maternal profiles in the system by entering the bed number below.</p>
                <div class="flex gap-4 max-w-lg">
                    <input type="text" id="main_search_bed_input" class="w-full bg-input border border-border text-foreground rounded-lg p-3 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-150 text-sm placeholder-muted-foreground" placeholder="e.g. Bed 01" onkeydown="if(event.key === 'Enter') executeBedSearch()">
                    <button class="px-6 py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg shadow-sm hover:shadow transition-all duration-150 text-sm flex items-center gap-2 cursor-pointer" onclick="executeBedSearch()"><i class="fas fa-search"></i> Search</button>
                </div>
            </div>

            <!-- Result Card -->
            <div id="search_results_container" style="display: none;" class="animate-in fade-in duration-200">
                <div class="card bg-card border border-primary/30 rounded-radius p-8 shadow-md">
                    <h3 class="text-lg font-bold text-primary border-b border-border pb-4 mb-6 flex items-center gap-2">
                        <i class="fas fa-id-card"></i> Admission Case File
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div>
                            <h4 class="text-sm font-bold text-foreground border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-baby text-primary"></i> Infant Tracking Records</h4>
                            <div class="space-y-3">
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Baby Name</label><span id="res_b_name" class="text-sm text-foreground font-semibold"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Gender</label><span id="res_b_gender" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Birth Date / Time</label><span id="res_b_dob" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Weight at Delivery</label><span id="res_b_weight" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Current Station Location</label><span id="res_b_ward" class="text-sm text-primary font-bold"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Admission Bed Number</label><span id="res_b_bed_number" class="text-sm text-primary font-bold"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Neonatal Clinical Notes</label><span id="res_b_notes" class="text-sm text-foreground"></span></div>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-rose-500 border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-female"></i> Mother Maternal Health Profile</h4>
                            <div class="space-y-3">
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Mother Full Name</label><span id="res_m_name" class="text-sm text-foreground font-semibold"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Clinic Book Reference</label><span id="res_m_book" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Identity Card (NIC)</label><span id="res_m_nic" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Phone Contact</label><span id="res_m_phone" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Blood Specification</label><span id="res_m_blood" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Obstetric Metrics (G/P)</label><span class="text-sm text-foreground font-semibold">Gravida <span id="res_m_g"></span>, Para <span id="res_m_p"></span></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Expected Delivery Window (EDD)</label><span id="res_m_edd" class="text-sm text-foreground"></span></div>
                                <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">High Risk Conditions Checklist</label><span id="res_m_risk" class="text-sm text-rose-500 font-bold"></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8 border-t border-border pt-6 flex gap-4 flex-wrap">
                        <button id="res_btn_report_baby" class="px-4 py-2.5 bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-600 dark:text-yellow-400 font-bold rounded-lg transition-all duration-150 text-sm flex items-center gap-1.5 cursor-pointer border border-yellow-500/20"><i class="fas fa-file-medical"></i> Add Infant Report</button>
                        <button id="res_btn_report_mother" class="px-4 py-2.5 bg-primary/10 hover:bg-primary/20 text-primary font-bold rounded-lg transition-all duration-150 text-sm flex items-center gap-1.5 cursor-pointer border border-primary/20" onclick=""><i class="fas fa-plus-circle"></i> Add Mother Report</button>
                    </div>
                </div>
            </div>

            <!-- No Results Card -->
            <div id="search_no_results" style="display: none; margin-top: 30px;">
                <div class="card border border-destructive/20 bg-destructive/10 rounded-radius p-6">
                    <p class="text-destructive font-bold text-sm flex items-center gap-2 m-0">
                        <i class="fas fa-exclamation-circle text-lg"></i> No active infant or admission records found linked to this bed number.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div id="modal" class="modal-overlay">
    <div class="modal-card">
        <div class="flex justify-between items-center border-b border-border pb-4 mb-6">
            <h2 id="modal_title" class="text-lg font-bold text-foreground"></h2>
            <button onclick="closeModal()" class="text-muted-foreground hover:text-foreground text-2xl font-bold cursor-pointer">&times;</button>
        </div>
        
        <!-- Profile View -->
        <div id="profile_view_area" style="display:none;" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">DOB</label><span id="m_dob" class="text-sm font-semibold text-foreground"></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Gender</label><span id="m_gender" class="text-sm text-foreground"></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Blood Group</label><span id="m_blood" class="text-sm text-foreground"></span></div>
                <div class="border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">NIC</label><span id="m_nic" class="text-sm text-foreground"></span></div>
                <div class="border-b border-border/50 pb-2 sm:col-span-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Medical Allergies</label><span id="m_allergies" class="text-sm text-foreground"></span></div>
            </div>
        </div>

        <!-- Report View -->
        <div id="report_view_area" style="display:none;" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-border pt-4">
                <div class="sm:col-span-2 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Diagnosis</label><span id="v_diag" class="text-sm text-foreground font-semibold"></span></div>
                <div class="pb-2 border-b border-border/50"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Vitals</label><span id="v_vitals" class="text-sm text-foreground"></span></div>
                <div class="pb-2 border-b border-border/50"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Symptoms</label><span id="v_symp" class="text-sm text-foreground"></span></div>
                <div class="sm:col-span-2 pb-2 border-b border-border/50"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Prescription</label><span id="v_pres" class="text-sm text-foreground"></span></div>
                <div class="sm:col-span-2 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Doctor Remarks</label><span id="v_rem" class="text-sm text-foreground"></span></div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div id="emergency_row" style="display:none;" class="border-t border-border pt-4 mt-6">
            <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Emergency Contact</label>
            <p class="text-sm text-foreground font-semibold"><span id="m_em_name"></span> <span id="m_em_phone" class="text-muted-foreground text-xs ml-3"></span></p>
        </div>

        <!-- Form Area -->
        <div id="form_area" style="display:none;" class="mt-4">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="p_id" id="f_p_id">
                <input type="hidden" name="baby_id" id="f_baby_id">
                <input type="hidden" name="report_id" id="f_rep_id">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Vitals</label>
                        <input type="text" name="vitals" id="f_vitals" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="BP, Temp, or Birth Weight">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Symptoms / Observations</label>
                        <input type="text" name="symptoms" id="f_symp" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Symptoms / Observations">
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Diagnosis / Clinical Impression</label>
                    <textarea name="diagnosis" id="f_diag" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm h-24 resize-none" placeholder="Diagnosis / Clinical Impression"></textarea>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Prescription</label>
                    <textarea name="prescription" id="f_pres" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm h-24 resize-none" placeholder="Medications, Feed instructions"></textarea>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">Remarks</label>
                    <textarea name="remarks" id="f_rem" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm h-24 resize-none" placeholder="Remarks"></textarea>
                </div>
                <button type="submit" name="save_report" class="w-full bg-primary hover:bg-primary/90 text-primary-foreground font-bold p-3 rounded-lg text-sm shadow-sm transition-all cursor-pointer">SAVE REPORT</button>
            </form>
        </div>
    </div>
</div>

<div id="unifiedBabyModal" class="modal-overlay" style="z-index: 3000;">
    <div class="modal-card border-primary/30">
        <div class="flex justify-between items-center border-b border-border pb-4 mb-6">
            <h2 class="text-lg font-bold text-primary flex items-center gap-2"><i class="fas fa-notes-medical"></i> Case Overview</h2>
            <button onclick="closeUnifiedBabyModal()" class="text-muted-foreground hover:text-foreground text-2xl font-bold cursor-pointer">&times;</button>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
            <div>
                <h4 class="text-sm font-bold text-foreground border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-baby text-primary"></i> Infant Parameters</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Baby Name</label><span id="pop_b_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Gender</label><span id="pop_b_gender" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Birth Date / Time</label><span id="pop_b_dob" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Weight</label><span id="pop_b_weight" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Current Ward</label><span id="pop_b_ward" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Admission Bed Number</label><span id="pop_b_bed_number" class="text-sm text-primary font-bold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Condition Notes</label><span id="pop_b_notes" class="text-sm text-foreground"></span></div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-bold text-rose-500 border-b border-border pb-2 mb-4 flex items-center gap-2"><i class="fas fa-female"></i> Mother Profile</h4>
                <div class="space-y-3">
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Mother Full Name</label><span id="pop_m_name" class="text-sm text-foreground font-semibold"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Clinic Book Reference</label><span id="pop_m_book" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Identity Card (NIC)</label><span id="pop_m_nic" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Phone Contact</label><span id="pop_m_phone" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Blood Specification</label><span id="pop_m_blood" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Obstetric Metrics (G/P)</label><span class="text-sm text-foreground font-semibold">Gravida <span id="pop_m_g"></span>, Para <span id="pop_m_p"></span></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Expected Delivery Window (EDD)</label><span id="pop_m_edd" class="text-sm text-foreground"></span></div>
                    <div class="flex flex-col border-b border-border/50 pb-2"><label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">Maternal Risk Factors</label><span id="pop_m_risk" class="text-sm text-rose-500 font-bold"></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function showSection(id) {
        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        document.getElementById('nav-' + id).classList.add('active');
    }
    function closeModal() { document.getElementById('modal').style.display = 'none'; }
    function closeUnifiedBabyModal() { document.getElementById('unifiedBabyModal').style.display = 'none'; }

    function executeBedSearch() {
        const bedInput = document.getElementById('main_search_bed_input');
        const bedNumber = bedInput.value.trim();
        const resultsContainer = document.getElementById('search_results_container');
        const noResultsContainer = document.getElementById('search_no_results');

        if (!bedNumber) {
            alert("Please enter a bed number to search.");
            return;
        }

        fetch('../admin/get_baby_details.php?bed_number=' + encodeURIComponent(bedNumber))
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultsContainer.style.display = 'none';
                    noResultsContainer.style.display = 'block';
                    return;
                }
                
                noResultsContainer.style.display = 'none';
                resultsContainer.style.display = 'block';

                document.getElementById('res_b_name').innerText = data.baby_name;
                document.getElementById('res_b_gender').innerText = data.baby_gender;
                document.getElementById('res_b_dob').innerText = data.birth_date;
                document.getElementById('res_b_weight').innerText = data.weight_kg + " kg";
                document.getElementById('res_b_ward').innerText = data.status === 'Discharged' ? 'Discharged' : data.ward_name + " Ward";
                document.getElementById('res_b_bed_number').innerText = data.admission_bed_number || "N/A";
                document.getElementById('res_b_notes').innerText = data.condition_notes || "None documented";
                
                document.getElementById('res_m_name').innerText = data.mother_name;
                document.getElementById('res_m_book').innerText = data.clinic_book_no || "N/A";
                document.getElementById('res_m_nic').innerText = data.mother_nic;
                document.getElementById('res_m_phone').innerText = data.mother_phone;
                document.getElementById('res_m_blood').innerText = data.mother_blood || "Unknown";
                document.getElementById('res_m_g').innerText = data.gravida || "0";
                document.getElementById('res_m_p').innerText = data.para || "0";
                document.getElementById('res_m_edd').innerText = data.edd_date || "N/A";
                document.getElementById('res_m_risk').innerText = data.pregnancy_risk_factors || "None (Low Risk)";

                // Setup Action Buttons
                document.getElementById('res_btn_report_baby').onclick = function() {
                    openReportForm({ baby_id: data.baby_id, baby_name: data.baby_name }, 'baby');
                };
                document.getElementById('res_btn_report_mother').onclick = function() {
                    openReportForm({ id: data.mother_id, full_name: data.mother_name }, 'mother');
                };
            })
            .catch(err => {
                console.error(err);
                resultsContainer.style.display = 'none';
                noResultsContainer.style.display = 'block';
            });
    }

    function openUnifiedModalFromDoctor(babyId) {
        fetch('../admin/get_baby_details.php?baby_id=' + babyId)
            .then(response => response.json())
            .then(data => {
                if (data.error) { alert(data.error); return; }
                document.getElementById('unifiedBabyModal').style.display = 'flex';
                document.getElementById('pop_b_name').innerText = data.baby_name;
                document.getElementById('pop_b_gender').innerText = data.baby_gender;
                document.getElementById('pop_b_dob').innerText = data.birth_date;
                document.getElementById('pop_b_weight').innerText = data.weight_kg + " kg";
                document.getElementById('pop_b_ward').innerText = data.ward_name + " Ward";
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
            });
    }

    function openReportForm(data, targetType) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        
        // Reset old entry inputs cleanly
        document.getElementById('f_rep_id').value = "";
        document.getElementById('f_vitals').value = "";
        document.getElementById('f_symp').value = "";
        document.getElementById('f_diag').value = "";
        document.getElementById('f_pres').value = "";
        document.getElementById('f_rem').value = "";

        if(targetType === 'baby') {
            document.getElementById('modal_title').innerText = "Clinical Report: " + data.baby_name;
            document.getElementById('f_baby_id').value = data.baby_id;
            document.getElementById('f_p_id').value = "";
        } else {
            document.getElementById('modal_title').innerText = "Clinical Report: " + data.full_name;
            document.getElementById('f_p_id').value = data.id;
            document.getElementById('f_baby_id').value = "";
        }
    }

    function viewReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Medical Record View";
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'block';
        document.getElementById('form_area').style.display = 'none';
        
        document.getElementById('v_diag').innerText = r.diagnosis;
        document.getElementById('v_vitals').innerText = r.vitals;
        document.getElementById('v_symp').innerText = r.symptoms;
        document.getElementById('v_pres').innerText = r.prescription;
        document.getElementById('v_rem').innerText = r.remarks;
    }

    function editReport(r) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Update Clinical Report";
        document.getElementById('profile_view_area').style.display = 'none';
        document.getElementById('emergency_row').style.display = 'none';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'block';
        
        document.getElementById('f_rep_id').value = r.report_id;
        document.getElementById('f_p_id').value = r.patient_id || "";
        document.getElementById('f_baby_id').value = r.baby_id || "";
        document.getElementById('f_vitals').value = r.vitals;
        document.getElementById('f_symp').value = r.symptoms;
        document.getElementById('f_diag').value = r.diagnosis;
        document.getElementById('f_pres').value = r.prescription;
        document.getElementById('f_rem').value = r.remarks;
    }

    function fillBasicInfo(p) {
        document.getElementById('m_dob').innerText = p.dob;
        document.getElementById('m_gender').innerText = p.gender;
        document.getElementById('m_blood').innerText = p.blood_group;
        document.getElementById('m_nic').innerText = p.nic;
        document.getElementById('m_allergies').innerText = p.allergies || "None";
    }

    function openProfile(p) {
        document.getElementById('modal').style.display = 'flex';
        document.getElementById('modal_title').innerText = "Patient Profile: " + p.full_name;
        document.getElementById('profile_view_area').style.display = 'block';
        document.getElementById('emergency_row').style.display = 'block';
        document.getElementById('report_view_area').style.display = 'none';
        document.getElementById('form_area').style.display = 'none';
        fillBasicInfo(p);
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
<script src="../assets/beams.js"></script>
</body>
</html>