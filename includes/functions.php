<?php
/**
 * AtomQuest — Shared Helper Functions
 * Validation, scoring, formatting, notifications, shared goal sync
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/audit.php';

// ─── Output Helpers ───────────────────────────────────────────

function h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

// ─── Validation ───────────────────────────────────────────────

/**
 * Validate an entire goal sheet for submission.
 * Returns an array of error messages (empty = valid).
 */
function validate_goal_sheet(array $goals): array {
    $errors = [];
    $active_goals = array_filter($goals, fn($g) => empty($g['is_deleted']));

    if (empty($active_goals)) {
        $errors[] = 'At least one goal is required.';
        return $errors;
    }

    if (count($active_goals) > MAX_GOALS_PER_SHEET) {
        $errors[] = 'Maximum ' . MAX_GOALS_PER_SHEET . ' goals allowed. You have ' . count($active_goals) . '.';
    }

    $total_weightage = 0;
    foreach ($active_goals as $i => $goal) {
        $num = $i + 1;
        $w = (int)($goal['weightage'] ?? 0);

        if ($w < MIN_WEIGHTAGE) {
            $errors[] = "Goal #$num: Weightage must be at least " . MIN_WEIGHTAGE . "%. Got $w%.";
        }
        if ($w > MAX_WEIGHTAGE) {
            $errors[] = "Goal #$num: Weightage cannot exceed " . MAX_WEIGHTAGE . "%.";
        }
        $total_weightage += $w;

        if (empty(trim($goal['title'] ?? ''))) {
            $errors[] = "Goal #$num: Title is required.";
        }
        if (empty($goal['thrust_area_id'])) {
            $errors[] = "Goal #$num: Thrust Area is required.";
        }
        if (empty($goal['uom_type']) || !isset(UOM_TYPES[$goal['uom_type']])) {
            $errors[] = "Goal #$num: Invalid Unit of Measurement.";
        }

        // Type-specific validation
        $uom = $goal['uom_type'] ?? '';
        if (in_array($uom, ['numeric_min', 'numeric_max', 'percent_min', 'percent_max'])) {
            if (!is_numeric($goal['target_value'] ?? '') || (float)$goal['target_value'] < 0) {
                $errors[] = "Goal #$num: Target must be a non-negative number.";
            }
        } elseif ($uom === 'timeline') {
            if (empty($goal['target_date'])) {
                $errors[] = "Goal #$num: Target date is required for timeline goals.";
            }
        }
        // zero-based needs no target
    }

    if ($total_weightage !== REQUIRED_TOTAL_WEIGHTAGE) {
        $errors[] = "Total weightage is {$total_weightage}%. It must equal exactly " . REQUIRED_TOTAL_WEIGHTAGE . "%.";
    }

    return $errors;
}

/**
 * Sanitize a string input
 */
function sanitize(string $val): string {
    return trim($val);
}

/**
 * Validate numeric input
 */
function validate_numeric($val, float $min = 0, float $max = 999999999.99): ?float {
    if ($val === null || $val === '') return null;
    if (!is_numeric($val)) return null;
    $f = (float)$val;
    if ($f < $min || $f > $max) return null;
    return $f;
}

// ─── Score Computation ────────────────────────────────────────

function compute_score(string $uom_type, $target, $actual): ?float {
    if ($actual === null || $actual === '') return null;

    $score = match($uom_type) {
        'numeric_min', 'percent_min' => ($target > 0) ? min(($actual / $target) * 100, SCORE_CAP) : 0,
        'numeric_max', 'percent_max' => ($actual > 0) ? min(($target / $actual) * 100, SCORE_CAP) : SCORE_CAP,
        'timeline' => (strtotime($actual) <= strtotime($target)) ? 100 : 0,
        'zero'     => ($actual == 0) ? 100 : 0,
        default    => 0,
    };

    return round((float)$score, 2);
}

/**
 * Calculate weighted average score for a goal sheet in a given quarter
 */
function compute_weighted_score(int $goal_sheet_id, string $quarter): ?float {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT g.weightage, a.computed_score
        FROM goals g
        LEFT JOIN achievements a ON a.goal_id = g.id AND a.quarter = ?
        WHERE g.goal_sheet_id = ? AND g.is_deleted = FALSE
    ");
    $stmt->execute([$quarter, $goal_sheet_id]);
    $rows = $stmt->fetchAll();

    $total_weight = 0;
    $weighted_sum = 0;
    foreach ($rows as $row) {
        if ($row['computed_score'] !== null) {
            $weighted_sum += (float)$row['computed_score'] * (int)$row['weightage'];
            $total_weight += (int)$row['weightage'];
        }
    }

    return $total_weight > 0 ? round($weighted_sum / $total_weight, 2) : null;
}

// ─── Cycle Helpers ────────────────────────────────────────────

function get_active_cycle(): ?array {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM appraisal_cycles WHERE is_active = TRUE LIMIT 1");
    return $stmt->fetch() ?: null;
}

function get_current_quarter(?array $cycle = null): ?string {
    $cycle = $cycle ?? get_active_cycle();
    if (!$cycle) return null;

    $today = date('Y-m-d');
    if ($today >= $cycle['q4_opens'] && $today <= $cycle['q4_closes']) return 'Q4';
    if ($today >= $cycle['q3_opens'] && $today <= $cycle['q3_closes']) return 'Q3';
    if ($today >= $cycle['q2_opens'] && $today <= $cycle['q2_closes']) return 'Q2';
    if ($today >= $cycle['q1_opens'] && $today <= $cycle['q1_closes']) return 'Q1';

    return null;
}

