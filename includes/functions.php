<?php
// includes/functions.php

function sendNotification($pdo, $to_user, $type, $ref_id) {
    // Don't notify if the user is acting on their own content
    if ($to_user != $_SESSION['user_id']) {
        $pdo->prepare("INSERT INTO notifications (user_id, actor_id, type, reference_id) VALUES (?, ?, ?, ?)")
            ->execute([$to_user, $_SESSION['user_id'], $type, $ref_id]);
    }
}
?>