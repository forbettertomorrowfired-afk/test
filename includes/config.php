<?php
/**
 * AtomQuest — Application Configuration
 */

// Database — Railway provides DATABASE_URL and PG* env vars
$db_url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? ($_SERVER['DATABASE_URL'] ?? null));

if ($db_url) {
    $parsed = parse_url($db_url);
    define('DB_HOST', $parsed['host']);
    define('DB_PORT', $parsed['port'] ?? 5432);
    define('DB_NAME', ltrim($parsed['path'], '/'));
    define('DB_USER', $parsed['user']);
    define('DB_PASS', $parsed['pass'] ?? '');
} elseif (getenv('PGHOST') || isset($_ENV['PGHOST']) || isset($_SERVER['PGHOST'])) {
    define('DB_HOST', getenv('PGHOST') ?: ($_ENV['PGHOST'] ?? $_SERVER['PGHOST']));
    define('DB_PORT', getenv('PGPORT') ?: ($_ENV['PGPORT'] ?? ($_SERVER['PGPORT'] ?? 5432)));
    define('DB_NAME', getenv('PGDATABASE') ?: ($_ENV['PGDATABASE'] ?? $_SERVER['PGDATABASE']));
    define('DB_USER', getenv('PGUSER') ?: ($_ENV['PGUSER'] ?? $_SERVER['PGUSER']));
    define('DB_PASS', getenv('PGPASSWORD') ?: ($_ENV['PGPASSWORD'] ?? ($_SERVER['PGPASSWORD'] ?? '')));
} else {
    // Local development defaults — socket path must match how pg was started
    define('DB_HOST', '/tmp/pg_runtime');
    define('DB_PORT', 5432);
    define('DB_NAME', 'atomquest');
    define('DB_USER', 'pranav');
    define('DB_PASS', '');
}

// Application
define('APP_NAME', 'AtomQuest');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');

// Paths
define('ROOT_DIR', dirname(__DIR__));
define('INCLUDES_DIR', __DIR__);
define('PUBLIC_DIR', ROOT_DIR . '/public');

// Score cap
define('SCORE_CAP', 150.0);

// Quarters
define('QUARTERS', ['Q1', 'Q2', 'Q3', 'Q4']);

// UoM types
define('UOM_TYPES', [
    'numeric_min'  => 'Numeric (Higher is Better)',
    'numeric_max'  => 'Numeric (Lower is Better)',
    'percent_min'  => '% (Higher is Better)',
    'percent_max'  => '% (Lower is Better)',
    'timeline'     => 'Timeline (Date-based)',
    'zero'         => 'Zero-based (Zero = Success)',
]);

// Goal constraints
define('MAX_GOALS_PER_SHEET', 8);
define('MIN_WEIGHTAGE', 10);
define('MAX_WEIGHTAGE', 100);
define('REQUIRED_TOTAL_WEIGHTAGE', 100);

// Statuses
define('GOAL_SHEET_STATUSES', ['draft', 'submitted', 'returned', 'approved', 'locked']);
define('ACHIEVEMENT_STATUSES', ['not_started', 'on_track', 'completed']);