function is_goal_setting_open(?array $cycle = null): bool {
    $cycle = $cycle ?? get_active_cycle();
    if (!$cycle) return false;
    $today = date('Y-m-d');
    return $today >= $cycle['goal_setting_opens'] && $today <= $cycle['goal_setting_closes'];
}

function is_quarter_open(string $quarter, ?array $cycle = null): bool {
    $cycle = $cycle ?? get_active_cycle();
    if (!$cycle) return false;
    $today = date('Y-m-d');
    $q = strtolower($quarter);
    return $today >= $cycle[$q . '_opens'] && $today <= $cycle[$q . '_closes'];
}

function is_quarter_past(string $quarter, ?array $cycle = null): bool {
    $cycle = $cycle ?? get_active_cycle();
    if (!$cycle) return false;
    $today = date('Y-m-d');
    $q = strtolower($quarter);
    return $today > $cycle[$q . '_closes'];
}

// ─── Goal Sheet Helpers ───────────────────────────────────────

function get_goal_sheet(int $user_id, int $cycle_id): ?array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM goal_sheets WHERE user_id = ? AND cycle_id = ?");
    $stmt->execute([$user_id, $cycle_id]);
    return $stmt->fetch() ?: null;
}

function get_goals_for_sheet(int $sheet_id, bool $include_deleted = false): array {
    $pdo = get_db();
    $where = $include_deleted ? '' : 'AND g.is_deleted = FALSE';
    $stmt = $pdo->prepare("
        SELECT g.*, t.name AS thrust_area_name
        FROM goals g
        JOIN thrust_areas t ON t.id = g.thrust_area_id
        WHERE g.goal_sheet_id = ? $where
        ORDER BY g.sort_order, g.id
    ");
    $stmt->execute([$sheet_id]);
    return $stmt->fetchAll();
}

function get_thrust_areas(?string $department = null): array {
    $pdo = get_db();
    if ($department) {
        $stmt = $pdo->prepare("SELECT * FROM thrust_areas WHERE is_active = TRUE AND (department IS NULL OR department = ?) ORDER BY name");
        $stmt->execute([$department]);
    } else {
        $stmt = $pdo->query("SELECT * FROM thrust_areas WHERE is_active = TRUE ORDER BY name");
    }
    return $stmt->fetchAll();
}

// ─── Shared Goal Sync ─────────────────────────────────────────

function sync_shared_achievements(int $source_goal_id, string $quarter): void {
    $pdo = get_db();

    // Get the source achievement
    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE goal_id = ? AND quarter = ?");
    $stmt->execute([$source_goal_id, $quarter]);
    $source = $stmt->fetch();
    if (!$source) return;

    // Find all linked goals
    $stmt = $pdo->prepare("SELECT id FROM goals WHERE shared_source_id = ? AND is_deleted = FALSE");
    $stmt->execute([$source_goal_id]);
    $linked = $stmt->fetchAll();

    foreach ($linked as $goal) {
        $stmt = $pdo->prepare("
            INSERT INTO achievements (goal_id, quarter, actual_value, completion_date, status, computed_score, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (goal_id, quarter) DO UPDATE SET
                actual_value = EXCLUDED.actual_value,
                completion_date = EXCLUDED.completion_date,
                status = EXCLUDED.status,
                computed_score = EXCLUDED.computed_score,
                updated_at = NOW()
        ");
        $stmt->execute([
            $goal['id'], $quarter,
            $source['actual_value'], $source['completion_date'],
            $source['status'], $source['computed_score'],
            $source['updated_by']
        ]);

        audit_log('achievements', $goal['id'], 'SYNC', null, null, null,
            json_encode(['synced_from' => $source_goal_id, 'quarter' => $quarter]));
    }
}

// ─── Notification Helpers ─────────────────────────────────────

function create_notification(int $user_id, string $type, string $message, ?string $link = null): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $message, $link]);
}

function get_unread_notifications(int $user_id, int $limit = 10): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ? AND is_read = FALSE
        ORDER BY created_at DESC LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function get_unread_count(int $user_id): int {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function mark_notification_read(int $id, int $user_id): void {
    $pdo = get_db();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
}

// ─── Team Helpers ─────────────────────────────────────────────

function get_team_members(int $manager_id): array {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE manager_id = ? AND is_active = TRUE ORDER BY name");
    $stmt->execute([$manager_id]);
    return $stmt->fetchAll();
}

function get_all_users(bool $active_only = true): array {
    $pdo = get_db();
    $where = $active_only ? 'WHERE is_active = TRUE' : '';
    return $pdo->query("SELECT * FROM users $where ORDER BY name")->fetchAll();
}

// ─── Status Badge Helper ─────────────────────────────────────

function status_badge(string $status): string {
    $class = match($status) {
        'draft'       => 'badge-secondary',
        'submitted'   => 'badge-primary',
        'returned'    => 'badge-warning',
        'approved'    => 'badge-success',
        'locked'      => 'badge-info',
        'not_started' => 'badge-secondary',
        'on_track'    => 'badge-primary',
        'completed'   => 'badge-success',
        default       => 'badge-secondary',
    };
    $label = ucwords(str_replace('_', ' ', $status));
    return "<span class=\"badge $class\">$label</span>";
}

function uom_label(string $uom_type): string {
    return UOM_TYPES[$uom_type] ?? $uom_type;
}
