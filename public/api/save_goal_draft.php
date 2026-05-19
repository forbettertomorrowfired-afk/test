<?php
/**
 * NexusSync - Save Goal Draft API (AJAX)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_auth();
// Redirect to goal_create.php which handles saving
redirect('/employee/goal_create.php');
