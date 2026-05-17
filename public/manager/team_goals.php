<?php
/**
 * AtomQuest — Manager Team Goals List
 */
$page_title = 'Team Goals';
require_once __DIR__ . '/../../includes/auth.php';
require_role('manager');
// Redirect to dashboard which has the team view
redirect('/manager/dashboard.php');
