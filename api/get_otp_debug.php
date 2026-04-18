<?php
// Returns the debug OTP from session (localhost fallback)
session_start();
header('Content-Type: application/json');

$otp = $_SESSION['otp_debug'] ?? null;
// Don't unset — user may refresh the page
echo json_encode(['otp' => $otp]);
