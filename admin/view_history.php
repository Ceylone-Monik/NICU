<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Include the database connection from the config folder
require_once '../config/db.php';

// SECURITY CHECK: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../index.php");
    exit();
}

// Fetch all historical tracks including babies who were deleted from the active roster upon discharge
$log_query = "
    SELECT l.*, 
           b.baby_name, b.baby_gender,
           p.full_name as mother_name, p.clinic_book_no
    FROM patient_movement_logs l
    LEFT JOIN babies b ON l.baby_id = b.baby_id
    LEFT JOIN patients p ON b.mother_id = p.id
    ORDER BY l.entered_at DESC
";
$logs_stmt = $pdo->query($log_query);
$history_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="users-table-container" style="margin-top: 30px;">
    <div class="table-header">
        <h3><i class="fas fa-history" style="color: #00ff96;"></i> Global Patient Movement & Discharge History Log</h3>
        <p style="color: #b0b0b0; font-size: 13px; margin-top: 5px;">A permanent audit trail tracking admissions, inter-ward transfers, and final discharges home.</p>
    </div>

    <?php if (empty($history_logs)): ?>
        <div class="no-users">
            <p>📭 No historical patient tracking records found in the log database system yet.</p>
        </div>
    <?php else: ?>
        <table class="users-table">
            <thead>
                <tr>
                    <th>Event Timestamp</th>
                    <th>Infant Name</th>
                    <th>Mother (Reference)</th>
                    <th>Activity Type</th>
                    <th>Movement Path</th>
                    <th>Stay Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history_logs as $log): ?>
                <tr class="clickable-history-row" onclick="openGlobalLogModal(<?php echo (int)$log['baby_id']; ?>)">
                    <td>
                        <span style="color: #ffffff; font-weight: 600;">
                            <?php echo date('M d, Y - h:i A', strtotime($log['entered_at'])); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($log['baby_name'] ?: 'Discharged Baby (ID: #'.$log['baby_id'].')'); ?></strong>
                        <br><small style="color: #777; text-transform: uppercase; font-size: 10px; font-weight: 600; letter-spacing: 0.5px;"><?php echo htmlspecialchars($log['baby_gender'] ?: 'N/A'); ?></small>
                    </td>
                    <td>
                        <?php if(!empty($log['mother_name'])): ?>
                            <?php echo htmlspecialchars($log['mother_name']); ?>
                            <br><small style="color: #00ff96; font-weight: 600;"><?php echo htmlspecialchars($log['clinic_book_no']); ?></small>
                        <?php else: ?>
                            <span style="color: #555; font-style: italic;">Archive Record</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $badge_class = 'doctor'; // Default green for standard transfer logs
                        if($log['action_type'] === 'Admission') { $badge_class = 'nurse'; } // Blue layout badge
                        if($log['action_type'] === 'Discharge') { $badge_class = 'admin'; } // Red layout badge
                        ?>
                        <span class="role-badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($log['action_type']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if($log['action_type'] === 'Admission'): ?>
                            Admitted directly into <span style="color: #00ff96; font-weight: 600;"><?php echo htmlspecialchars($log['to_ward']); ?> Ward</span>
                        <?php elseif($log['action_type'] === 'Discharge'): ?>
                            Moved from <?php echo htmlspecialchars($log['from_ward']); ?> ➔ <span style="color: #ffa502; font-weight: 600;">Discharged Home 🏁</span>
                        <?php else: ?>
                            Moved: <?php echo htmlspecialchars($log['from_ward']); ?> ➔ <span style="color: #64c8ff; font-weight: 600;"><?php echo htmlspecialchars($log['to_ward']); ?> Ward</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if($log['left_at'] !== null): ?>
                            <span style="color: #00ff96; font-weight: bold;">
                                <?php echo $log['duration_days']; ?> Days
                            </span>
                            <br><small style="color: #555; font-size: 11px;">Out: <?php echo date('M d, H:i', strtotime($log['left_at'])); ?></small>
                        <?php else: ?>
                            <span style="color: #ffa502; font-style: italic; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-spinner fa-spin"></i> Active Now
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>