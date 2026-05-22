<?php
session_start();
require_once '../config/db.php';

// Security: Allow logged-in Admin or Doctor roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Doctor'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['baby_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.*, 
                   p.full_name as mother_name, p.dob as mother_dob, p.nic as mother_nic,
                   p.phone as mother_phone, p.blood_group as mother_blood, 
                   p.clinic_book_no, p.lmp_date, p.edd_date, p.gravida, p.para,
                   p.pregnancy_risk_factors, p.guardian_name, p.guardian_relation,
                   p.emergency_contact_name, p.emergency_phone, p.allergies as mother_allergies
            FROM babies b 
            JOIN patients p ON b.mother_id = p.id 
            WHERE b.baby_id = ?
        ");
        $stmt->execute([$_GET['baby_id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            echo json_encode($data);
        } else {
            echo json_encode(['error' => 'Infant record not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Missing Baby ID parameter']);
}