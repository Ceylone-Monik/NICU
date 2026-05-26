<?php
session_start();
require '../config/db.php';

// SECURITY CHECK: If not logged in or NOT a Nurse, kick them out
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Nurse') {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT users.full_name, nurse_details.department, nurse_details.shift 
                       FROM users 
                       JOIN nurse_details ON users.id = nurse_details.user_id 
                       WHERE users.id = ?");
$stmt->execute([$userId]);
$nurse = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Dashboard</title>
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
    </style>
</head>
<body class="min-h-screen bg-background text-foreground flex flex-col md:flex-row transition-colors duration-200">

<div class="flex-1 flex flex-col md:flex-row w-full relative z-10">
    <!-- Sidebar -->
    <div class="w-full md:w-72 bg-sidebar border-b md:border-b-0 md:border-r border-sidebar-border text-sidebar-foreground p-6 flex flex-col justify-between flex-shrink-0 transition-colors duration-200">
        <div>
            <div class="flex flex-col items-center text-center pb-6 border-b border-sidebar-border mb-6">
                <div class="w-16 h-16 rounded-full bg-accent text-accent-foreground flex items-center justify-center text-3xl mb-3 shadow-inner">
                    👩‍⚕️
                </div>
                <h3 class="text-base font-bold text-foreground"><?php echo htmlspecialchars($nurse['full_name']); ?></h3>
                <p class="text-xs text-muted-foreground font-semibold uppercase tracking-wider mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
            </div>
            
            <nav class="space-y-1">
                <div class="nav-item active flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-semibold transition-all duration-150 cursor-pointer" id="nav-home" onclick="showSection('home')">
                    <i class="fas fa-home w-5 text-center"></i> <span>Dashboard</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-shifts" onclick="showSection('shifts')">
                    <i class="fas fa-clock w-5 text-center"></i> <span>Shifts</span>
                </div>
                <div class="nav-item flex items-center gap-3 px-4 py-3 rounded-radius text-sm font-medium transition-all duration-150 cursor-pointer" id="nav-tasks" onclick="showSection('tasks')">
                    <i class="fas fa-check-square w-5 text-center"></i> <span>Tasks</span>
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
                <h1 class="text-3xl font-extrabold tracking-tight text-foreground">Nurse Portal</h1>
                <p class="text-sm text-muted-foreground font-medium mt-1">Hospital Management System</p>
            </div>
            <span class="px-4 py-1.5 bg-accent text-accent-foreground text-xs font-bold rounded-full mt-3 md:mt-0 shadow-sm border border-border">
                <i class="far fa-calendar-alt mr-1"></i> <?php echo date('F d, Y'); ?>
            </span>
        </div>

        <!-- Home Section -->
        <div id="home" class="section active">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm transition-all duration-200 hover:shadow-md max-w-2xl">
                <h3 class="text-xl font-bold text-foreground mb-6">👋 Welcome, <?php echo htmlspecialchars($nurse['full_name']); ?></h3>
                <div class="space-y-4">
                    <div class="flex items-center gap-3 border-b border-border/50 pb-3">
                        <span class="p-2.5 bg-primary/10 text-primary rounded-lg text-lg"><i class="fas fa-hospital"></i></span>
                        <div>
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">Department</span>
                            <span class="text-sm text-foreground font-semibold"><?php echo htmlspecialchars($nurse['department']); ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 bg-primary/10 text-primary rounded-lg text-lg"><i class="fas fa-clock"></i></span>
                        <div>
                            <span class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider block">Current Shift</span>
                            <span class="text-sm text-foreground font-semibold"><?php echo htmlspecialchars($nurse['shift']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shifts Section -->
        <div id="shifts" class="section">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm transition-all duration-200 hover:shadow-md max-w-2xl">
                <h3 class="text-xl font-bold text-foreground mb-4"><i class="fas fa-clock text-primary mr-1"></i> Your Shifts</h3>
                <p class="text-sm text-muted-foreground mb-6">
                    <strong>Department:</strong> <?php echo htmlspecialchars($nurse['department']); ?><br>
                    <strong>Assigned Shift:</strong> <?php echo htmlspecialchars($nurse['shift']); ?>
                </p>
                <button class="px-4 py-2.5 bg-muted text-muted-foreground font-semibold rounded-lg text-xs uppercase cursor-not-allowed opacity-60">View Schedule</button>
            </div>
        </div>

        <!-- Tasks Section -->
        <div id="tasks" class="section">
            <div class="card bg-card border border-border rounded-radius p-8 shadow-sm transition-all duration-200 hover:shadow-md max-w-2xl">
                <h3 class="text-xl font-bold text-foreground mb-4"><i class="fas fa-check-square text-primary mr-1"></i> Daily Tasks</h3>
                <p class="text-sm text-muted-foreground mb-6">No tasks assigned for today.</p>
                <button class="px-4 py-2.5 bg-muted text-muted-foreground font-semibold rounded-lg text-xs uppercase cursor-not-allowed opacity-60">View All Tasks</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showSection(sectionId) {
        // Hide all sections
        const sections = document.querySelectorAll('.section');
        sections.forEach(section => section.classList.remove('active'));

        // Show selected section
        document.getElementById(sectionId).classList.add('active');

        // Update active nav item
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => item.classList.remove('active'));
        
        // Highlight active nav item
        const activeNav = document.getElementById('nav-' + sectionId);
        if(activeNav) activeNav.classList.add('active');
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