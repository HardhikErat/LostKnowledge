<?php
// Returns the debug reset link from session (localhost fallback)
session_start();
header('Content-Type: application/json');

$link = $_SESSION['reset_link_debug'] ?? null;
if ($link) {
    unset($_SESSION['reset_link_debug']);
    echo json_encode(['link' => $link]);
} else {
    echo json_encode(['link' => null]);
}
