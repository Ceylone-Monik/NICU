<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode([]);
    exit();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) >= 2) {
    // Fetch matching mothers
    $stmt = $pdo->prepare("SELECT id, full_name, nic, phone, dob, blood_group FROM patients WHERE nic LIKE ? OR full_name LIKE ? LIMIT 5");
    $stmt->execute(["%$query%", "%$query%"]);
    $mothers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach linked infants to each mother profile found
    foreach ($mothers as &$mother) {
        $b_stmt = $pdo->prepare("SELECT baby_id, baby_name, baby_gender, birth_date, weight_kg, status, ward_name FROM babies WHERE mother_id = ?");
        $b_stmt->execute([$mother['id']]);
        $mother['babies'] = $b_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode($mothers);
} else {
    echo json_encode([]);
}