<?php
/**
 * AtomQuest — Unit Tests for Critical Validation Logic
 * Run: php tests/test_validation.php
 * Exits 0 on pass, 1 on failure.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$passed = 0;
$failed = 0;

function assert_eq($expected, $actual, string $test): void {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ $test\n";
        $passed++;
    } else {
        echo "  ✗ $test — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failed++;
    }
}

function assert_true($val, string $test): void { assert_eq(true, $val, $test); }
function assert_false($val, string $test): void { assert_eq(false, $val, $test); }

// ─── Test 1: Weightage Sum Validation ────────────────────
echo "\n== Test: Weightage Validation ==\n";

$valid_goals = [
    ['title' => 'A', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 50],
    ['title' => 'B', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 50],
];
$errors = validate_goal_sheet($valid_goals);
assert_eq(0, count($errors), 'Valid sheet (50+50=100) passes');

$under = [
    ['title' => 'A', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 40],
    ['title' => 'B', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 40],
];
$errors = validate_goal_sheet($under);
assert_true(count($errors) > 0, 'Under 100% (80%) fails');

$over = [
    ['title' => 'A', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 60],
    ['title' => 'B', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 60],
];
$errors = validate_goal_sheet($over);
assert_true(count($errors) > 0, 'Over 100% (120%) fails');

// ─── Test 2: Goal Count Limits ───────────────────────────
echo "\n== Test: Goal Count ==\n";

$goals_9 = [];
for ($i = 0; $i < 9; $i++) {
    $goals_9[] = ['title' => "G$i", 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 10, 'weightage' => 11];
}
$errors = validate_goal_sheet($goals_9);
assert_true(count($errors) > 0, '9 goals rejected');

$goals_1 = [
    ['title' => 'Only', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 100],
];
$errors = validate_goal_sheet($goals_1);
assert_eq(0, count($errors), '1 goal at 100% passes');

// ─── Test 3: Min Weightage ───────────────────────────────
echo "\n== Test: Min Weightage ==\n";

$low_weight = [
    ['title' => 'A', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 5],
    ['title' => 'B', 'thrust_area_id' => 1, 'uom_type' => 'numeric_min', 'target_value' => 100, 'weightage' => 95],
];
$errors = validate_goal_sheet($low_weight);
assert_true(count($errors) > 0, 'Weightage 5% rejected (min is 10%)');

// ─── Test 4: Score Computation ───────────────────────────
echo "\n== Test: Score Computation ==\n";

// numeric_min (higher is better)
assert_eq(100.0, compute_score('numeric_min', 100, 100), 'numeric_min: 100/100 = 100%');
assert_eq(50.0, compute_score('numeric_min', 100, 50), 'numeric_min: 50/100 = 50%');
assert_eq(150.0, compute_score('numeric_min', 100, 200), 'numeric_min: 200/100 capped at 150%');
assert_eq(null, compute_score('numeric_min', 100, null), 'numeric_min: null actual = null');

// numeric_max (lower is better)
assert_eq(100.0, compute_score('numeric_max', 50, 50), 'numeric_max: 50/50 = 100%');
assert_eq(50.0, compute_score('numeric_max', 50, 100), 'numeric_max: 50/100 = 50%');

// zero-based
assert_eq(100.0, compute_score('zero', 0, 0), 'zero: 0 incidents = 100%');
assert_eq(0.0, compute_score('zero', 0, 5), 'zero: 5 incidents = 0%');

// timeline
assert_eq(100.0, compute_score('timeline', '2026-12-31', '2026-12-01'), 'timeline: early completion = 100%');
assert_eq(0.0, compute_score('timeline', '2026-12-31', '2027-01-15'), 'timeline: late completion = 0%');

// Division by zero guard
assert_eq(0.0, compute_score('numeric_min', 0, 50), 'numeric_min: target=0 guard');
assert_eq(150.0, compute_score('numeric_max', 50, 0), 'numeric_max: actual=0 = capped');

// ─── Results ─────────────────────────────────────────────
echo "\n== Results: $passed passed, $failed failed ==\n\n";
exit($failed > 0 ? 1 : 0);
