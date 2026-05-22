<?php
session_start();
require 'config/db.php';

$error = "";

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pass = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] == 'Admin') header("Location: admin/dashboard.php");
        elseif ($user['role'] == 'Doctor') header("Location: doctor/dashboard.php");
        else header("Location: nurse/dashboard.php");
        exit();
    } else {
        $error = "Invalid credentials!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hospital Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="login-card">
    <h2 class="login-title">Login</h2>

    <?php if($error): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <i class="fas fa-envelope"></i>
            <input class="form-control" type="email" name="email" placeholder="Email" required>
        </div>

        <div class="input-group">
            <i class="fas fa-lock"></i>
            <input type="password" name="password" placeholder="Password" required>
        </div>

        <div class="remember-row">
            <label><input type="checkbox"> Remember me</label>
            <a href="#" style="color: #2ecc71; text-decoration: none;">Forgot Password?</a>
        </div>

        <button type="submit" name="login" class="login-btn">LOGIN</button>
    </form>

    
</div>

</body>
</html>