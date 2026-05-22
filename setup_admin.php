<?php
require 'config/db.php';

$full_name = 'System Admin';
$email = 'admin@hospital.com';
$password = 'admin123';
$role = 'Admin';

// This generates the hash specifically for YOUR server
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Delete any existing admin to avoid "Duplicate entry" errors
    $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

    // Insert the clean record
    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$full_name, $email, $hashed_password, $role]);

    echo "Admin created successfully! <br>";
    echo "Email: admin@hospital.com <br>";
    echo "Password: admin123 <br>";
    echo "<a href='index.php'>Go to Login</a>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>