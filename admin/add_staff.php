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
    <link rel="stylesheet" href="../assets/dashboard.css">
    <style>
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group label {
            display: block;
            color: #b0b0b0;
            font-size: 13px;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: #ffffff;
            font-size: 15px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .input-group input::placeholder,
        .input-group select::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .input-group input:focus,
        .input-group select:focus {
            outline: none;
            border-color: #00ff96;
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(0, 255, 150, 0.3);
        }

        .input-group option {
            background: #1a1a2e;
            color: #ffffff;
        }

        .form-section {
            background: rgba(30, 30, 40, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 20px;
            color: white;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 50px rgba(0, 255, 150, 0.1);
            max-width: 600px;
        }

        .form-section h2 {
            color: #00ff96;
            font-size: 26px;
            margin-bottom: 30px;
            font-weight: 300;
            letter-spacing: 1px;
        }

        .form-section h4 {
            color: #00ff96;
            font-size: 14px;
            margin-top: 20px;
            margin-bottom: 15px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .role-fields {
            background: rgba(0, 255, 150, 0.05);
            border: 1px solid rgba(0, 255, 150, 0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideUp 0.8s ease-out;
        }

        .message.success {
            background: rgba(0, 255, 150, 0.1);
            border: 1px solid rgba(0, 255, 150, 0.3);
            color: #00ff96;
        }

        .message.error {
            background: rgba(255, 107, 107, 0.1);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff8787;
        }

        .back-link {
            display: inline-block;
            color: #00ff96;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #00cc7f;
            text-decoration: underline;
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
<body>

<div class="container-main">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="profile-section">
            <div class="avatar">👤</div>
            <h3><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></h3>
            <p><?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?></p>
        </div>

        <div class="nav-menu">
            <div class="nav-item active" onclick="window.location.href='dashboard.php'">📊 Staff Management</div>
            
        </div>

        <div class="logout-section">
            <a href="../logout.php" class="logout-btn"> 🛑  Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>Register New Staff</h1>
            <p><?php echo date('F d, Y'); ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo (strpos($message, 'Error') !== false) ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <h2>Add Staff Member</h2>
            <form method="POST">
                <div class="input-group">
                    <label>📝 Full Name</label>
                    <input type="text" name="full_name" placeholder="Enter full name" required>
                </div>

                <div class="input-group">
                    <label>✉️ Email Address</label>
                    <input type="email" name="email" placeholder="Enter email address" required>
                </div>

                <div class="input-group">
                    <label>🔐 Login Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="input-group">
                    <label>👔 Select Role</label>
                    <select name="role" id="role_select" onchange="toggleRoleFields()" required>
                        <option value="">-- Choose Role --</option>
                        <option value="Doctor">Doctor</option>
                        <option value="Nurse">Nurse</option>
                    </select>
                </div>

                <div id="doctor_section" style="display:none;" class="role-fields">
                    <h4>👨‍⚕️ Doctor Details</h4>
                    <div class="input-group">
                        <label>Specialization</label>
                        <input type="text" name="specialization" placeholder="e.g. Heart Surgeon, Cardiologist">
                    </div>
                    <div class="input-group">
                        <label>Medical License Number</label>
                        <input type="text" name="license_no" placeholder="e.g. MED123456">
                    </div>
                </div>

                <div id="nurse_section" style="display:none;" class="role-fields">
                    <h4>👩‍⚕️ Nurse Details</h4>
                    <div class="input-group">
                        <label>Department</label>
                        <input type="text" name="department" placeholder="e.g. ICU, OPD, Emergency">
                    </div>
                    <div class="input-group">
                        <label>Shift</label>
                        <select name="shift">
                            <option value="Morning">Morning Shift (6 AM - 2 PM)</option>
                            <option value="Evening">Evening Shift (2 PM - 10 PM)</option>
                            <option value="Night">Night Shift (10 PM - 6 AM)</option>
                        </select>
                    </div>
                </div>

                <button type="submit" name="register_staff" class="btn">✓ Register Staff</button>
            </form>

            <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>