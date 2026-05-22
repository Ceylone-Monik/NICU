<?php
// 1. Start the session to gain access to it
session_start();

// 2. Unset all session variables
$_SESSION = array();

// 3. Destroy the session entirely
session_destroy();

// 4. Redirect the user back to the login page (index.php)
header("Location: index.php");
exit();
?>