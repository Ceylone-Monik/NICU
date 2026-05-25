<?php
session_start();
require_once '../config/db.php';

// Security: Allow logged-in Admin or Doctor roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Admin', 'Doctor'])) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (isset($_GET['baby_id']) || isset($_GET['bed_number'])) {
    try {
        $baby_id = null;
        if (isset($_GET['baby_id'])) {
            $baby_id = (int)$_GET['baby_id'];
        } else {
            // Find baby ID associated with this bed number
            // Check patient_movement_logs first (historical and current stays)
            $find_stmt = $pdo->prepare("SELECT DISTINCT baby_id FROM patient_movement_logs WHERE bed_number = ? LIMIT 1");
            $find_stmt->execute([$_GET['bed_number']]);
            $baby_id = $find_stmt->fetchColumn();
            
            // If not found in logs, check babies table directly
            if (!$baby_id) {
                $find_stmt = $pdo->prepare("SELECT baby_id FROM babies WHERE admission_bed_number = ? LIMIT 1");
                $find_stmt->execute([$_GET['bed_number']]);
                $baby_id = $find_stmt->fetchColumn();
            }
        }

        if (!$baby_id) {
            echo json_encode(['error' => 'No infant record linked to this stay period or bed number']);
            exit();
        }

        // Fetch unified details mapping baby metrics and maternal information profiles
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
        $stmt->execute([$baby_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            // Fetch distinct bed numbers this baby has stayed in for stay period selection
            $stays_stmt = $pdo->prepare("SELECT DISTINCT bed_number FROM patient_movement_logs WHERE baby_id = ? AND bed_number IS NOT NULL ORDER BY entered_at DESC");
            $stays_stmt->execute([$baby_id]);
            $data['all_stays'] = $stays_stmt->fetchAll(PDO::FETCH_COLUMN);

            // Filter movement history by the specified bed_number, or fall back to the baby's current admission_bed_number
            $bed_number = isset($_GET['bed_number']) ? $_GET['bed_number'] : $data['admission_bed_number'];
            if (!$bed_number && !empty($data['all_stays'])) {
                $bed_number = $data['all_stays'][0];
            }
            
            if ($bed_number) {
                $log_stmt = $pdo->prepare("SELECT * FROM patient_movement_logs WHERE baby_id = ? AND bed_number = ? ORDER BY entered_at ASC");
                $log_stmt->execute([$baby_id, $bed_number]);
            } else {
                $log_stmt = $pdo->prepare("SELECT * FROM patient_movement_logs WHERE baby_id = ? ORDER BY entered_at ASC");
                $log_stmt->execute([$baby_id]);
            }
            $data['movement_history'] = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
            $data['selected_bed_number'] = $bed_number;

            echo json_encode($data);
        } else {
            echo json_encode(['error' => 'Infant record not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Missing Baby ID or Bed Number parameter']);
}