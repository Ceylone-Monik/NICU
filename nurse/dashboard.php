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
    <link rel="stylesheet" href="../assets/dashboard.css">
</head>
<body>

<div class="container-main">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="profile-section">
            <div class="avatar">👩‍⚕️</div>
            <h3><?php echo htmlspecialchars($nurse['full_name']); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['role']); ?></p>
        </div>

        <div class="nav-menu">
            <div class="nav-item active" onclick="showSection('home')">🏥 Dashboard</div>
            <div class="nav-item" onclick="showSection('shifts')">⏰ Shifts</div>
            <div class="nav-item" onclick="showSection('tasks')">✓ Tasks</div>
        </div>

        <div class="logout-section">
            <a href="../logout.php" class="logout-btn"> 🛑  Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>Nurse Dashboard</h1>
            <p>Current Date: <?php echo date('F d, Y'); ?></p>
        </div>

        <!-- Home Section -->
        <div id="home" class="section active">
            <div class="card">
                <h3>👋 Welcome, <?php echo htmlspecialchars($nurse['full_name']); ?></h3>
                <p>
                    <strong>🏢 Department:</strong> <?php echo htmlspecialchars($nurse['department']); ?>
                </p>
                <p>
                    <strong>⏰ Current Shift:</strong> <?php echo htmlspecialchars($nurse['shift']); ?>
                </p>
            </div>
        </div>

        <!-- Shifts Section -->
        <div id="shifts" class="section">
            <div class="card">
                <h3>⏰ Your Shifts</h3>
                <p>
                    <strong>Department:</strong> <?php echo htmlspecialchars($nurse['department']); ?><br>
                    <strong>Assigned Shift:</strong> <?php echo htmlspecialchars($nurse['shift']); ?>
                </p>
                <button class="btn btn-disabled">View Schedule</button>
            </div>
        </div>

        <!-- Tasks Section -->
        <div id="tasks" class="section">
            <div class="card">
                <h3>✓ Daily Tasks</h3>
                <p>No tasks assigned for today.</p>
                <button class="btn btn-disabled">View All Tasks</button>
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
        event.target.classList.add('active');
    }
</script>

</body>
</html>