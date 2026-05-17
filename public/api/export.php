<?php
/**
 * AtomQuest — CSV Export API
 */

require_once __DIR__ . '/../../includes/auth.php';
require_role('admin', 'manager');

$pdo = get_db();
$cycle = get_active_cycle();
$quarter = $_GET['quarter'] ?? 'Q1';
$dept_filter = $_GET['department'] ?? '';

if (!$cycle) {
    http_response_code(400);
    echo 'No active cycle';
    exit;
}

$dept_where = $dept_filter ? "AND u.department = ?" : "";
$params = [$cycle['id']];
if ($dept_filter) $params[] = $dept_filter;

$stmt = $pdo->prepare("
    SELECT u.employee_id, u.name, u.department, g.title AS goal_title,
           t.name AS thrust_area, g.uom_type, g.target_value, g.target_date, g.weightage,
           a.actual_value, a.completion_date, a.computed_score, a.status AS achievement_status, a.is_late_entry
    FROM users u
    LEFT JOIN goal_sheets gs ON gs.user_id = u.id AND gs.cycle_id = ?
    LEFT JOIN goals g ON g.goal_sheet_id = gs.id AND g.is_deleted = FALSE
    LEFT JOIN thrust_areas t ON t.id = g.thrust_area_id
    LEFT JOIN achievements a ON a.goal_id = g.id AND a.quarter = '$quarter'
    WHERE u.role IN ('employee','manager') AND u.is_active = TRUE $dept_where
    ORDER BY u.department, u.name, g.sort_order
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Output CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="achievement_report_' . $quarter . '_' . date('Ymd') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Employee ID', 'Name', 'Department', 'Goal', 'Thrust Area', 'UoM', 'Target Value', 'Target Date', 'Weightage', 'Actual Value', 'Completion Date', 'Score', 'Status', 'Late Entry']);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['employee_id'], $r['name'], $r['department'],
        $r['goal_title'] ?? '', $r['thrust_area'] ?? '',
        $r['uom_type'] ? uom_label($r['uom_type']) : '',
        $r['target_value'] ?? '', $r['target_date'] ?? '',
        $r['weightage'] ?? '',
        $r['actual_value'] ?? '', $r['completion_date'] ?? '',
        $r['computed_score'] ?? '', $r['achievement_status'] ?? '',
        !empty($r['is_late_entry']) ? 'Yes' : ''
    ]);
}

fclose($out);
