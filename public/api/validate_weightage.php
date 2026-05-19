<?php
/**
 * NexusSync - Weightage Validation API (AJAX)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_auth();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$goals = $input['goals'] ?? [];

$errors = validate_goal_sheet($goals);

echo json_encode([
    'valid'  => empty($errors),
    'errors' => $errors,
    'total_weightage' => array_sum(array_column($goals, 'weightage')),
    'goal_count' => count($goals),
]);
