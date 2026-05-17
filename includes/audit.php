<?php
/**
 * AtomQuest — Audit Trail Logger
 */

require_once __DIR__ . '/db.php';

/**
 * Log a single audit entry
 */
function audit_log(
    string $table_name,
    int $record_id,
    string $action,
    ?int $changed_by = null,
    ?string $field_name = null,
    ?string $old_value = null,
    ?string $new_value = null,
    ?string $reason = null
): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (table_name, record_id, action, field_name, old_value, new_value, changed_by, ip_address, reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $table_name,
        $record_id,
        $action,
        $field_name,
        $old_value,
        $new_value,
        $changed_by ?? ($_SESSION['user_id'] ?? null),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $reason,
    ]);
}

/**
 * Log multiple field changes in one call (for row-level auditing)
 */
function audit_log_fields(
    string $table_name,
    int $record_id,
    string $action,
    array $old_values,
    array $new_values,
    ?int $changed_by = null,
    ?string $reason = null
): void {
    foreach ($new_values as $field => $new_val) {
        $old_val = $old_values[$field] ?? null;
        if ((string)$old_val !== (string)$new_val) {
            audit_log($table_name, $record_id, $action, $changed_by, $field, (string)$old_val, (string)$new_val, $reason);
        }
    }
}

/**
 * Log a row-level change with JSON old/new values
 */
function audit_log_row(
    string $table_name,
    int $record_id,
    string $action,
    ?array $old_row = null,
    ?array $new_row = null,
    ?int $changed_by = null,
    ?string $reason = null
): void {
    audit_log(
        $table_name,
        $record_id,
        $action,
        $changed_by,
        null,
        $old_row ? json_encode($old_row) : null,
        $new_row ? json_encode($new_row) : null,
        $reason
    );
}
