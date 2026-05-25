<?php
session_start();
// Look up one level to find the config folder
require_once '../config/db.php';

// SECURITY CHECK: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

$message = "";

// Handle Form Submission
if (isset($_POST['register_staff'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    try {
        $pdo->beginTransaction();

        // 1. Insert into common users table
        $sql_user = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute([$full_name, $email, $password, $role]);
        $userId = $pdo->lastInsertId();

        // 2. Insert into role-specific tables
        if ($role == 'Doctor') {
            $spec = $_POST['specialization'];
            $license = $_POST['license_no'];
            $sql_doc = "INSERT INTO doctor_details (user_id, specialization, license_no) VALUES (?, ?, ?)";
            $stmt_doc = $pdo->prepare($sql_doc);
            $stmt_doc->execute([$userId, $spec, $license]);
            $message = "Doctor added successfully!";
        } 
        else if ($role == 'Nurse') {
            $dept = $_POST['department'];
            $shift = $_POST['shift'];
            $sql_nurse = "INSERT INTO nurse_details (user_id, department, shift) VALUES (?, ?, ?)";
            $stmt_nurse = $pdo->prepare($sql_nurse);
            $stmt_nurse->execute([$userId, $dept, $shift]);
            $message = "Nurse added successfully!";
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Register Staff</title>
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
    <script>
        function toggleRoleFields() {
            var role = document.getElementById("role_select").value;
            document.getElementById("doctor_section").style.display = (role === "Doctor") ? "block" : "none";
            document.getElementById("nurse_section").style.display = (role === "Nurse") ? "block" : "none";
        }
    </script>
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
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="btn-staff" onclick="window.location.href='dashboard.php?tab=staff'">
                    <i class="fas fa-users-cog w-5 text-center"></i> <span>Staff Management</span>
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
        <a href="dashboard.php?tab=staff" class="inline-flex items-center gap-2 text-primary hover:underline font-bold text-sm mb-6 transition-all"><i class="fas fa-arrow-left"></i> Return to Staff Management</a>

        <div class="border-b border-border pb-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Register New Staff</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Configure systemic access profiles for doctors or nursing shifts.</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <?php if (!empty($message)): ?>
            <div class="p-4 rounded-radius mb-6 text-sm font-semibold flex items-center gap-2 <?php echo (strpos($message, 'Error') !== false) ? 'bg-destructive/10 text-destructive border border-destructive/20' : 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20'; ?>">
                <i class="<?php echo (strpos($message, 'Error') !== false) ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'; ?>"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <div class="card bg-card border border-border rounded-radius p-8 shadow-sm max-w-xl">
            <h2 class="text-lg font-bold text-foreground mb-6 flex items-center gap-2"><i class="fas fa-user-plus text-primary"></i> Add Staff Member</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">📝 Full Name</label>
                    <input type="text" name="full_name" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Enter full name" required>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">✉️ Email Address</label>
                    <input type="email" name="email" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Enter email address" required>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">🔐 Login Password</label>
                    <input type="password" name="password" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="Enter password" required>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block mb-1">👔 Select Role</label>
                    <select name="role" id="role_select" onchange="toggleRoleFields()" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm cursor-pointer" required>
                        <option value="" selected disabled>-- Choose Role --</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>

                <!-- Doctor fields -->
                <div id="doctor_section" style="display:none;" class="p-4 bg-primary/5 border border-primary/20 rounded-lg space-y-4">
                    <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-2 flex items-center gap-1.5"><i class="fas fa-user-md"></i> Doctor Details</h4>
                    <div>
                        <label class="text-[10px] font-semibold text-muted-foreground block mb-1">Specialization</label>
                        <input type="text" name="specialization" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="e.g. Heart Surgeon, Cardiologist">
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-muted-foreground block mb-1">Medical License Number</label>
                        <input type="text" name="license_no" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="e.g. MED123456">
                    </div>
                </div>

                <!-- Nurse fields -->
                <div id="nurse_section" style="display:none;" class="p-4 bg-primary/5 border border-primary/20 rounded-lg space-y-4">
                    <h4 class="text-xs font-bold text-primary uppercase tracking-wider mb-2 flex items-center gap-1.5"><i class="fas fa-user-nurse"></i> Nurse Details</h4>
                    <div>
                        <label class="text-[10px] font-semibold text-muted-foreground block mb-1">Department</label>
                        <input type="text" name="department" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm" placeholder="e.g. ICU, OPD, Emergency">
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-muted-foreground block mb-1">Shift</label>
                        <select name="shift" class="w-full bg-input border border-border text-foreground rounded-lg p-2.5 outline-none focus:border-primary text-sm cursor-pointer">
                            <option value="Morning">Morning Shift (6 AM - 2 PM)</option>
                            <option value="Evening">Evening Shift (2 PM - 10 PM)</option>
                            <option value="Night">Night Shift (10 PM - 6 AM)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="register_staff" class="w-full py-3 bg-primary hover:bg-primary/90 text-primary-foreground font-bold rounded-lg text-sm shadow-sm transition-all cursor-pointer border border-primary/10">✓ Register Staff</button>
            </form>
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