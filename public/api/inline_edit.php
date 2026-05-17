<?php
/**
 * AtomQuest — Inline Edit API (AJAX)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_role('manager', 'admin');
validate_csrf();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);
$field = $input['field'] ?? '';
$value = $input['value'] ?? '';

if (!$id || !$field) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$pdo = get_db();

// Only allow editing specific fields
$allowed_fields = ['target_value', 'weightage'];
if (!in_array($field, $allowed_fields)) {
    echo json_encode(['error' => 'Field not editable']);
    exit;
}

// Get current goal
$stmt = $pdo->prepare("SELECT g.*, gs.status, gs.version FROM goals g JOIN goal_sheets gs ON gs.id = g.goal_sheet_id WHERE g.id = ?");
$stmt->execute([$id]);
$goal = $stmt->fetch();

if (!$goal || $goal['status'] !== 'submitted') {
    echo json_encode(['error' => 'Goal not found or sheet is not in submitted state']);
    exit;
}

// Validate value
if ($field === 'weightage') {
    $value = (int)$value;
    if ($value < MIN_WEIGHTAGE || $value > MAX_WEIGHTAGE) {
        echo json_encode(['error' => "Weightage must be between " . MIN_WEIGHTAGE . " and " . MAX_WEIGHTAGE]);
        exit;
    }
} elseif ($field === 'target_value') {
    if (!is_numeric($value) || (float)$value < 0) {
        echo json_encode(['error' => 'Target must be a non-negative number']);
        exit;
    }
    $value = (float)$value;
}

$old_value = $goal[$field];

$stmt = $pdo->prepare("UPDATE goals SET $field = ? WHERE id = ?");
$stmt->execute([$value, $id]);

// Update sheet version
$pdo->prepare("UPDATE goal_sheets SET version = version + 1 WHERE id = ?")->execute([$goal['goal_sheet_id']]);

// Audit
audit_log('goals', $id, 'UPDATE', current_user_id(), $field, (string)$old_value, (string)$value);

// Get new version
$stmt = $pdo->prepare("SELECT version FROM goal_sheets WHERE id = ?");
$stmt->execute([$goal['goal_sheet_id']]);
$new_version = (int)$stmt->fetchColumn();

echo json_encode(['success' => true, 'version' => $new_version]);
