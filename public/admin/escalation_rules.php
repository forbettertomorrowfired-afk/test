<?php
/**
 * NexusSync - Escalation Rules Management (Bonus)
 */

$page_title = 'Escalation Rules';
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = get_db();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'run_check') {
        // Run escalation checks
        $cycle = get_active_cycle();
        if (!$cycle) {
            flash('error', 'No active cycle.');
            redirect('/admin/escalation_rules.php');
        }

        $rules = $pdo->query("SELECT * FROM escalation_rules WHERE is_active = TRUE")->fetchAll();
        $triggered = 0;

        foreach ($rules as $rule) {
            $users_to_notify = [];

            if ($rule['trigger_type'] === 'goal_not_submitted') {
                $cutoff = date('Y-m-d', strtotime($cycle['goal_setting_opens'] . " +{$rule['delay_days']} days"));
                if (date('Y-m-d') >= $cutoff) {
                    $stmt = $pdo->prepare("
                        SELECT u.id FROM users u
                        LEFT JOIN goal_sheets gs ON gs.user_id = u.id AND gs.cycle_id = ?
                        WHERE u.role IN ('employee','manager') AND u.is_active = TRUE
                        AND (gs.id IS NULL OR gs.status = 'draft')
                    ");
                    $stmt->execute([$cycle['id']]);
                    $users_to_notify = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
            } elseif ($rule['trigger_type'] === 'not_approved') {
                $stmt = $pdo->prepare("
                    SELECT gs.user_id FROM goal_sheets gs
                    WHERE gs.cycle_id = ? AND gs.status = 'submitted'
                    AND gs.submitted_at <= NOW() - INTERVAL '{$rule['delay_days']} days'
                ");
                $stmt->execute([$cycle['id']]);
                $users_to_notify = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($rule['trigger_type'] === 'checkin_pending') {
                $current_q = get_current_quarter($cycle);
                if ($current_q && is_quarter_open($current_q, $cycle)) {
                    $q_opens_key = strtolower($current_q) . '_opens';
                    $cutoff = date('Y-m-d', strtotime($cycle[$q_opens_key] . " +{$rule['delay_days']} days"));
                    if (date('Y-m-d') >= $cutoff) {
                        $stmt = $pdo->prepare("
                            SELECT gs.user_id FROM goal_sheets gs
                            WHERE gs.cycle_id = ? AND gs.status IN ('approved','locked')
                            AND gs.user_id NOT IN (
                                SELECT DISTINCT a.updated_by FROM achievements a
                                JOIN goals g ON g.id = a.goal_id
                                WHERE g.goal_sheet_id = gs.id AND a.quarter = ?
                            )
                        ");
                        $stmt->execute([$cycle['id'], $current_q]);
                        $users_to_notify = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                }
            }

            foreach ($users_to_notify as $uid) {
                // De-duplicate: check if already escalated
                $check = $pdo->prepare("SELECT COUNT(*) FROM escalation_log WHERE rule_id = ? AND user_id = ? AND cycle_id = ? AND resolved_at IS NULL");
                $check->execute([$rule['id'], $uid, $cycle['id']]);
                if ((int)$check->fetchColumn() > 0) continue;

                // Log escalation
                $pdo->prepare("INSERT INTO escalation_log (rule_id, user_id, cycle_id) VALUES (?,?,?)")
                    ->execute([$rule['id'], $uid, $cycle['id']]);

                // Determine notification target
                $notify_uid = $uid;
                if ($rule['notify_target'] === 'manager') {
                    $mgr = $pdo->prepare("SELECT manager_id FROM users WHERE id = ?");
                    $mgr->execute([$uid]);
                    $notify_uid = (int)$mgr->fetchColumn() ?: $uid;
                } elseif ($rule['notify_target'] === 'hr') {
                    $hr = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
                    $notify_uid = $hr ?: $uid;
                }

                create_notification($notify_uid, 'escalation', "Escalation: {$rule['rule_name']} triggered.", '/admin/escalation_rules.php');
                $triggered++;
            }
        }

        flash('success', "Escalation check complete. $triggered new escalation(s) triggered.");
        redirect('/admin/escalation_rules.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE escalation_rules SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        flash('success', 'Rule toggled.');
        redirect('/admin/escalation_rules.php');
    }
}

$rules = $pdo->query("SELECT * FROM escalation_rules ORDER BY id")->fetchAll();
$esc_logs = $pdo->query("SELECT el.*, er.rule_name, u.name AS user_name
    FROM escalation_log el JOIN escalation_rules er ON er.id = el.rule_id
    JOIN users u ON u.id = el.user_id ORDER BY el.triggered_at DESC LIMIT 50")->fetchAll();

include __DIR__ . '/../../includes/layout/header.php';
?>

<h1>Escalation Rules</h1>

<div class="card">
    <div class="card-header">
        <h2>Rules</h2>
        <form method="POST" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="run_check">
            <button type="submit" class="btn btn-primary btn-sm">▶ Run Escalation Check</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rule</th><th>Trigger</th><th>Delay (days)</th><th>Notify</th><th>Active</th><th>Toggle</th></tr></thead>
            <tbody>
                <?php foreach ($rules as $r): ?>
                <tr>
                    <td><?= h($r['rule_name']) ?></td>
                    <td><code><?= h($r['trigger_type']) ?></code></td>
                    <td><?= $r['delay_days'] ?></td>
                    <td><?= ucfirst($r['notify_target']) ?></td>
                    <td><?= $r['is_active'] ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' ?></td>
                    <td>
                        <form method="POST" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Escalation Log -->
<div class="card">
    <div class="card-header"><h2>Escalation Log</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Rule</th><th>Employee</th><th>Triggered</th><th>Resolved</th><th>Note</th></tr></thead>
            <tbody>
                <?php foreach ($esc_logs as $l): ?>
                <tr>
                    <td><?= h($l['rule_name']) ?></td>
                    <td><?= h($l['user_name']) ?></td>
                    <td><?= date('d M Y H:i', strtotime($l['triggered_at'])) ?></td>
                    <td><?= $l['resolved_at'] ? date('d M Y', strtotime($l['resolved_at'])) : '<span class="badge badge-warning">Pending</span>' ?></td>
                    <td><?= h($l['resolution_note'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($esc_logs)): ?>
                <tr><td colspan="5" class="text-center text-muted">No escalations yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/layout/footer.php'; ?>
