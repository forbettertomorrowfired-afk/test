<?php
/**
 * NexusSync - Notifications API (AJAX)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_auth();

header('Content-Type: application/json');

$notifications = get_unread_notifications(current_user_id(), 20);

// Format for JSON
$result = array_map(function($n) {
    return [
        'id'         => $n['id'],
        'type'       => $n['type'],
        'message'    => $n['message'],
        'link'       => $n['link'],
        'created_at' => date('d M Y H:i', strtotime($n['created_at'])),
    ];
}, $notifications);

echo json_encode($result);
